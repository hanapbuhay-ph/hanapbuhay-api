<?php

namespace App\Services\Media;

use Cloudinary\Asset\File;
use Cloudinary\Asset\Image;
use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStorageService
{
    private const PUBLIC_PREFIX = 'cloudinary:public:';

    private const PRIVATE_PREFIX = 'cloudinary:private:';

    private const LOCAL_PRIVATE_PREFIX = 'local:private:';

    private ?Cloudinary $cloudinary = null;

    public function storePublicFile(UploadedFile $file, string $path): string
    {
        if (! $this->usesCloudinary()) {
            return $file->storeAs(dirname($path), basename($path), 'public');
        }

        return $this->uploadPublic($file->getRealPath(), $path, $file->getClientOriginalExtension());
    }

    public function storePublicContents(string $contents, string $path, string $format): string
    {
        if (! $this->usesCloudinary()) {
            $stored = Storage::disk('public')->put($path, $contents);
            if (! $stored) {
                throw new RuntimeException('Unable to store media file.');
            }

            return $path;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'hanapbuhay-media-');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to prepare media file for upload.');
        }

        try {
            if (file_put_contents($tempPath, $contents) === false) {
                throw new RuntimeException('Unable to prepare media file for upload.');
            }

            return $this->uploadPublic($tempPath, $path, $format);
        } finally {
            @unlink($tempPath);
        }
    }

    public function storePrivateFile(UploadedFile $file, string $path): string
    {
        if (! $this->usesCloudinary()) {
            $storedPath = $file->storeAs(dirname($path), basename($path), 'private');
            if (! is_string($storedPath)) {
                throw new RuntimeException('Unable to store private media file.');
            }

            return self::LOCAL_PRIVATE_PREFIX.$storedPath;
        }

        $publicId = trim($path, './');
        $result = $this->cloudinary()->uploadApi()->upload($file->getRealPath(), [
            'resource_type' => 'raw',
            'type' => 'authenticated',
            'public_id' => $publicId,
            'overwrite' => true,
            'use_filename' => false,
            'unique_filename' => false,
        ]);

        return self::PRIVATE_PREFIX.$result['public_id'];
    }

    public function delete(?string $path): bool
    {
        if ($path === null || $path === '') {
            return true;
        }

        if (str_starts_with($path, self::PUBLIC_PREFIX)) {
            return $this->destroy(substr($path, strlen(self::PUBLIC_PREFIX)), 'image', 'upload');
        }

        if (str_starts_with($path, self::PRIVATE_PREFIX)) {
            return $this->destroy(substr($path, strlen(self::PRIVATE_PREFIX)), 'raw', 'authenticated');
        }

        if (str_starts_with($path, self::LOCAL_PRIVATE_PREFIX)) {
            return Storage::disk('private')->delete(substr($path, strlen(self::LOCAL_PRIVATE_PREFIX)));
        }

        if (preg_match('~^https?://~i', $path)) {
            return true;
        }

        return Storage::disk('public')->delete($path);
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    public function deleteMany(array $paths): bool
    {
        $deleted = true;

        foreach ($paths as $path) {
            $deleted = $this->delete($path) && $deleted;
        }

        return $deleted;
    }

    public function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        if (str_starts_with($path, self::PUBLIC_PREFIX)) {
            return (string) Image::upload(
                substr($path, strlen(self::PUBLIC_PREFIX)),
                $this->cloudinary()->configuration,
            )->toUrl();
        }

        return asset('storage/'.$path);
    }

    public function privateUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return URL::temporarySignedRoute(
            'media.private',
            now()->addMinutes(max(1, config('media.private_url_minutes', 5))),
            ['media' => Crypt::encryptString($path)],
        );
    }

    public function responseForPrivateMedia(string $path): StreamedResponse
    {
        if (str_starts_with($path, self::PRIVATE_PREFIX)) {
            $publicId = substr($path, strlen(self::PRIVATE_PREFIX));
            $url = (string) File::authenticated(
                $publicId,
                $this->cloudinary()->configuration,
            )->signUrl()->toUrl();

            $cloudResponse = Http::timeout(30)->withOptions(['stream' => true])->get($url);
            if (! $cloudResponse->successful()) {
                throw new RuntimeException('Private media could not be retrieved from cloud storage.');
            }

            $contentType = $cloudResponse->header('Content-Type', 'application/octet-stream');

            return response()->stream(function () use ($cloudResponse): void {
                $stream = $cloudResponse->toPsrResponse()->getBody();
                while (! $stream->eof()) {
                    echo $stream->read(8192);
                }
            }, 200, [
                'Content-Type' => $contentType,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'Content-Disposition' => 'inline',
            ]);
        }

        $disk = str_starts_with($path, self::LOCAL_PRIVATE_PREFIX) ? 'private' : 'public';
        $storagePath = $disk === 'private'
            ? substr($path, strlen(self::LOCAL_PRIVATE_PREFIX))
            : $path;
        $storage = Storage::disk($disk);
        if (! $storage->exists($storagePath)) {
            abort(404);
        }

        $contentType = $storage->mimeType($storagePath) ?: 'application/octet-stream';

        return response()->stream(function () use ($disk, $storagePath): void {
            $stream = Storage::disk($disk)->readStream($storagePath);
            if ($stream === false) {
                throw new RuntimeException('Private media could not be read from storage.');
            }

            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Content-Disposition' => 'inline',
        ]);
    }

    private function uploadPublic(string $file, string $path, string $format): string
    {
        $publicId = $this->publicId($path);
        $result = $this->cloudinary()->uploadApi()->upload($file, [
            'resource_type' => 'image',
            'type' => 'upload',
            'public_id' => $publicId,
            'overwrite' => true,
            'use_filename' => false,
            'unique_filename' => false,
            'format' => strtolower($format) === 'jpg' ? 'jpg' : $format,
        ]);

        return self::PUBLIC_PREFIX.$result['public_id'];
    }

    private function publicId(string $path): string
    {
        $withoutExtension = pathinfo($path, PATHINFO_DIRNAME).'/'.pathinfo($path, PATHINFO_FILENAME);

        return trim($withoutExtension, './');
    }

    private function destroy(string $publicId, string $resourceType, string $type): bool
    {
        $result = $this->cloudinary()->uploadApi()->destroy($publicId, [
            'resource_type' => $resourceType,
            'type' => $type,
            'invalidate' => true,
        ]);

        return ($result['result'] ?? null) === 'ok' || ($result['result'] ?? null) === 'not found';
    }

    private function usesCloudinary(): bool
    {
        return config('media.disk') === 'cloudinary';
    }

    private function cloudinary(): Cloudinary
    {
        if ($this->cloudinary !== null) {
            return $this->cloudinary;
        }

        $cloudinaryUrl = config('media.cloudinary_url');
        if (! is_string($cloudinaryUrl) || $cloudinaryUrl === '') {
            throw new RuntimeException('Cloudinary is selected but CLOUDINARY_URL is missing.');
        }

        return $this->cloudinary = new Cloudinary($cloudinaryUrl);
    }
}

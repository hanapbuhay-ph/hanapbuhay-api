<?php

namespace App\Services\JobPost;

use App\Models\JobPost;
use App\Models\JobPostImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class JobPostImageService
{
    public const MAX_IMAGES = 10;

    private const MAX_DIMENSION = 1200;

    private const THUMB_SIZE = 400;

    private const JPEG_QUALITY = 82;

    private function manager(): ImageManager
    {
        return new ImageManager(new Driver);
    }

    /**
     * Upload and append images to a job post.
     *
     * @param  UploadedFile[]  $files
     * @return JobPostImage[]
     */
    public function upload(JobPost $post, array $files): array
    {
        $existing = $post->images()->count();
        $nextOrder = $existing;
        $created = [];
        $encoder = new JpegEncoder(self::JPEG_QUALITY);

        foreach ($files as $file) {
            $manager = $this->manager();

            $slug = Str::uuid()->toString();
            $dir = "job_posts/{$post->id}";
            $imagePath = "{$dir}/{$slug}.jpg";
            $thumbPath = "{$dir}/{$slug}_thumb.jpg";

            // Optimized image — resize down if larger than max dimension
            $img = $manager->decode($file->getRealPath());
            if ($img->width() > self::MAX_DIMENSION || $img->height() > self::MAX_DIMENSION) {
                $img->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);
            }
            Storage::disk('public')->put($imagePath, $img->encode($encoder)->toString());

            // Thumbnail — square crop from centre
            $thumb = $manager->decode($file->getRealPath());
            $thumb->cover(self::THUMB_SIZE, self::THUMB_SIZE);
            Storage::disk('public')->put($thumbPath, $thumb->encode($encoder)->toString());

            $created[] = $post->images()->create([
                'image_path' => $imagePath,
                'thumbnail_path' => $thumbPath,
                'display_order' => $nextOrder++,
            ]);
        }

        return $created;
    }

    /**
     * Delete a single image record and its stored files.
     * Re-numbers remaining images from zero.
     */
    public function delete(JobPostImage $image): void
    {
        $post = $image->jobPost;

        DB::transaction(function () use ($image, $post): void {
            $image->delete(); // observer deletes files

            // Re-number remaining images
            $post->images()->each(function (JobPostImage $img, int $idx): void {
                $img->timestamps = false;
                $img->update(['display_order' => $idx]);
            });
        });
    }

    /**
     * Reorder images for a post.
     *
     * @param  int[]  $orderedIds  Every image ID belonging to the post, in desired order.
     */
    public function reorder(JobPost $post, array $orderedIds): void
    {
        $postImageIds = $post->images()->pluck('id')->all();

        if (
            count($orderedIds) !== count($postImageIds)
            || array_diff($orderedIds, $postImageIds) !== []
            || array_diff($postImageIds, $orderedIds) !== []
        ) {
            throw new \InvalidArgumentException(
                'image_ids must contain every image belonging to this post exactly once.'
            );
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $order => $id) {
                JobPostImage::where('id', $id)->update(['display_order' => $order]);
            }
        });
    }

    /**
     * @param  Collection<JobPostImage>  $images
     */
    public function formatImages(Collection $images): array
    {
        return $images->map(fn (JobPostImage $img) => $this->formatImage($img))->values()->all();
    }

    public function formatImage(JobPostImage $img): array
    {
        return [
            'id' => $img->id,
            'image_url' => asset('storage/'.$img->image_path),
            'thumbnail_url' => $img->thumbnail_path ? asset('storage/'.$img->thumbnail_path) : null,
            'display_order' => $img->display_order,
        ];
    }
}

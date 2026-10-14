<?php

use App\Services\Media\MediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('private');
    config(['media.disk' => 'public']);
});

it('serves local private media only through an unexpired signed URL', function () {
    $path = app(MediaStorageService::class)->storePrivateFile(
        UploadedFile::fake()->createWithContent('id.txt', 'private document'),
        'verifications/1/id.txt',
    );

    expect($path)->toStartWith('local:private:')
        ->and(Storage::disk('public')->allFiles())->toBe([]);
    expect(Storage::disk('private')->exists(substr($path, strlen('local:private:'))))->toBeTrue();

    $url = app(MediaStorageService::class)->privateUrl($path);

    $response = $this->get($url)
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($response->streamedContent())->toBe('private document');
});

it('rejects private media requests with invalid or expired signatures', function () {
    $url = app(MediaStorageService::class)->privateUrl('verifications/1/id.jpg');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    $this->get(str_replace($query['signature'], 'invalid', $url))
        ->assertForbidden();

    $this->travel(6)->minutes();

    $this->get($url)
        ->assertForbidden();
});

it('serves legacy public-disk private files only through a signed proxy URL', function () {
    Storage::disk('public')->put('messages/1/legacy.txt', 'legacy private file');
    $url = app(MediaStorageService::class)->privateUrl('messages/1/legacy.txt');

    $response = $this->get($url)->assertOk();

    expect($response->streamedContent())->toBe('legacy private file');
});

it('streams authenticated Cloudinary media through the API proxy', function () {
    config([
        'media.disk' => 'cloudinary',
        'media.cloudinary_url' => 'cloudinary://123456:example-secret@demo',
    ]);
    Http::fake(fn () => Http::response('private cloud file', 200, ['Content-Type' => 'application/pdf']));

    $url = app(MediaStorageService::class)->privateUrl('cloudinary:private:reports/42/evidence.pdf');
    $response = $this->get($url)->assertOk();

    expect($response->streamedContent())->toBe('private cloud file');

    Http::assertSent(fn (ClientRequest $request): bool => str_contains(
        $request->url(),
        'res.cloudinary.com/demo/raw/authenticated/s--',
    ) && str_contains($request->url(), '/reports/42/evidence.pdf'));
});

it('generates public Cloudinary delivery URLs from stored media references', function () {
    config([
        'media.disk' => 'cloudinary',
        'media.cloudinary_url' => 'cloudinary://123456:example-secret@demo',
    ]);

    expect(app(MediaStorageService::class)->publicUrl('cloudinary:public:photos/42'))
        ->toStartWith('https://res.cloudinary.com/demo/image/upload/')
        ->toContain('photos/42');
});

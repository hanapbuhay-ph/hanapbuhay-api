<?php

use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerPortfolioPhoto;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function makeWorkerWithProfile(): User
{
    $user = User::factory()->create(['role' => 'worker', 'email_verified_at' => now()]);
    WorkerProfile::factory()->create(['user_id' => $user->id]);

    return $user;
}

it('updates bio and availability_status successfully', function () {
    $worker = makeWorkerWithProfile();

    $response = $this->actingAs($worker)->post('/api/worker/profile', [
        'bio' => 'Experienced electrician.',
        'availability_status' => 'available',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Profile updated.',
            'data' => [
                'worker_profile' => [
                    'bio' => 'Experienced electrician.',
                    'availability_status' => 'available',
                ],
            ],
        ]);
});

it('syncs category_ids and verifies pivot table is updated', function () {
    $worker = makeWorkerWithProfile();
    $cat1 = ServiceCategory::factory()->create(['name' => 'Electrical Works']);
    $cat2 = ServiceCategory::factory()->create(['name' => 'Plumbing']);

    $response = $this->actingAs($worker)->post('/api/worker/profile', [
        'category_ids' => [$cat1->id, $cat2->id],
    ]);

    $response->assertStatus(200);

    $profileId = $worker->workerProfile->id;
    $this->assertDatabaseHas('worker_service_categories', [
        'worker_profile_id' => $profileId,
        'service_category_id' => $cat1->id,
    ]);
    $this->assertDatabaseHas('worker_service_categories', [
        'worker_profile_id' => $profileId,
        'service_category_id' => $cat2->id,
    ]);

    $categories = $response->json('data.worker_profile.categories');
    expect(collect($categories)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$cat1->id, $cat2->id])->sort()->values()->all());
});

it('clears categories when an explicit empty selection is submitted', function () {
    $worker = makeWorkerWithProfile();
    $category = ServiceCategory::factory()->create();
    $worker->workerProfile->serviceCategories()->attach($category);

    $this->actingAs($worker)->post('/api/worker/profile', [
        'sync_categories' => '1',
    ])->assertOk();

    expect($worker->workerProfile->fresh()->serviceCategories)->toHaveCount(0);
});

it('stores portfolio uploads and removes photos omitted from the keep list', function () {
    $worker = makeWorkerWithProfile();

    $response = $this->actingAs($worker)->post('/api/worker/profile', [
        'sync_portfolio_photos' => '1',
        'portfolio_photos' => [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
        ],
    ]);

    $response->assertOk();
    $photos = $worker->workerProfile->portfolioPhotos()->get();
    expect($photos)->toHaveCount(2);
    foreach ($photos as $photo) {
        Storage::disk('public')->assertExists($photo->file_path);
    }

    $keptPhoto = $photos->first();
    $removedPhoto = $photos->last();
    $updateResponse = $this->actingAs($worker)->post('/api/worker/profile', [
        'sync_portfolio_photos' => '1',
        'keep_portfolio_photo_ids' => [$keptPhoto->id],
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('data.worker_profile.portfolio_photos.0.id', $keptPhoto->id);
    expect($worker->workerProfile->portfolioPhotos()->pluck('id')->all())
        ->toBe([$keptPhoto->id]);
    Storage::disk('public')->assertMissing($removedPhoto->file_path);
});

it('appends uploaded photos when existing photos are not being synchronized', function () {
    $worker = makeWorkerWithProfile();
    $this->actingAs($worker)->post('/api/worker/profile', [
        'sync_portfolio_photos' => '1',
        'portfolio_photos' => [UploadedFile::fake()->image('first.jpg')],
    ])->assertOk();

    $this->actingAs($worker)->post('/api/worker/profile', [
        'portfolio_photos' => [UploadedFile::fake()->image('second.jpg')],
    ])->assertOk();

    expect($worker->workerProfile->portfolioPhotos()->count())->toBe(2);
});

it('rejects portfolio photo IDs belonging to another worker', function () {
    $worker = makeWorkerWithProfile();
    $otherWorker = makeWorkerWithProfile();
    $photo = WorkerPortfolioPhoto::create([
        'worker_profile_id' => $otherWorker->workerProfile->id,
        'file_path' => 'worker-portfolios/other/photo.jpg',
        'display_order' => 0,
    ]);

    $this->actingAs($worker)->post('/api/worker/profile', [
        'sync_portfolio_photos' => '1',
        'keep_portfolio_photo_ids' => [$photo->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['keep_portfolio_photo_ids.0']);
});

it('rejects portfolio uploads that exceed the six-photo limit', function () {
    $worker = makeWorkerWithProfile();

    $this->actingAs($worker)->post('/api/worker/profile', [
        'sync_portfolio_photos' => '1',
        'portfolio_photos' => array_map(
            fn ($number) => UploadedFile::fake()->image("photo-{$number}.jpg"),
            range(1, 7),
        ),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['portfolio_photos']);
});

it('returns 403 for non-worker (client role)', function () {
    $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);

    $this->actingAs($client)->post('/api/worker/profile', ['bio' => 'test'])
        ->assertStatus(403)
        ->assertJson(['success' => false, 'message' => 'This action is for workers only.']);
});

it('returns 422 for invalid availability_status value', function () {
    $worker = makeWorkerWithProfile();

    $this->actingAs($worker)->post('/api/worker/profile', ['availability_status' => 'on_vacation'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['availability_status']);
});

it('returns 422 when category_ids contains a non-existent ID', function () {
    $worker = makeWorkerWithProfile();

    $this->actingAs($worker)->post('/api/worker/profile', ['category_ids' => [99999]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category_ids.0']);
});

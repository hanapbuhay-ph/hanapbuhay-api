<?php

use App\Models\HanapbuhayNotification;
use App\Models\AdminAuditLog;
use App\Models\JobPost;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows an admin to create a job post for a worker and notifies the worker', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $worker = User::factory()->create(['role' => 'worker', 'is_active' => true]);
    $profile = WorkerProfile::factory()->create(['user_id' => $worker->id]);
    $category = ServiceCategory::factory()->create();

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')
        ->once()
        ->withArgs(fn (User $target, string $title, string $body, string $type, array $data): bool =>
            $target->id === $worker->id
            && $type === 'job_post_created'
            && $data['job_post_id'] !== null
        )
        ->andReturn(new HanapbuhayNotification());

    $this->actingAs($admin)
        ->postJson('/api/admin/posts', [
            'worker_profile_id' => $profile->id,
            'service_category_id' => $category->id,
            'title' => 'Home cleaning',
            'description' => 'Full home cleaning service.',
            'rate_amount' => 850,
            'rate_type' => 'per_project',
            'is_available' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.job_post.title', 'Home cleaning')
        ->assertJsonPath('data.job_post.service_category_id', $category->id);

    expect(JobPost::where('worker_profile_id', $profile->id)->where('title', 'Home cleaning')->exists())->toBeTrue();
});

it('allows an admin to update a job post and notifies the worker', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $worker = User::factory()->create(['role' => 'worker', 'is_active' => true]);
    $profile = WorkerProfile::factory()->create(['user_id' => $worker->id]);
    $category = ServiceCategory::factory()->create();
    $post = JobPost::factory()->create([
        'worker_profile_id' => $profile->id,
        'service_category_id' => $category->id,
    ]);

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')
        ->once()
        ->withArgs(fn (User $target, string $title, string $body, string $type, array $data): bool =>
            $target->id === $worker->id
            && $type === 'job_post_updated'
            && $data['job_post_id'] === (string) $post->id
        )
        ->andReturn(new HanapbuhayNotification());

    $this->actingAs($admin)
        ->putJson("/api/admin/posts/{$post->id}", [
            'service_category_id' => $category->id,
            'title' => 'Updated service title',
            'description' => 'Updated service description.',
            'rate_amount' => 1200,
            'rate_type' => 'daily',
            'is_available' => false,
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.job_post.title', 'Updated service title')
        ->assertJsonPath('data.job_post.is_available', false)
        ->assertJsonPath('data.job_post.is_active', false);

    expect($post->fresh()->title)->toBe('Updated service title');
});

it('notifies the worker when an admin permanently deletes a job post', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $worker = User::factory()->create(['role' => 'worker', 'is_active' => true]);
    $profile = WorkerProfile::factory()->create(['user_id' => $worker->id]);
    $post = JobPost::factory()->create(['worker_profile_id' => $profile->id]);

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')
        ->once()
        ->withArgs(fn (User $target, string $title, string $body, string $type, array $data): bool =>
            $target->id === $worker->id
            && $type === 'job_post_removed'
            && $body === 'No longer offered.'
            && $data['job_post_id'] === $post->id
        )
        ->andReturn(new HanapbuhayNotification());

    $this->actingAs($admin)
        ->deleteJson("/api/admin/posts/{$post->id}", ['reason' => 'No longer offered.'])
        ->assertOk()
        ->assertJsonPath('message', 'Job post permanently deleted.');

    expect(JobPost::withTrashed()->find($post->id))->toBeNull();
});

it('bulk deletes selected job posts and notifies each worker', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $workers = collect([
        User::factory()->create(['role' => 'worker', 'is_active' => true]),
        User::factory()->create(['role' => 'worker', 'is_active' => true]),
    ]);
    $profiles = $workers->map(fn (User $worker) => WorkerProfile::factory()->create(['user_id' => $worker->id]));
    $posts = $profiles->map(fn (WorkerProfile $profile) => JobPost::factory()->create([
        'worker_profile_id' => $profile->id,
    ]));
    $workerIds = $workers->pluck('id')->all();

    $this->mock(NotificationService::class)
        ->shouldReceive('notify')
        ->twice()
        ->withArgs(fn (User $target, string $title, string $body, string $type): bool =>
            in_array($target->id, $workerIds, true)
            && $type === 'job_post_removed'
            && $body === 'Bulk removal reason.'
        )
        ->andReturn(new HanapbuhayNotification());

    $this->actingAs($admin)
        ->deleteJson('/api/admin/posts/bulk', [
            'post_ids' => $posts->pluck('id')->all(),
            'reason' => 'Bulk removal reason.',
        ])
        ->assertOk()
        ->assertJsonPath('data.deleted_ids', $posts->pluck('id')->all());

    expect(JobPost::withTrashed()->whereIn('id', $posts->pluck('id'))->count())->toBe(0);
    expect(AdminAuditLog::where('admin_id', $admin->id)
        ->where('action', 'delete_job_post')
        ->whereIn('target_id', $posts->pluck('id'))
        ->count())->toBe(2);
});

it('rejects bulk deletion requests with duplicate or missing job post IDs', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $post = JobPost::factory()->create();

    $this->actingAs($admin)
        ->deleteJson('/api/admin/posts/bulk', [
            'post_ids' => [$post->id, $post->id],
            'reason' => 'Duplicate selection.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('post_ids.1');

    $this->actingAs($admin)
        ->deleteJson('/api/admin/posts/bulk', [
            'post_ids' => [$post->id, 999999],
            'reason' => 'Unknown selection.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('post_ids.1');

    expect(JobPost::find($post->id))->not->toBeNull();
});

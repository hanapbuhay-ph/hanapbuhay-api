<?php

use App\Models\Booking;
use App\Models\JobPost;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeBookingAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'is_active' => true]);
}

function makeAdminBooking(?string $status = 'pending'): Booking
{
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();
    $jobPost = JobPost::factory()->create([
        'worker_profile_id' => $worker->workerProfile->id,
        'service_category_id' => $category->id,
        'is_active' => true,
    ]);

    return Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'job_post_id' => $jobPost->id,
        'service_category_id' => $category->id,
        'status' => $status,
        'scheduled_at' => now()->addDays(2),
    ]);
}

it('allows an admin to create a pending booking for an active client and notifies both parties', function () {
    $admin = makeBookingAdmin();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();
    $jobPost = JobPost::factory()->create([
        'worker_profile_id' => $worker->workerProfile->id,
        'service_category_id' => $category->id,
        'is_active' => true,
    ]);

    $notifications = $this->mock(NotificationService::class);
    foreach ([$client, $worker] as $recipient) {
        $notifications->shouldReceive('notify')
            ->once()
            ->withArgs(fn (User $user, string $title, string $body, string $type): bool => $user->id === $recipient->id && $type === 'booking_updated'
            );
        $notifications->shouldReceive('sendPush')
            ->once()
            ->withArgs(fn (User $user, string $title, string $body, array $data): bool => $user->id === $recipient->id && $data['type'] === 'booking_updated'
            );
    }

    $this->actingAs($admin)
        ->postJson('/api/admin/bookings', [
            'client_id' => $client->id,
            'worker_id' => $worker->id,
            'job_post_id' => $jobPost->id,
            'service_category_id' => $category->id,
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'notes' => 'Please call before arriving.',
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('bookings', [
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'job_post_id' => $jobPost->id,
        'status' => 'pending',
        'notes' => 'Please call before arriving.',
    ]);
});

it('rejects an admin booking when the selected client is not a client', function () {
    $admin = makeBookingAdmin();
    $worker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();
    $notClient = User::factory()->create(['role' => 'worker']);

    $this->actingAs($admin)
        ->postJson('/api/admin/bookings', [
            'client_id' => $notClient->id,
            'worker_id' => $worker->id,
            'job_post_id' => JobPost::factory()->create([
                'worker_profile_id' => $worker->workerProfile->id,
                'service_category_id' => $category->id,
            ])->id,
            'service_category_id' => $category->id,
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

it('rejects a job post that does not belong to the selected worker', function () {
    $admin = makeBookingAdmin();
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    $otherWorker = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();
    $jobPost = JobPost::factory()->create([
        'worker_profile_id' => $otherWorker->workerProfile->id,
        'service_category_id' => $category->id,
    ]);

    $this->actingAs($admin)
        ->postJson('/api/admin/bookings', [
            'client_id' => $client->id,
            'worker_id' => $worker->id,
            'job_post_id' => $jobPost->id,
            'service_category_id' => $category->id,
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['job_post_id']);
});

it('allows an admin to reschedule a pending booking and notifies both parties', function () {
    $admin = makeBookingAdmin();
    $booking = makeAdminBooking();

    $notifications = $this->mock(NotificationService::class);
    foreach ([$booking->client, $booking->worker] as $recipient) {
        $notifications->shouldReceive('notify')
            ->once()
            ->withArgs(fn (User $user, string $title, string $body, string $type): bool => $user->id === $recipient->id && $type === 'booking_rescheduled'
            );
        $notifications->shouldReceive('sendPush')
            ->once()
            ->withArgs(fn (User $user, string $title, string $body, array $data): bool => $user->id === $recipient->id && $data['type'] === 'booking_rescheduled'
            );
    }

    $scheduledAt = now()->addDays(5)->toIso8601String();
    $this->actingAs($admin)
        ->postJson("/api/admin/bookings/{$booking->id}/reschedule", [
            'scheduled_at' => $scheduledAt,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'pending');

    expect($booking->fresh()->scheduled_at->toIso8601String())->toBe($scheduledAt);
});

it('does not allow an admin to reschedule a non-pending booking', function () {
    $admin = makeBookingAdmin();
    $booking = makeAdminBooking('accepted');

    $this->actingAs($admin)
        ->postJson("/api/admin/bookings/{$booking->id}/reschedule", [
            'scheduled_at' => now()->addDays(5)->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

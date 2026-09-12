<?php

use App\Models\Booking;
use App\Models\Message;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAdminChatFixture(): array
{
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $client = User::factory()->create(['role' => 'client']);
    $worker = User::factory()->create(['role' => 'worker']);
    WorkerProfile::factory()->create(['user_id' => $worker->id]);
    $category = ServiceCategory::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => $category->id,
    ]);

    return [$admin, $client, $worker, $booking];
}

it('admin can list chat conversations across bookings', function () {
    [$admin, $client, $worker, $booking] = makeAdminChatFixture();
    Message::create([
        'booking_id' => $booking->id,
        'sender_id' => $client->id,
        'receiver_id' => $worker->id,
        'content' => 'Hello admin.',
        'is_read' => false,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/chat-logs')
        ->assertOk()
        ->assertJsonPath('data.conversations.0.booking_id', $booking->id)
        ->assertJsonStructure(['data' => ['conversations', 'pagination']]);
});

it('admin can inspect a thread without marking messages read', function () {
    [$admin, $client, $worker, $booking] = makeAdminChatFixture();
    $message = Message::create([
        'booking_id' => $booking->id,
        'sender_id' => $client->id,
        'receiver_id' => $worker->id,
        'content' => 'Attachment message.',
        'attachment_path' => 'messages/example.jpg',
        'is_read' => false,
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin/chat-logs/{$booking->id}")
        ->assertOk()
        ->assertJsonPath('data.messages.0.sender_role', 'client')
        ->assertJsonPath('data.messages.0.is_flagged', false)
        ->assertJsonPath('data.messages.0.attachment_url', asset('storage/messages/example.jpg'))
        ->assertJsonPath('data.pagination.total', 1);

    expect($message->fresh()->is_read)->toBeFalse();
});

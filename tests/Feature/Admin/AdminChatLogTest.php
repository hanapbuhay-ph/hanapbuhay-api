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

it('admin sees one conversation per client-worker pair across bookings', function () {
    [$admin, $client, $worker, $booking] = makeAdminChatFixture();
    Message::create([
        'booking_id' => $booking->id,
        'sender_id' => $client->id,
        'receiver_id' => $worker->id,
        'content' => 'Hello admin.',
        'is_read' => false,
    ]);
    $secondBooking = Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => ServiceCategory::factory()->create()->id,
    ]);
    Message::create([
        'booking_id' => $secondBooking->id,
        'sender_id' => $worker->id,
        'receiver_id' => $client->id,
        'content' => 'Following up.',
        'is_read' => true,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/chat-logs')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.conversations.0.conversation_id', "{$client->id}-{$worker->id}")
        ->assertJsonPath('data.conversations.0.client.id', $client->id)
        ->assertJsonPath('data.conversations.0.worker.id', $worker->id)
        ->assertJsonPath('data.conversations.0.messages_count', 2)
        ->assertJsonPath('data.conversations.0.bookings_count', 2)
        ->assertJsonStructure(['data' => ['conversations', 'pagination']]);

    $this->actingAs($admin)
        ->getJson('/api/admin/chat-logs?search='.urlencode($client->name))
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.conversations.0.client.id', $client->id);
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

it('admin can inspect all messages for a client-worker conversation without marking them read', function () {
    [$admin, $client, $worker, $booking] = makeAdminChatFixture();
    $client->update(['profile_photo_path' => 'profiles/client.jpg']);
    $worker->update(['profile_photo_path' => 'profiles/worker.jpg']);
    $first = Message::create([
        'booking_id' => $booking->id,
        'sender_id' => $client->id,
        'receiver_id' => $worker->id,
        'content' => 'First booking message.',
        'is_read' => false,
    ]);
    $secondBooking = Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'service_category_id' => ServiceCategory::factory()->create()->id,
    ]);
    $second = Message::create([
        'booking_id' => $secondBooking->id,
        'sender_id' => $worker->id,
        'receiver_id' => $client->id,
        'content' => 'Second booking message.',
        'is_read' => false,
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin/chat-logs/conversations/{$client->id}/{$worker->id}")
        ->assertOk()
        ->assertJsonPath('data.conversation.bookings_count', 2)
        ->assertJsonPath('data.conversation.client.profile_photo_url', asset('storage/profiles/client.jpg'))
        ->assertJsonPath('data.conversation.worker.profile_photo_url', asset('storage/profiles/worker.jpg'))
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonPath('data.messages.0.id', $first->id)
        ->assertJsonPath('data.messages.0.booking_code', $booking->booking_code)
        ->assertJsonPath('data.messages.0.sender_profile_photo_url', asset('storage/profiles/client.jpg'))
        ->assertJsonPath('data.messages.1.id', $second->id)
        ->assertJsonPath('data.messages.1.booking_code', $secondBooking->booking_code)
        ->assertJsonPath('data.messages.1.sender_profile_photo_url', asset('storage/profiles/worker.jpg'));

    expect($first->fresh()->is_read)->toBeFalse()
        ->and($second->fresh()->is_read)->toBeFalse();
});

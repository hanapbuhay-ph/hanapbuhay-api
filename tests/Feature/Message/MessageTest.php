<?php

use App\Models\Booking;
use App\Models\Message;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Local setup closures ──────────────────────────────────────────────────────

$makeBooking = function (?int $clientId = null, ?int $workerId = null, string $status = 'accepted'): Booking {
    $client   = $clientId ? \App\Models\User::find($clientId) : makeBookingClient();
    $worker   = $workerId ? \App\Models\User::find($workerId) : makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    return Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => $status,
    ]);
};

$makeMessage = function (Booking $booking, int $senderId, int $receiverId, string $content = 'Hello!'): Message {
    return Message::create([
        'booking_id'  => $booking->id,
        'sender_id'   => $senderId,
        'receiver_id' => $receiverId,
        'content'     => $content,
    ]);
};

// ── GET /api/bookings/{id}/messages ───────────────────────────────────────────

it('client retrieves messages for their booking → 200 with correct pagination shape', function () use ($makeBooking, $makeMessage) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $makeMessage($booking, $client->id, $worker->id, 'Hi there!');

    $this->actingAs($client)
        ->getJson("/api/bookings/{$booking->id}/messages")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Messages retrieved.')
        ->assertJsonCount(1, 'data.messages')
        ->assertJsonStructure([
            'data' => [
                'messages'   => [['id', 'sender_id', 'sender_name', 'content', 'attachment_url', 'is_read', 'created_at']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ])
        ->assertJsonPath('data.pagination.per_page', 30)
        ->assertJsonPath('data.pagination.total', 1);
});

it('worker retrieves messages for their booking → 200', function () use ($makeBooking, $makeMessage) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $makeMessage($booking, $worker->id, $client->id, 'On my way.');

    $this->actingAs($worker)
        ->getJson("/api/bookings/{$booking->id}/messages")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.messages');
});

it('messages are ordered ascending by created_at', function () use ($makeBooking, $makeMessage) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $first  = $makeMessage($booking, $client->id, $worker->id, 'First');
    $second = $makeMessage($booking, $worker->id, $client->id, 'Second');

    $response = $this->actingAs($client)
        ->getJson("/api/bookings/{$booking->id}/messages")
        ->assertStatus(200);

    expect($response->json('data.messages.0.id'))->toBe($first->id)
        ->and($response->json('data.messages.1.id'))->toBe($second->id);
});

it('unrelated user cannot retrieve messages → 403', function () use ($makeBooking) {
    $booking  = $makeBooking();
    $outsider = makeBookingClient();

    $this->actingAs($outsider)
        ->getJson("/api/bookings/{$booking->id}/messages")
        ->assertStatus(403);
});

it('unauthenticated request to GET messages → 401', function () use ($makeBooking) {
    $booking = $makeBooking();

    $this->getJson("/api/bookings/{$booking->id}/messages")
        ->assertStatus(401);
});

it('GET messages on non-existent booking → 404', function () {
    $client = makeBookingClient();

    $this->actingAs($client)
        ->getJson('/api/bookings/99999/messages')
        ->assertStatus(404);
});

// ── POST /api/bookings/{id}/messages ──────────────────────────────────────────

it('client sends a message on an active booking → 201, correct sender_id and is_read = false', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/messages", [
            'message' => 'Hello, I am on my way.',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Message sent.')
        ->assertJsonStructure([
            'data' => ['message' => ['id', 'sender_id', 'sender_name', 'content', 'attachment_url', 'is_read', 'created_at']],
        ])
        ->assertJsonPath('data.message.is_read', false)
        ->assertJsonPath('data.message.sender_id', $client->id);

    $this->assertDatabaseHas('messages', [
        'booking_id'  => $booking->id,
        'sender_id'   => $client->id,
        'receiver_id' => $worker->id,
        'content'     => 'Hello, I am on my way.',
        'is_read'     => false,
    ]);
});

it('worker sends a message → 201', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $this->actingAs($worker)
        ->postJson("/api/bookings/{$booking->id}/messages", [
            'message' => 'I will arrive in 10 minutes.',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.message.sender_id', $worker->id);

    $this->assertDatabaseHas('messages', [
        'booking_id'  => $booking->id,
        'sender_id'   => $worker->id,
        'receiver_id' => $client->id,
    ]);
});

it('sending on a cancelled booking → 422', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id, 'cancelled');

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/messages", [
            'message' => 'Hello?',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'You cannot send messages on an inactive booking.');
});

it('sending on a declined booking → 422', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id, 'declined');

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/messages", [
            'message' => 'Hello?',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'You cannot send messages on an inactive booking.');
});

it('unrelated user cannot send a message → 403', function () use ($makeBooking) {
    $booking  = $makeBooking();
    $outsider = makeBookingClient();

    $this->actingAs($outsider)
        ->postJson("/api/bookings/{$booking->id}/messages", [
            'message' => 'Hello?',
        ])
        ->assertStatus(403);
});

it('unauthenticated request to POST messages → 401', function () use ($makeBooking) {
    $booking = $makeBooking();

    $this->postJson("/api/bookings/{$booking->id}/messages", [
        'message' => 'Hello?',
    ])
        ->assertStatus(401);
});

it('missing message field → 422', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/messages", [])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['message']]);
});

it('message exceeding 2000 chars → 422', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson("/api/bookings/{$booking->id}/messages", [
            'message' => str_repeat('a', 2001),
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['message']]);
});

it('POST messages on non-existent booking → 404', function () {
    $client = makeBookingClient();

    $this->actingAs($client)
        ->postJson('/api/bookings/99999/messages', [
            'message' => 'Hello?',
        ])
        ->assertStatus(404);
});

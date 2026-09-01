<?php

use App\Models\Booking;
use App\Models\Message;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeBookingPair(string $status = 'active'): array
{
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    $booking = Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => $status,
    ]);

    return compact('client', 'worker', 'booking');
}

function seedMessage(Booking $booking, User $sender, bool $isRead = false): Message
{
    $receiverId = $sender->id === $booking->client_id
        ? $booking->worker_id
        : $booking->client_id;

    return Message::create([
        'booking_id'  => $booking->id,
        'sender_id'   => $sender->id,
        'receiver_id' => $receiverId,
        'content'     => 'Hello there!',
        'is_read'     => $isRead,
    ]);
}

// ══════════════════════════════════════════════════════════════════════════════
// GET /api/messages  — inbox
// ══════════════════════════════════════════════════════════════════════════════

it('returns 401 when unauthenticated on inbox', function () {
    $this->getJson('/api/messages')->assertStatus(401);
});

it('returns empty conversations when user has no messages', function () {
    $client = makeBookingClient();

    $this->actingAs($client)->getJson('/api/messages')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(0, 'data.conversations');
});

it('returns a conversation row for each booking with messages', function () {
    ['client' => $client, 'worker' => $worker, 'booking' => $booking] = makeBookingPair();

    seedMessage($booking, $client);

    $response = $this->actingAs($client)->getJson('/api/messages')->assertStatus(200);

    expect($response->json('data.conversations'))->toHaveCount(1);
    expect($response->json('data.conversations.0.booking_id'))->toBe($booking->id);
    expect($response->json('data.conversations.0.booking_code'))->toBe($booking->booking_code);
});

it('inbox includes correct other_party for client view', function () {
    ['client' => $client, 'worker' => $worker, 'booking' => $booking] = makeBookingPair();

    seedMessage($booking, $client);

    $response = $this->actingAs($client)->getJson('/api/messages')->assertStatus(200);

    expect($response->json('data.conversations.0.other_party.id'))->toBe($worker->id);
});

it('inbox includes correct other_party for worker view', function () {
    ['client' => $client, 'worker' => $worker, 'booking' => $booking] = makeBookingPair();

    seedMessage($booking, $client);

    $response = $this->actingAs($worker)->getJson('/api/messages')->assertStatus(200);

    expect($response->json('data.conversations.0.other_party.id'))->toBe($client->id);
});

it('inbox unread_count reflects messages not yet read by viewer', function () {
    ['client' => $client, 'worker' => $worker, 'booking' => $booking] = makeBookingPair();

    // Worker sends 2 unread messages to client
    seedMessage($booking, $worker, false);
    seedMessage($booking, $worker, false);

    $response = $this->actingAs($client)->getJson('/api/messages')->assertStatus(200);

    expect($response->json('data.conversations.0.unread_count'))->toBe(2);
});

it('inbox does not show bookings with no messages', function () {
    ['client' => $client] = makeBookingPair(); // no messages seeded

    $response = $this->actingAs($client)->getJson('/api/messages')->assertStatus(200);

    expect($response->json('data.conversations'))->toBeEmpty();
});

it('inbox orders conversations by most recent message first', function () {
    $client   = makeBookingClient();
    $category = ServiceCategory::factory()->create();

    $worker1 = makeApprovedBookingWorker();
    $worker2 = makeApprovedBookingWorker();

    $booking1 = Booking::factory()->create(['client_id' => $client->id, 'worker_id' => $worker1->id, 'service_category_id' => $category->id]);
    $booking2 = Booking::factory()->create(['client_id' => $client->id, 'worker_id' => $worker2->id, 'service_category_id' => $category->id]);

    // booking1 has older message, booking2 has newer
    $oldMsg = new Message();
    $oldMsg->forceFill(['booking_id' => $booking1->id, 'sender_id' => $client->id, 'receiver_id' => $worker1->id, 'content' => 'Old', 'is_read' => false]);
    $oldMsg->created_at = now()->subHour();
    $oldMsg->updated_at = now()->subHour();
    $oldMsg->save();

    $newMsg = new Message();
    $newMsg->forceFill(['booking_id' => $booking2->id, 'sender_id' => $client->id, 'receiver_id' => $worker2->id, 'content' => 'New', 'is_read' => false]);
    $newMsg->created_at = now();
    $newMsg->updated_at = now();
    $newMsg->save();

    $response = $this->actingAs($client)->getJson('/api/messages')->assertStatus(200);

    $ids = collect($response->json('data.conversations'))->pluck('booking_id')->values()->all();
    expect($ids[0])->toBe($booking2->id);
    expect($ids[1])->toBe($booking1->id);
});

// ══════════════════════════════════════════════════════════════════════════════
// GET /api/messages/{bookingId}  — spec URL thread
// ══════════════════════════════════════════════════════════════════════════════

it('returns thread via spec URL GET /api/messages/{bookingId}', function () {
    ['client' => $client, 'worker' => $worker, 'booking' => $booking] = makeBookingPair();

    seedMessage($booking, $client);

    $this->actingAs($client)
        ->getJson("/api/messages/{$booking->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'booking'    => ['id', 'booking_code', 'status'],
                'messages'   => [['id', 'sender_id', 'sender_name', 'content', 'attachment_url', 'is_read', 'created_at']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it('fetching thread marks unread messages from other party as read', function () {
    ['client' => $client, 'worker' => $worker, 'booking' => $booking] = makeBookingPair();

    // Worker sends 2 unread messages to client
    $msg1 = seedMessage($booking, $worker, false);
    $msg2 = seedMessage($booking, $worker, false);

    // Client fetches thread
    $this->actingAs($client)->getJson("/api/messages/{$booking->id}")->assertStatus(200);

    expect($msg1->fresh()->is_read)->toBeTrue();
    expect($msg2->fresh()->is_read)->toBeTrue();
    expect($msg1->fresh()->read_at)->not->toBeNull();
});

it('fetching thread does NOT mark own sent messages as read', function () {
    ['client' => $client, 'worker' => $worker, 'booking' => $booking] = makeBookingPair();

    // Client sends a message to worker (worker hasn't read it)
    $msg = seedMessage($booking, $client, false);

    // Client fetches own thread — should not self-mark
    $this->actingAs($client)->getJson("/api/messages/{$booking->id}")->assertStatus(200);

    expect($msg->fresh()->is_read)->toBeFalse();
});

it('returns 403 on spec thread URL for unrelated user', function () {
    ['booking' => $booking] = makeBookingPair();
    $stranger = makeBookingClient();

    $this->actingAs($stranger)
        ->getJson("/api/messages/{$booking->id}")
        ->assertStatus(403);
});

it('returns 404 on spec thread URL for non-existent booking', function () {
    $client = makeBookingClient();

    $this->actingAs($client)->getJson('/api/messages/99999')->assertStatus(404);
});

// ══════════════════════════════════════════════════════════════════════════════
// POST /api/messages/{bookingId}  — spec URL send
// ══════════════════════════════════════════════════════════════════════════════

it('sends a text message via spec URL POST /api/messages/{bookingId}', function () {
    ['client' => $client, 'booking' => $booking] = makeBookingPair();

    $this->actingAs($client)
        ->postJson("/api/messages/{$booking->id}", ['message' => 'Hello worker!'])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => ['message' => ['id', 'sender_id', 'sender_name', 'content', 'attachment_url', 'is_read', 'created_at']],
        ]);
});

it('sends a message with an image attachment', function () {
    Storage::fake('public');

    ['client' => $client, 'booking' => $booking] = makeBookingPair();

    $this->actingAs($client)
        ->postJson("/api/messages/{$booking->id}", [
            'attachment' => UploadedFile::fake()->image('photo.jpg', 100, 100),
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    // attachment_url should not be null
    $this->actingAs($client)
        ->postJson("/api/messages/{$booking->id}", [
            'attachment' => UploadedFile::fake()->image('photo.png'),
        ])
        ->assertStatus(201);
});

it('attachment_url is populated in response when attachment is uploaded', function () {
    Storage::fake('public');

    ['client' => $client, 'booking' => $booking] = makeBookingPair();

    $response = $this->actingAs($client)
        ->postJson("/api/messages/{$booking->id}", [
            'attachment' => UploadedFile::fake()->image('test.jpg'),
        ])
        ->assertStatus(201);

    expect($response->json('data.message.attachment_url'))->not->toBeNull();
});

it('returns 422 when both message and attachment are missing', function () {
    ['client' => $client, 'booking' => $booking] = makeBookingPair();

    $this->actingAs($client)
        ->postJson("/api/messages/{$booking->id}", [])
        ->assertStatus(422);
});

it('returns 422 when attachment is not an image', function () {
    ['client' => $client, 'booking' => $booking] = makeBookingPair();

    $this->actingAs($client)
        ->postJson("/api/messages/{$booking->id}", [
            'attachment' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['attachment']);
});

it('returns 422 when attachment exceeds 5MB', function () {
    ['client' => $client, 'booking' => $booking] = makeBookingPair();

    $this->actingAs($client)
        ->postJson("/api/messages/{$booking->id}", [
            'attachment' => UploadedFile::fake()->image('big.jpg')->size(6000),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['attachment']);
});

it('returns 422 when sending on a cancelled booking via spec URL', function () {
    ['client' => $client, 'booking' => $booking] = makeBookingPair('cancelled');

    $this->actingAs($client)
        ->postJson("/api/messages/{$booking->id}", ['message' => 'Hello?'])
        ->assertStatus(422);
});

it('returns 401 when unauthenticated on spec message URLs', function () {
    $this->getJson('/api/messages')->assertStatus(401);
    $this->getJson('/api/messages/1')->assertStatus(401);
    $this->postJson('/api/messages/1', [])->assertStatus(401);
});

// ══════════════════════════════════════════════════════════════════════════════
// Legacy GET /api/bookings/{id}/messages — read receipts
// ══════════════════════════════════════════════════════════════════════════════

it('legacy GET /bookings/{id}/messages also marks unread messages as read', function () {
    ['client' => $client, 'worker' => $worker, 'booking' => $booking] = makeBookingPair();

    $msg = seedMessage($booking, $worker, false);

    $this->actingAs($client)->getJson("/api/bookings/{$booking->id}/messages")->assertStatus(200);

    expect($msg->fresh()->is_read)->toBeTrue();
});

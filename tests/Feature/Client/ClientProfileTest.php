<?php

use App\Models\Booking;
use App\Models\RatingReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns ratings received from workers in the client profile', function () {
    $client = makeBookingClient();
    $client->update(['profile_photo_path' => 'https://lh3.googleusercontent.com/client-photo']);
    $worker = makeApprovedBookingWorker();
    $worker->update(['profile_photo_path' => 'https://lh3.googleusercontent.com/worker-photo']);
    $secondWorker = makeApprovedBookingWorker();
    $otherClient = makeBookingClient();

    $workerBooking = Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
        'status' => 'completed',
    ]);
    $secondWorkerBooking = Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $secondWorker->id,
        'status' => 'completed',
    ]);
    $unrelatedBooking = Booking::factory()->create([
        'client_id' => $otherClient->id,
        'worker_id' => $worker->id,
        'status' => 'completed',
    ]);

    RatingReview::factory()->create([
        'booking_id' => $workerBooking->id,
        'rated_by' => $worker->id,
        'rated_user' => $client->id,
        'score' => 5,
        'comment' => 'Great communication.',
    ]);
    RatingReview::factory()->create([
        'booking_id' => $secondWorkerBooking->id,
        'rated_by' => $secondWorker->id,
        'rated_user' => $client->id,
        'score' => 4,
        'comment' => 'Reliable client.',
    ]);
    RatingReview::factory()->create([
        'booking_id' => $unrelatedBooking->id,
        'rated_by' => $otherClient->id,
        'rated_user' => $client->id,
        'score' => 1,
        'comment' => 'Must not be treated as a worker rating.',
    ]);

    expect(RatingReview::where('rated_user', $client->id)->count())->toBe(3);

    $response = $this->actingAs($worker)
        ->getJson("/api/clients/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.client.profile_photo_url', 'https://lh3.googleusercontent.com/client-photo')
        ->assertJsonPath('data.client.rating_count', 2)
        ->assertJsonPath('data.client.average_rating', 4.5)
        ->assertJsonCount(2, 'data.client.ratings');

    $ratings = collect($response->json('data.client.ratings'));
    $workerRating = $ratings->firstWhere('comment', 'Great communication.');
    $secondWorkerRating = $ratings->firstWhere('comment', 'Reliable client.');

    expect($workerRating['rated_by_worker_profile_id'])->toBe($worker->workerProfile->id)
        ->and($workerRating['rated_by_name'])->toBe($worker->name)
        ->and($workerRating['reviewer_avatar_url'])->toBe('https://lh3.googleusercontent.com/worker-photo')
        ->and($workerRating['score'])->toBe(5)
        ->and($secondWorkerRating['rated_by_worker_profile_id'])->toBe($secondWorker->workerProfile->id)
        ->and($secondWorkerRating['rated_by_name'])->toBe($secondWorker->name);
});

it('returns an empty rating list and null average when no worker has rated the client', function () {
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();
    Booking::factory()->create([
        'client_id' => $client->id,
        'worker_id' => $worker->id,
    ]);

    $this->actingAs($worker)
        ->getJson("/api/clients/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.client.rating_count', 0)
        ->assertJsonPath('data.client.average_rating', null)
        ->assertJsonCount(0, 'data.client.ratings');
});

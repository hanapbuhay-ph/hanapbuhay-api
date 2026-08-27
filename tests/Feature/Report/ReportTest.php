<?php

use App\Models\Booking;
use App\Models\Report;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ── Local setup closures ──────────────────────────────────────────────────────

$makeBooking = function (?int $clientId = null, ?int $workerId = null): Booking {
    $client   = $clientId ? \App\Models\User::find($clientId) : makeBookingClient();
    $worker   = $workerId ? \App\Models\User::find($workerId) : makeApprovedBookingWorker();
    $category = ServiceCategory::factory()->create();

    return Booking::factory()->create([
        'client_id'           => $client->id,
        'worker_id'           => $worker->id,
        'service_category_id' => $category->id,
        'status'              => 'completed',
    ]);
};

$basePayload = fn (Booking $booking, int $reportedUserId) => [
    'booking_id'       => $booking->id,
    'reported_user_id' => $reportedUserId,
    'reason'           => 'no_show',
    'description'      => 'Worker did not show up.',
];

// ── Happy path ────────────────────────────────────────────────────────────────

it('client submits a report with evidence photos → 201, evidence_paths saved', function () use ($makeBooking, $basePayload) {
    Storage::fake('public');

    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $response = $this->actingAs($client)
        ->postJson('/api/reports', array_merge($basePayload($booking, $worker->id), [
            'evidence_photos' => [
                UploadedFile::fake()->image('photo1.jpg'),
                UploadedFile::fake()->image('photo2.png'),
            ],
        ]))
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Report submitted successfully.')
        ->assertJsonStructure(['data' => ['report' => ['id', 'booking_id', 'reason', 'description', 'status']]]);

    $report = Report::find($response->json('data.report.id'));
    expect($report->evidence_paths)->toBeArray()->not->toBeEmpty();
});

it('client submits a report without evidence photos → 201, evidence_paths is null', function () use ($makeBooking, $basePayload) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $response = $this->actingAs($client)
        ->postJson('/api/reports', $basePayload($booking, $worker->id))
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $report = Report::find($response->json('data.report.id'));
    expect($report->evidence_paths)->toBeNull();
});

it('worker submits a report against the client → 201', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $this->actingAs($worker)
        ->postJson('/api/reports', [
            'booking_id'       => $booking->id,
            'reported_user_id' => $client->id,
            'reason'           => 'misconduct',
            'description'      => 'Client was rude.',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('unrelated user (not on the booking) → 403', function () use ($makeBooking, $basePayload) {
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking(null, $worker->id);
    $outsider = makeBookingClient();

    $this->actingAs($outsider)
        ->postJson('/api/reports', $basePayload($booking, $worker->id))
        ->assertStatus(403);
});

it('unauthenticated request → 401', function () use ($makeBooking, $basePayload) {
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking(null, $worker->id);

    $this->postJson('/api/reports', $basePayload($booking, $worker->id))
        ->assertStatus(401);
});

// ── reported_user validation ──────────────────────────────────────────────────

it('client tries to report themselves → 422', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson('/api/reports', [
            'booking_id'       => $booking->id,
            'reported_user_id' => $client->id,
            'reason'           => 'no_show',
            'description'      => 'Test.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'You can only report the other party on this booking.');
});

it('client reports a user who is not on the booking → 422', function () use ($makeBooking) {
    $client   = makeBookingClient();
    $worker   = makeApprovedBookingWorker();
    $booking  = $makeBooking($client->id, $worker->id);
    $stranger = makeBookingClient();

    $this->actingAs($client)
        ->postJson('/api/reports', [
            'booking_id'       => $booking->id,
            'reported_user_id' => $stranger->id,
            'reason'           => 'no_show',
            'description'      => 'Test.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'You can only report the other party on this booking.');
});

// ── Validation ────────────────────────────────────────────────────────────────

it('missing booking_id → 422', function () {
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();

    $this->actingAs($client)
        ->postJson('/api/reports', [
            'reported_user_id' => $worker->id,
            'reason'           => 'no_show',
            'description'      => 'Test.',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['booking_id']]);
});

it('non-existent booking_id → 422', function () {
    $client = makeBookingClient();
    $worker = makeApprovedBookingWorker();

    $this->actingAs($client)
        ->postJson('/api/reports', [
            'booking_id'       => 99999,
            'reported_user_id' => $worker->id,
            'reason'           => 'no_show',
            'description'      => 'Test.',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['booking_id']]);
});

it('missing reason → 422', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson('/api/reports', [
            'booking_id'       => $booking->id,
            'reported_user_id' => $worker->id,
            'description'      => 'Test.',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['reason']]);
});

it('invalid reason value → 422', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson('/api/reports', [
            'booking_id'       => $booking->id,
            'reported_user_id' => $worker->id,
            'reason'           => 'bad_vibes',
            'description'      => 'Test.',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['reason']]);
});

it('missing description → 422', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson('/api/reports', [
            'booking_id'       => $booking->id,
            'reported_user_id' => $worker->id,
            'reason'           => 'no_show',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['description']]);
});

it('description exceeding 2000 chars → 422', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson('/api/reports', [
            'booking_id'       => $booking->id,
            'reported_user_id' => $worker->id,
            'reason'           => 'no_show',
            'description'      => str_repeat('a', 2001),
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['description']]);
});

it('more than 3 evidence_photos → 422', function () use ($makeBooking) {
    Storage::fake('public');

    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson('/api/reports', [
            'booking_id'       => $booking->id,
            'reported_user_id' => $worker->id,
            'reason'           => 'no_show',
            'description'      => 'Test.',
            'evidence_photos'  => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
                UploadedFile::fake()->image('d.jpg'),
            ],
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['evidence_photos']]);
});

it('evidence_photo that is not an image (PDF) → 422', function () use ($makeBooking) {
    Storage::fake('public');

    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $this->actingAs($client)
        ->postJson('/api/reports', [
            'booking_id'       => $booking->id,
            'reported_user_id' => $worker->id,
            'reason'           => 'no_show',
            'description'      => 'Test.',
            'evidence_photos'  => [
                UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ],
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['evidence_photos.0']]);
});

// ── List endpoint ─────────────────────────────────────────────────────────────

it('auth user sees only their own reports → 200 with pagination', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    Report::create([
        'booking_id'    => $booking->id,
        'reported_by'   => $client->id,
        'reported_user' => $worker->id,
        'reason'        => 'no_show',
        'description'   => 'Test.',
        'status'        => 'under_review',
    ]);

    $this->actingAs($client)
        ->getJson('/api/reports')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Reports retrieved.')
        ->assertJsonCount(1, 'data.reports')
        ->assertJsonStructure([
            'data' => [
                'reports' => [['id', 'booking_id', 'booking_code', 'reported_user', 'reason', 'status', 'created_at']],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

it("other user's reports are not included in the response", function () use ($makeBooking) {
    $client1 = makeBookingClient();
    $client2 = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client1->id, $worker->id);

    Report::create([
        'booking_id'    => $booking->id,
        'reported_by'   => $client1->id,
        'reported_user' => $worker->id,
        'reason'        => 'no_show',
        'description'   => 'Test.',
        'status'        => 'under_review',
    ]);

    $this->actingAs($client2)
        ->getJson('/api/reports')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data.reports');
});

// ── Show endpoint ─────────────────────────────────────────────────────────────

it('reporter can view their own report → 200 with full detail', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $report = Report::create([
        'booking_id'     => $booking->id,
        'reported_by'    => $client->id,
        'reported_user'  => $worker->id,
        'reason'         => 'misconduct',
        'description'    => 'Charged extra.',
        'evidence_paths' => ['reports/1/photo.jpg'],
        'status'         => 'under_review',
    ]);

    $this->actingAs($client)
        ->getJson("/api/reports/{$report->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => ['report' => [
                'id', 'booking_id', 'booking_code', 'reported_user',
                'reason', 'description', 'evidence_paths',
                'status', 'admin_remarks', 'created_at',
            ]],
        ])
        ->assertJsonPath('data.report.evidence_paths', ['reports/1/photo.jpg'])
        ->assertJsonPath('data.report.admin_remarks', null);
});

it('non-reporter (even if on the booking) cannot view the report → 403', function () use ($makeBooking) {
    $client  = makeBookingClient();
    $worker  = makeApprovedBookingWorker();
    $booking = $makeBooking($client->id, $worker->id);

    $report = Report::create([
        'booking_id'    => $booking->id,
        'reported_by'   => $client->id,
        'reported_user' => $worker->id,
        'reason'        => 'no_show',
        'description'   => 'Test.',
        'status'        => 'under_review',
    ]);

    $this->actingAs($worker)
        ->getJson("/api/reports/{$report->id}")
        ->assertStatus(403);
});

it('non-existent report → 404', function () {
    $client = makeBookingClient();

    $this->actingAs($client)
        ->getJson('/api/reports/99999')
        ->assertStatus(404);
});

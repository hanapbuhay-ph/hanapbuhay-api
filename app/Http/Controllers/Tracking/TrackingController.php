<?php

namespace App\Http\Controllers\Tracking;

use App\Events\LocationTrackingStarted;
use App\Events\LocationTrackingStopped;
use App\Events\LocationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tracking\StartTrackingRequest;
use App\Http\Requests\Tracking\StopTrackingRequest;
use App\Http\Requests\Tracking\UpdateTrackingRequest;
use App\Models\Booking;
use App\Models\BookingTracking;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function start(StartTrackingRequest $request, int $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('tracking', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if ($booking->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Tracking is only available for active bookings.',
            ], 422);
        }

        $role = $request->user()->id === $booking->client_id ? 'client' : 'worker';

        $role === 'client'
            ? $booking->update(['is_client_tracking' => true])
            : $booking->update(['is_worker_tracking' => true]);

        $recipient = $role === 'client' ? $booking->worker : $booking->client;
        $title = $role === 'client' ? 'Client Is On The Way' : 'Worker Is On The Way';
        $body = $role === 'client'
            ? "The client is on the way for booking {$booking->booking_code}."
            : "The worker is on the way for booking {$booking->booking_code}.";
        $this->notifications->notify($recipient, $title, $body, 'tracking_started', ['booking_id' => $booking->id]);

        LocationTrackingStarted::dispatch($booking->id, $role);

        return response()->json([
            'success' => true,
            'message' => 'Tracking started.',
            'data' => [
                'booking_id' => $booking->id,
                'role' => $role,
                'is_client_tracking' => $booking->fresh()->is_client_tracking,
                'is_worker_tracking' => $booking->fresh()->is_worker_tracking,
            ],
        ]);
    }

    public function update(UpdateTrackingRequest $request, int $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('tracking', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if ($booking->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Tracking is only available for active bookings.',
            ], 422);
        }

        $role = $request->user()->id === $booking->client_id ? 'client' : 'worker';

        $isTracking = $role === 'client'
            ? $booking->is_client_tracking
            : $booking->is_worker_tracking;

        if (! $isTracking) {
            return response()->json([
                'success' => false,
                'message' => 'You must start tracking before sending updates.',
            ], 422);
        }

        $validated = $request->validated();
        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $recordedAt = now();

        LocationUpdated::dispatch($booking->id, $role, $latitude, $longitude, $recordedAt->toISOString());

        BookingTracking::create([
            'booking_id' => $booking->id,
            'tracked_role' => $role,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $validated['accuracy'] ?? null,
            'recorded_at' => $recordedAt,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Location updated.',
            'data' => [
                'booking_id' => $booking->id,
                'role' => $role,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $validated['accuracy'] ?? null,
            ],
        ]);
    }

    /**
     * POST /api/bookings/{bookingId}/tracking/location  (spec URL alias)
     * REST fallback for location updates — delegates to update().
     */
    public function location(UpdateTrackingRequest $request, int $id): JsonResponse
    {
        return $this->update($request, $id);
    }

    public function stop(StopTrackingRequest $request, int $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('tracking', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $role = $request->user()->id === $booking->client_id ? 'client' : 'worker';

        $role === 'client'
            ? $booking->update(['is_client_tracking' => false])
            : $booking->update(['is_worker_tracking' => false]);

        $recipient = $role === 'client' ? $booking->worker : $booking->client;
        $title = $role === 'client' ? 'Client Has Arrived' : 'Worker Has Arrived';
        $body = $role === 'client'
            ? "The client has arrived for booking {$booking->booking_code}."
            : "The worker has arrived for booking {$booking->booking_code}.";
        $this->notifications->notify($recipient, $title, $body, 'tracking_stopped', ['booking_id' => $booking->id]);

        LocationTrackingStopped::dispatch($booking->id, $role);

        return response()->json([
            'success' => true,
            'message' => 'Tracking stopped.',
            'data' => [
                'booking_id' => $booking->id,
                'role' => $role,
                'is_client_tracking' => $booking->fresh()->is_client_tracking,
                'is_worker_tracking' => $booking->fresh()->is_worker_tracking,
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $booking = Booking::with([
            'client.barangay',
            'worker.workerProfile',
            'worker.barangay',
        ])->find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('tracking', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $clientBarangay = $booking->client?->barangay;
        $workerBarangay = $booking->worker?->barangay;
        $latestLocation = fn (string $role): ?array => $this->latestLocation($booking->id, $role);

        return response()->json([
            'success' => true,
            'message' => 'Tracking state retrieved.',
            'data' => [
                'is_client_tracking' => $booking->is_client_tracking,
                'is_worker_tracking' => $booking->is_worker_tracking,
                'client_barangay' => $clientBarangay ? [
                    'name' => $clientBarangay->name,
                    'latitude' => (float) $clientBarangay->latitude,
                    'longitude' => (float) $clientBarangay->longitude,
                ] : null,
                'worker_barangay' => $workerBarangay ? [
                    'name' => $workerBarangay->name,
                    'latitude' => (float) $workerBarangay->latitude,
                    'longitude' => (float) $workerBarangay->longitude,
                ] : null,
                'last_client_location' => $booking->is_client_tracking ? $latestLocation('client') : null,
                'last_worker_location' => $booking->is_worker_tracking ? $latestLocation('worker') : null,
            ],
        ]);
    }

    private function latestLocation(int $bookingId, string $role): ?array
    {
        $location = BookingTracking::query()
            ->where('booking_id', $bookingId)
            ->where('tracked_role', $role)
            ->latest('recorded_at')
            ->first(['latitude', 'longitude', 'accuracy', 'recorded_at']);

        if ($location === null) {
            return null;
        }

        return [
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'accuracy' => $location->accuracy === null ? null : (float) $location->accuracy,
            'recorded_at' => $location->recorded_at->toISOString(),
        ];
    }
}

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
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

        LocationTrackingStarted::dispatch($booking->id, $role);

        return response()->json([
            'success' => true,
            'message' => 'Tracking started.',
            'data'    => [
                'booking_id'         => $booking->id,
                'role'               => $role,
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

        $latitude    = (float) $request->input('latitude');
        $longitude   = (float) $request->input('longitude');
        $recordedAt  = now();

        LocationUpdated::dispatch($booking->id, $role, $latitude, $longitude, $recordedAt->toISOString());

        BookingTracking::create([
            'booking_id'  => $booking->id,
            'tracked_role' => $role,
            'latitude'    => $latitude,
            'longitude'   => $longitude,
            'recorded_at' => $recordedAt,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Location updated.',
            'data'    => [
                'booking_id' => $booking->id,
                'role'       => $role,
                'latitude'   => $latitude,
                'longitude'  => $longitude,
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

        LocationTrackingStopped::dispatch($booking->id, $role);

        return response()->json([
            'success' => true,
            'message' => 'Tracking stopped.',
            'data'    => [
                'booking_id'         => $booking->id,
                'role'               => $role,
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

        return response()->json([
            'success' => true,
            'message' => 'Tracking state retrieved.',
            'data'    => [
                'is_client_tracking' => $booking->is_client_tracking,
                'is_worker_tracking' => $booking->is_worker_tracking,
                'client_barangay'    => $clientBarangay ? [
                    'name'      => $clientBarangay->name,
                    'latitude'  => (float) $clientBarangay->latitude,
                    'longitude' => (float) $clientBarangay->longitude,
                ] : null,
                'worker_barangay'    => $workerBarangay ? [
                    'name'      => $workerBarangay->name,
                    'latitude'  => (float) $workerBarangay->latitude,
                    'longitude' => (float) $workerBarangay->longitude,
                ] : null,
            ],
        ]);
    }
}

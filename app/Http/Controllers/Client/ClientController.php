<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * GET /api/clients/{clientId}
     * A worker may view clients who have a booking with that worker.
     */
    public function show(Request $request, int|string $clientId): JsonResponse
    {
        abort_unless($request->user()->role === 'worker', 403, 'Only workers can view client profiles.');

        $hasBooking = Booking::query()
            ->where('worker_id', $request->user()->id)
            ->where('client_id', $clientId)
            ->exists();

        abort_unless($hasBooking, 403, 'You can only view clients from your bookings.');

        $client = User::query()
            ->with('barangay:id,name,latitude,longitude')
            ->where('id', $clientId)
            ->where('role', 'client')
            ->firstOrFail();

        $bookings = Booking::query()
            ->with('serviceCategory:id,name')
            ->where('worker_id', $request->user()->id)
            ->where('client_id', $client->id)
            ->latest('scheduled_at')
            ->get(['id', 'booking_code', 'service_category_id', 'scheduled_at', 'status', 'notes', 'created_at']);

        return response()->json([
            'success' => true,
            'message' => 'Client profile retrieved.',
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'mobile_number' => $client->mobile_number,
                    'profile_photo_path' => $client->profile_photo_path,
                    'profile_photo_url' => $client->profile_photo_path
                        ? asset('storage/' . $client->profile_photo_path)
                        : null,
                    'barangay' => $client->barangay,
                    'joined_at' => $client->created_at?->toIso8601String(),
                    'booking_count' => $bookings->count(),
                    'completed_booking_count' => $bookings->where('status', 'completed')->count(),
                    'bookings' => $bookings->map(fn (Booking $booking) => [
                        'id' => $booking->id,
                        'booking_code' => $booking->booking_code,
                        'service' => $booking->serviceCategory?->name,
                        'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
                        'status' => $booking->status,
                        'notes' => $booking->notes,
                        'created_at' => $booking->created_at?->toIso8601String(),
                    ])->values(),
                ],
            ],
        ]);
    }
}
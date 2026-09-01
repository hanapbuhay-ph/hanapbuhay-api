<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class BookingService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function create(array $data, int $clientId): Booking
    {
        $booking = Booking::create([
            'client_id'           => $clientId,
            'worker_id'           => $data['worker_id'],
            'service_category_id' => $data['service_category_id'],
            'scheduled_at'        => $data['scheduled_at'],
            'notes'               => $data['notes'] ?? null,
        ]);

        return $booking->load(['worker.barangay', 'serviceCategory']);
    }

    public function list(User $user, ?string $status): LengthAwarePaginator
    {
        $query = Booking::with(['client.barangay', 'worker.barangay', 'serviceCategory'])
            ->orderByDesc('created_at');

        if ($user->role === 'client') {
            $query->where('client_id', $user->id);
        } elseif ($user->role === 'worker') {
            $query->where('worker_id', $user->id);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate(15);
    }

    public function find(int $id): ?Booking
    {
        return Booking::with(['client.barangay', 'worker.barangay', 'serviceCategory'])->find($id);
    }

    public function accept(Booking $booking): JsonResponse|Booking
    {
        if ($booking->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Booking cannot be accepted.'], 422);
        }

        $booking->update(['status' => 'accepted']);

        $fresh = $booking->fresh(['client.barangay', 'worker.barangay', 'serviceCategory']);

        $this->notifications->notify(
            $fresh->client,
            'Booking Accepted',
            "Your booking {$fresh->booking_code} has been accepted.",
            'booking_accepted',
            ['booking_id' => $fresh->id],
        );

        $this->notifications->sendPush(
            $fresh->client,
            'Booking Accepted',
            "Your booking {$fresh->booking_code} has been accepted.",
            ['booking_id' => (string) $fresh->id, 'type' => 'booking_accepted'],
        );

        return $fresh;
    }

    public function decline(Booking $booking): JsonResponse|Booking
    {
        if ($booking->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Booking cannot be declined.'], 422);
        }

        $booking->update(['status' => 'declined']);

        $fresh = $booking->fresh(['client.barangay', 'worker.barangay', 'serviceCategory']);

        $this->notifications->notify(
            $fresh->client,
            'Booking Declined',
            "Your booking {$fresh->booking_code} has been declined.",
            'booking_declined',
            ['booking_id' => $fresh->id],
        );

        $this->notifications->sendPush(
            $fresh->client,
            'Booking Declined',
            "Your booking {$fresh->booking_code} has been declined.",
            ['booking_id' => (string) $fresh->id, 'type' => 'booking_declined'],
        );

        return $fresh;
    }

    public function cancel(Booking $booking, User $user, ?string $reason): JsonResponse|Booking
    {
        if (! in_array($booking->status, ['pending', 'accepted'])) {
            return response()->json(['success' => false, 'message' => 'Booking cannot be cancelled.'], 422);
        }

        $booking->update([
            'status'              => 'cancelled',
            'cancelled_by'        => $user->role,
            'cancellation_reason' => $reason,
        ]);

        return $booking->fresh(['client.barangay', 'worker.barangay', 'serviceCategory']);
    }

    public function start(Booking $booking): JsonResponse|Booking
    {
        if ($booking->status !== 'accepted') {
            return response()->json(['success' => false, 'message' => 'Booking cannot be started.'], 422);
        }

        $booking->update(['status' => 'active', 'started_at' => now()]);

        return $booking->fresh(['client.barangay', 'worker.barangay', 'serviceCategory']);
    }

    public function complete(Booking $booking): JsonResponse|Booking
    {
        if ($booking->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Booking cannot be completed.'], 422);
        }

        $booking->update([
            'status'             => 'completed',
            'completed_at'       => now(),
            'is_client_tracking' => false,
            'is_worker_tracking' => false,
        ]);

        $fresh = $booking->fresh(['client.barangay', 'worker.barangay', 'serviceCategory']);

        $this->notifications->notify(
            $fresh->client,
            'Booking Completed',
            "Your booking {$fresh->booking_code} has been completed.",
            'booking_completed',
            ['booking_id' => $fresh->id],
        );

        $this->notifications->sendPush(
            $fresh->client,
            'Booking Completed',
            "Your booking {$fresh->booking_code} has been completed.",
            ['booking_id' => (string) $fresh->id, 'type' => 'booking_completed'],
        );

        return $fresh;
    }
}

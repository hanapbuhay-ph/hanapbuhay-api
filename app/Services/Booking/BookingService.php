<?php

namespace App\Services\Booking;

use App\Exceptions\BookingScheduleConflictException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function create(array $data, int $clientId): Booking
    {
        $booking = DB::transaction(function () use ($data, $clientId): Booking {
            User::query()->whereKey($data['worker_id'])->lockForUpdate()->firstOrFail();

            $scheduledAt = Carbon::parse($data['scheduled_at']);
            $this->ensureScheduleIsAvailable($data['worker_id'], $scheduledAt);

            return Booking::create([
                'client_id' => $clientId,
                'worker_id' => $data['worker_id'],
                'service_category_id' => $data['service_category_id'],
                'job_post_id' => $data['job_post_id'] ?? null,
                'scheduled_at' => $scheduledAt,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
            ]);
        });

        $booking->load(['worker.barangay', 'worker.workerProfile', 'serviceCategory']);

        $title = 'New Booking Request';
        $body = "You have received booking request {$booking->booking_code}.";
        $notificationData = ['booking_id' => (string) $booking->id];

        $this->notifications->notify(
            $booking->worker,
            $title,
            $body,
            'booking_created',
            ['booking_id' => $booking->id],
        );

        return $booking;
    }

    public function createForAdmin(array $data, User $client): Booking
    {
        $booking = $this->create($data, $client->id)
            ->load(['client', 'worker']);

        $this->notifyBookingParticipants(
            $booking,
            'Booking Created',
            "Booking {$booking->booking_code} has been scheduled by the HanapBuhay admin and is awaiting the worker's response.",
            'booking_updated',
        );

        return $booking;
    }

    public function list(User $user, ?string $status): LengthAwarePaginator
    {
        $query = Booking::with([
            'client.barangay',
            'worker.barangay',
            'worker.workerProfile:id,user_id',
            'serviceCategory',
        ])
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
        return Booking::with([
            'client.barangay',
            'worker.barangay',
            'worker.workerProfile:id,user_id',
            'serviceCategory',
        ])->find($id);
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

        return $fresh;
    }

    public function cancel(Booking $booking, User $user, ?string $reason): JsonResponse|Booking
    {
        if (! in_array($booking->status, ['pending', 'accepted'])) {
            return response()->json(['success' => false, 'message' => 'Booking cannot be cancelled.'], 422);
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_by' => $user->role,
            'cancellation_reason' => $reason,
        ]);

        $fresh = $booking->fresh(['client.barangay', 'worker.barangay', 'serviceCategory']);

        $this->notifications->notify(
            $fresh->worker,
            'Booking Cancelled',
            "Booking {$fresh->booking_code} has been cancelled by the client.",
            'booking_cancelled',
            ['booking_id' => $fresh->id],
        );

        return $fresh;
    }

    public function start(Booking $booking): JsonResponse|Booking
    {
        if ($booking->status !== 'accepted') {
            return response()->json(['success' => false, 'message' => 'Booking cannot be started.'], 422);
        }

        $booking->update(['status' => 'active', 'started_at' => now()]);

        $fresh = $booking->fresh(['client.barangay', 'worker.barangay', 'serviceCategory']);
        $this->notifications->notify(
            $fresh->client,
            'Job Started',
            "The worker has started booking {$fresh->booking_code}.",
            'booking_started',
            ['booking_id' => $fresh->id],
        );

        return $fresh;
    }

    public function complete(Booking $booking): JsonResponse|Booking
    {
        if ($booking->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Booking cannot be completed.'], 422);
        }

        $booking->update([
            'status' => 'completed',
            'completed_at' => now(),
            'is_client_tracking' => false,
            'is_worker_tracking' => false,
        ]);

        $workerProfile = $booking->worker?->workerProfile;
        if ($workerProfile) {
            $workerProfile->increment('completed_jobs');
        }

        $fresh = $booking->fresh(['client.barangay', 'worker.barangay', 'serviceCategory']);

        $this->notifications->notify(
            $fresh->client,
            'Booking Completed',
            "Your booking {$fresh->booking_code} has been completed.",
            'booking_completed',
            ['booking_id' => $fresh->id],
        );

        return $fresh;
    }

    public function reschedule(Booking $booking, \DateTimeInterface $scheduledAt): JsonResponse|Booking
    {
        $fresh = DB::transaction(function () use ($booking, $scheduledAt): JsonResponse|Booking {
            User::query()->whereKey($booking->worker_id)->lockForUpdate()->firstOrFail();
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! in_array($lockedBooking->status, ['pending', 'accepted'])) {
                return response()->json(['success' => false, 'message' => 'Booking cannot be rescheduled.'], 422);
            }

            $this->ensureScheduleIsAvailable($lockedBooking->worker_id, $scheduledAt, $lockedBooking->id);
            $lockedBooking->update(['scheduled_at' => $scheduledAt]);

            return $lockedBooking->fresh(['client.barangay', 'worker.barangay', 'serviceCategory']);
        });

        if ($fresh instanceof JsonResponse) {
            return $fresh;
        }

        $this->notifications->notify(
            $fresh->worker,
            'Booking Rescheduled',
            "Booking {$fresh->booking_code} has been rescheduled.",
            'booking_rescheduled',
            ['booking_id' => $fresh->id],
        );

        return $fresh;
    }

    public function reschedulePendingByAdmin(Booking $booking, \DateTimeInterface $scheduledAt): JsonResponse|Booking
    {
        $fresh = DB::transaction(function () use ($booking, $scheduledAt): JsonResponse|Booking {
            User::query()->whereKey($booking->worker_id)->lockForUpdate()->firstOrFail();
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($lockedBooking->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending bookings can be rescheduled by an admin.',
                ], 422);
            }

            $this->ensureScheduleIsAvailable($lockedBooking->worker_id, $scheduledAt, $lockedBooking->id);
            $lockedBooking->update(['scheduled_at' => $scheduledAt]);

            return $lockedBooking->fresh(['client.barangay', 'worker.barangay', 'serviceCategory']);
        });

        if ($fresh instanceof JsonResponse) {
            return $fresh;
        }

        $this->notifyBookingParticipants(
            $fresh,
            'Booking Rescheduled',
            "Booking {$fresh->booking_code} has been rescheduled. Open the booking to review the updated schedule.",
            'booking_rescheduled',
        );

        return $fresh;
    }

    private function ensureScheduleIsAvailable(
        int $workerId,
        \DateTimeInterface $scheduledAt,
        ?int $exceptBookingId = null,
    ): void {
        $query = Booking::query()
            ->where('worker_id', $workerId)
            ->where('scheduled_at', $scheduledAt->format('Y-m-d H:i:s'))
            ->whereIn('status', ['pending', 'accepted', 'active']);

        if ($exceptBookingId !== null) {
            $query->whereKeyNot($exceptBookingId);
        }

        if ($query->exists()) {
            throw new BookingScheduleConflictException;
        }
    }

    private function notifyBookingParticipants(Booking $booking, string $title, string $body, string $type): void
    {
        foreach ([$booking->client, $booking->worker] as $user) {
            $data = ['booking_id' => $booking->id];
            $this->notifications->notify($user, $title, $body, $type, $data);
        }
    }
}

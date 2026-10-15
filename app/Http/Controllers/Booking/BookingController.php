<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\CreateBookingRequest;
use App\Http\Requests\Booking\RateBookingRequest;
use App\Http\Requests\Booking\RescheduleBookingRequest;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Booking\RatingService;
use App\Services\Media\MediaStorageService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $service,
        private readonly RatingService $ratingService,
    ) {}

    public function store(CreateBookingRequest $request): JsonResponse
    {
        $worker = User::find($request->worker_id);

        if (! $worker || $worker->role !== 'worker') {
            return response()->json(['success' => false, 'message' => 'The selected worker does not exist.'], 422);
        }

        $profile = $worker->workerProfile;

        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Worker profile not found.'], 422);
        }

        // Note: per spec, unverified workers CAN be booked after client acknowledges a warning modal.
        // Verification enforcement is the client app's responsibility — the backend allows it.

        $booking = $this->service->create($request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Booking created.',
            'data' => ['booking' => $this->formatBooking($booking)],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->user(), $request->query('status'));

        return response()->json([
            'success' => true,
            'data' => [
                'bookings' => collect($paginator->items())
                    ->map(fn (Booking $booking): Booking => $this->withWorkerProfilePhotoUrl($booking))
                    ->all(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $booking = $this->service->find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('view', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => ['booking' => $this->withWorkerProfilePhotoUrl($booking)],
        ]);
    }

    /**
     * POST /api/bookings/{id}/respond
     * Spec-defined unified accept/decline endpoint (worker only).
     * Delegates to the same BookingService methods as the individual routes.
     */
    public function respond(Request $request, int|string $id): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', 'in:accept,decline'],
            'reason' => ['required_if:action,decline', 'nullable', 'string', 'max:500'],
        ]);

        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->id !== $booking->worker_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if ($request->input('action') === 'accept') {
            $result = $this->service->accept($booking);
            $msg = 'Booking accepted.';
        } else {
            // Pass the decline reason through cancellation_reason for tracking
            $result = $this->service->decline($booking);
            $msg = 'Booking declined.';
        }

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data' => ['booking' => ['id' => $result->id, 'status' => $result->status]],
        ]);
    }

    public function accept(Request $request, int|string $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->id !== $booking->worker_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $result = $this->service->accept($booking);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(['success' => true, 'message' => 'Booking accepted.', 'data' => ['booking' => $result]]);
    }

    public function decline(Request $request, int|string $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->id !== $booking->worker_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $result = $this->service->decline($booking);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(['success' => true, 'message' => 'Booking declined.', 'data' => ['booking' => $result]]);
    }

    public function cancel(CancelBookingRequest $request, int|string $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('cancel', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $result = $this->service->cancel($booking, $request->user(), $request->reason);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(['success' => true, 'message' => 'Booking cancelled.', 'data' => ['booking' => $result]]);
    }

    /**
     * POST /api/bookings/{id}/status  (spec §F6)
     * Unified status-update endpoint.
     *   status=active    → worker starts the job
     *   status=completed → worker marks the job done
     */
    public function updateStatus(Request $request, int|string $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:active,completed'],
        ]);

        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->id !== $booking->worker_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if ($request->input('status') === 'active') {
            $result = $this->service->start($booking);
            $msg = 'Booking marked as active.';
        } else {
            $result = $this->service->complete($booking);
            $msg = 'Booking marked as completed.';
        }

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data' => [
                'booking' => [
                    'id' => $result->id,
                    'status' => $result->status,
                    'started_at' => $result->started_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    public function start(Request $request, int|string $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->id !== $booking->worker_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $result = $this->service->start($booking);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(['success' => true, 'message' => 'Booking started.', 'data' => ['booking' => $result]]);
    }

    public function complete(Request $request, int|string $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->id !== $booking->worker_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $result = $this->service->complete($booking);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(['success' => true, 'message' => 'Booking completed.', 'data' => ['booking' => $result]]);
    }

    public function reschedule(RescheduleBookingRequest $request, int|string $id): JsonResponse
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->id !== $booking->client_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $scheduledAt = Carbon::parse($request->scheduled_at);

        $result = $this->service->reschedule($booking, $scheduledAt);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(['success' => true, 'message' => 'Booking rescheduled.', 'data' => ['booking' => $this->formatBooking($result)]]);
    }

    public function rate(RateBookingRequest $request, int|string $id): JsonResponse
    {
        $booking = Booking::with('worker.workerProfile')->find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('rate', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if ($booking->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'You can only rate a completed booking.',
            ], 422);
        }

        try {
            $rating = $this->ratingService->rate(
                $booking,
                $request->user(),
                $request->integer('score'),
                $request->input('comment'),
            );
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'success' => false,
                'message' => 'You have already rated this booking.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Review submitted.',
            'data' => [
                'rating' => [
                    'id' => $rating->id,
                    'score' => $rating->score,
                    'comment' => $rating->comment,
                ],
            ],
        ], 201);
    }

    private function formatBooking(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'status' => $booking->status,
            'worker' => [
                'id' => $booking->worker->id,
                'worker_profile_id' => $booking->worker->workerProfile?->id,
                'name' => $booking->worker->name,
                'profile_photo_url' => app(MediaStorageService::class)
                    ->publicUrl($booking->worker->profile_photo_path),
                'barangay' => $booking->worker->barangay?->name,
            ],
            'client' => [
                'id' => $booking->client->id,
                'name' => $booking->client->name,
                'profile_photo_url' => $booking->client->profile_photo_path,
                'barangay' => $booking->client->barangay?->name,
            ],
            'service_category' => [
                'id' => $booking->serviceCategory?->id,
                'name' => $booking->serviceCategory?->name,
            ],
            'scheduled_at' => $booking->scheduled_at,
            'started_at' => $booking->started_at,
            'completed_at' => $booking->completed_at,
            'cancellation_reason' => $booking->cancellation_reason,
            'is_client_tracking' => $booking->is_client_tracking,
            'is_worker_tracking' => $booking->is_worker_tracking,
            'notes' => $booking->notes,
            'created_at' => $booking->created_at,
        ];
    }

    private function withWorkerProfilePhotoUrl(Booking $booking): Booking
    {
        $worker = $booking->worker;
        if ($worker !== null) {
            $worker->setAttribute(
                'profile_photo_url',
                app(MediaStorageService::class)->publicUrl($worker->profile_photo_path),
            );
        }

        return $booking;
    }
}

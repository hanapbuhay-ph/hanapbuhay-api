<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\CreateBookingRequest;
use App\Http\Requests\Booking\RateBookingRequest;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Booking\RatingService;
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

        if (! $profile || $profile->verification_status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'This worker is not yet verified.'], 422);
        }

        $booking = $this->service->create($request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Booking created.',
            'data'    => ['booking' => $this->formatBooking($booking)],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->user(), $request->query('status'));

        return response()->json([
            'success' => true,
            'data'    => [
                'bookings'   => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $booking = $this->service->find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        if ($request->user()->cannot('view', $booking)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return response()->json(['success' => true, 'data' => ['booking' => $booking]]);
    }

    public function accept(Request $request, int $id): JsonResponse
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

    public function decline(Request $request, int $id): JsonResponse
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

    public function cancel(CancelBookingRequest $request, int $id): JsonResponse
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

    public function start(Request $request, int $id): JsonResponse
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

    public function complete(Request $request, int $id): JsonResponse
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

    public function rate(RateBookingRequest $request, int $id): JsonResponse
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
            'data'    => [
                'rating' => [
                    'id'      => $rating->id,
                    'score'   => $rating->score,
                    'comment' => $rating->comment,
                ],
            ],
        ], 201);
    }

    private function formatBooking(Booking $booking): array
    {
        return [
            'id'               => $booking->id,
            'booking_code'     => $booking->booking_code,
            'status'           => $booking->status,
            'worker'           => [
                'id'       => $booking->worker->id,
                'name'     => $booking->worker->name,
                'barangay' => $booking->worker->barangay?->name,
            ],
            'service_category' => $booking->serviceCategory->name,
            'scheduled_at'     => $booking->scheduled_at,
            'notes'            => $booking->notes,
            'created_at'       => $booking->created_at,
        ];
    }
}

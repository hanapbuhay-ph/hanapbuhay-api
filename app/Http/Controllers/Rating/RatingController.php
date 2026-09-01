<?php

namespace App\Http\Controllers\Rating;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Booking\RatingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function __construct(private readonly RatingService $ratingService) {}

    /**
     * POST /api/ratings  (spec §H1)
     * Submit a rating with booking_id in the request body.
     * This is the spec-defined URL; /bookings/{id}/rate is the legacy route.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'score'      => ['required', 'integer', 'min:1', 'max:5'],
            'comment'    => ['nullable', 'string', 'max:300'],
        ]);

        $booking = Booking::with('worker.workerProfile')->find($request->integer('booking_id'));

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
            'message' => 'Rating submitted.',
            'data'    => [
                'rating' => [
                    'id'      => $rating->id,
                    'score'   => $rating->score,
                    'comment' => $rating->comment,
                ],
            ],
        ], 201);
    }
}

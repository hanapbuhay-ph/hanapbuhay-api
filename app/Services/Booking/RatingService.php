<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\RatingReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RatingService
{
    public function rate(Booking $booking, User $rater, int $score, ?string $comment): RatingReview
    {
        $ratedUserId = $rater->id === $booking->client_id
            ? $booking->worker->workerProfile->user_id
            : $booking->client_id;

        return DB::transaction(function () use ($booking, $rater, $ratedUserId, $score, $comment): RatingReview {
            $rating = RatingReview::create([
                'booking_id' => $booking->id,
                'rated_by'   => $rater->id,
                'rated_user' => $ratedUserId,
                'score'      => $score,
                'comment'    => $comment,
            ]);

            $ratedUser = User::find($ratedUserId);

            if ($ratedUser?->role === 'worker') {
                $stats = RatingReview::where('rated_user', $ratedUserId)
                    ->selectRaw('COUNT(*) as total, AVG(score) as average')
                    ->first();

                $ratedUser->workerProfile()->update([
                    'total_reviews'  => $stats->total,
                    'average_rating' => round($stats->average, 2),
                ]);
            }

            return $rating;
        });
    }
}

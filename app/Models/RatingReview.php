<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingReview extends Model
{
    use HasFactory;

    protected $table = 'ratings_reviews';

    protected $fillable = ['booking_id', 'rated_by', 'rated_user', 'score', 'comment'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function ratedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    public function ratedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_user');
    }
}

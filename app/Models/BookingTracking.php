<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTracking extends Model
{
    protected $table = 'booking_tracking';

    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'tracked_role',
        'latitude',
        'longitude',
        'accuracy',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}

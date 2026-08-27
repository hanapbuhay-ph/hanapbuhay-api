<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_code',
        'client_id',
        'worker_id',
        'service_category_id',
        'notes',
        'scheduled_at',
        'status',
        'cancelled_by',
        'cancellation_reason',
        'is_client_tracking',
        'is_worker_tracking',
        'started_at',
        'completed_at',
        'force_cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at'       => 'datetime',
            'started_at'         => 'datetime',
            'completed_at'       => 'datetime',
            'is_client_tracking' => 'boolean',
            'is_worker_tracking' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Booking $booking): void {
            $year = now()->year;
            $lastCode = static::withTrashed()
                ->whereYear('created_at', $year)
                ->orderByDesc('id')
                ->value('booking_code');

            $sequence = $lastCode
                ? (int) substr($lastCode, -5) + 1
                : 1;

            $booking->booking_code = sprintf('HB-%d-%05d', $year, $sequence);
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function trackingPoints(): HasMany
    {
        return $this->hasMany(BookingTracking::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}

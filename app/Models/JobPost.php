<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobPost extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // Soft-delete does not fire DB cascade, so delete images manually.
        static::deleting(function (self $post): void {
            $post->images()->each(fn ($img) => $img->delete());
        });
    }

    protected $fillable = [
        'worker_profile_id',
        'service_category_id',
        'title',
        'description',
        'rate_amount',
        'rate_type',
        'is_available',
        'is_active',
    ];

    protected $appends = ['rate_display'];

    protected function casts(): array
    {
        return [
            'rate_amount' => 'decimal:2',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function workerProfile(): BelongsTo
    {
        return $this->belongsTo(WorkerProfile::class);
    }

    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(JobPostImage::class)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getRateDisplayAttribute(): string
    {
        $amount = number_format((float) $this->rate_amount, 2);

        return match ($this->rate_type) {
            'hourly' => "₱{$amount}/hr",
            'daily' => "₱{$amount}/day",
            'weekly' => "₱{$amount}/wk",
            'monthly' => "₱{$amount}/mo",
            'per_session' => "From ₱{$amount}/session",
            'per_project' => "From ₱{$amount}/project",
            default => "₱{$amount}",
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'bio',
        'verification_status',
        'trust_tier',
        'availability_status',
        'average_rating',
        'total_reviews',
        'completed_jobs',
        'verification_remarks',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'average_rating' => 'decimal:2',
            'verified_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

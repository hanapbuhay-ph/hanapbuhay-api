<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkerProfile extends Model
{
    use HasFactory;
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

    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }

    public function serviceCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceCategory::class,
            'worker_service_categories',
            'worker_profile_id',
            'service_category_id'
        );
    }

    public function jobPosts(): HasMany
    {
        return $this->hasMany(JobPost::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(RatingReview::class, 'rated_user', 'user_id');
    }
}

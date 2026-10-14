<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPostImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_post_id',
        'image_path',
        'thumbnail_path',
        'display_order',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $image): void {
            if ($image->image_path) {
                app(\App\Services\Media\MediaStorageService::class)->delete($image->image_path);
            }
            if ($image->thumbnail_path) {
                app(\App\Services\Media\MediaStorageService::class)->delete($image->thumbnail_path);
            }
        });
    }

    public function jobPost(): BelongsTo
    {
        return $this->belongsTo(JobPost::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ServiceCategory extends Model
{
    protected $fillable = ['name', 'icon', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function workerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkerProfile::class,
            'worker_service_categories',
            'service_category_id',
            'worker_profile_id'
        );
    }
}

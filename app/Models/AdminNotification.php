<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminNotification extends Model
{
    protected $fillable = [
        'type',
        'title',
        'body',
        'data',
    ];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AdminNotificationRead::class);
    }
}

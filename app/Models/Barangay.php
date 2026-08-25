<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
    ];

    // Barangay is a static seeded table — expose coords
    // for Haversine distance computation in worker search.
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

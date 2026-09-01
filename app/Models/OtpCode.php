<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class OtpCode extends Model
{
    protected $fillable = [
        'email',
        'code',
        'type',
        'expires_at',
        'used_at',
        'reset_token',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
        ];
    }

    // Scope reused by both email verification and password reset flows.
    public function scopeValidFor(Builder $query, string $email, string $type): Builder
    {
        return $query->where('email', $email)
                     ->where('type', $type)
                     ->whereNull('used_at')
                     ->where('expires_at', '>', Carbon::now());
    }
}

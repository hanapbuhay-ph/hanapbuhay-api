<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccountAction extends Model
{
    protected $fillable = [
        'target_user_id',
        'target_name',
        'target_email',
        'requested_by',
        'requester_name',
        'requester_email',
        'action',
        'reason',
        'scheduled_for',
        'status',
        'completed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }
}

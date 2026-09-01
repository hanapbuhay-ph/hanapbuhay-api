<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationDocument extends Model
{
    use HasFactory;
    protected $fillable = [
        'worker_profile_id',
        'document_type',
        'file_path',
        'status',
        'remarks',
    ];

    public function workerProfile(): BelongsTo
    {
        return $this->belongsTo(WorkerProfile::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public const SUPER_ADMIN_EMAIL = 'hanapbuhay1234@gmail.com';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile_number',
        'role',
        'profile_photo_path',
        'last_seen_at',
        'barangay_id',
        'google_id',
        'is_google_account',
        'two_factor_enabled',
        'is_active',
        'deletion_requested_at',
        'email_verified_at',
        'notify_booking_updates',
        'notify_messages',
        'notify_promotions',
        'notify_push',
        'notify_email',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_google_account' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'notify_booking_updates' => 'boolean',
            'notify_messages' => 'boolean',
            'notify_promotions' => 'boolean',
            'notify_push' => 'boolean',
            'notify_email' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function workerProfile(): HasOne
    {
        return $this->hasOne(WorkerProfile::class);
    }

    public function clientBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_id');
    }

    public function workerBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'worker_id');
    }

    public function receivedRatings(): HasMany
    {
        return $this->hasMany(RatingReview::class, 'rated_user');
    }

    public function sentRatings(): HasMany
    {
        return $this->hasMany(RatingReview::class, 'rated_by');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(HanapbuhayNotification::class);
    }

    public function pendingAdminActions(): HasMany
    {
        return $this->hasMany(UserAccountAction::class, 'target_user_id')
            ->where('status', 'pending');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'admin_id');
    }
}

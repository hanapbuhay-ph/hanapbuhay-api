# HanapBuhay — Database Schema **Document Type:** Complete Database Reference **Database:** MySQL 8.x **Framework:** Laravel 13 (PHP 8.5) **Last Updated:** August 2026

---

## Migration Build Order

Migrations must be created and run in this exact order due to foreign key dependencies:

```
1.  barangays
2.  users (Laravel default + custom fields)
3.  cache (Laravel default)
4.  jobs (Laravel default)
5.  add_custom_fields_to_users
6.  otp_codes
7.  worker_profiles
8.  service_categories
9.  worker_service_categories (pivot)
10. job_posts
11. bookings
12. booking_tracking
13. ratings_reviews
14. reports
15. messages
16. hanapbuhay_notifications
17. device_tokens
18. admin_audit_logs
19. personal_access_tokens (Sanctum, auto)
20. password_reset_tokens (Laravel default)
21. sessions (Laravel default)
```

---

## Current Migration Status

```
✅ 1.  barangays                  — DONE + SEEDED
✅ 2.  users (default)            — DONE
✅ 3.  cache                      — DONE
✅ 4.  jobs                       — DONE
✅ 5.  add_custom_fields_to_users — DONE
✅ 6.  otp_codes                  — DONE
✅ 7.  worker_profiles            — DONE
⏳ 8.  service_categories         — PENDING
⏳ 9.  worker_service_categories  — PENDING
⏳ 10. job_posts                  — PENDING
⏳ 11. bookings                   — PENDING
⏳ 12. booking_tracking           — PENDING
⏳ 13. ratings_reviews            — PENDING
⏳ 14. reports                    — PENDING
⏳ 15. messages                   — PENDING
⏳ 16. hanapbuhay_notifications   — PENDING
⏳ 17. device_tokens              — PENDING
⏳ 18. admin_audit_logs           — PENDING
```

---

## TABLE 1: barangays **Status: ✅ Exists + Seeded**

```php
Schema::create('barangays', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->decimal('latitude', 10, 7);
    $table->decimal('longitude', 10, 7);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### Seeded Data (20 Barangays of Trinidad, Bohol)
```
All 20 barangays seeded with OSM center
coordinates at 7 decimal precision.
All is_active = true.
IDs 1–20.

Sample rows:
id=1   Alicia      lat: 9.9612000  lng: 124.3701000
id=9   Calanggaman lat: 9.9523000  lng: 124.3701000
id=13  Mabini      lat: 9.9534000  lng: 124.3689000
id=17  Poblacion   lat: 9.9545000  lng: 124.3656000
```

### Model
```php
// app/Models/Barangay.php
protected $fillable = [
    'name', 'latitude', 'longitude', 'is_active'
];

protected function casts(): array {
    return [
        'is_active' => 'boolean',
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
    ];
}

// Relationships:
public function users(): HasMany
public function workerProfiles(): HasManyThrough
```

---

## TABLE 2: users **Status: ✅ Exists with custom fields**

```php
// Original Laravel migration creates:
// id, name, email, email_verified_at,
// password, remember_token, timestamps

// Migration 5 (add_custom_fields_to_users)
// adds these columns:
Schema::table('users', function (Blueprint $table) {
    $table->string('mobile_number')->nullable();
    $table->enum('role', ['client', 'worker', 'admin'])
          ->default('client');
    $table->string('profile_photo_path')->nullable();
    $table->foreignId('barangay_id')
          ->nullable()
          ->constrained('barangays')
          ->nullOnDelete();
    $table->string('google_id')
          ->nullable()
          ->unique();
    $table->boolean('is_google_account')
          ->default(false);
    $table->boolean('is_active')->default(true);
    $table->softDeletes();
    // password is nullable for Google OAuth users
    // (modify original migration or add separate
    // nullable constraint migration)
});
```

### Full Column List
```
id                  bigint unsigned PK auto-increment
name                varchar(255) NOT NULL
email               varchar(255) NOT NULL UNIQUE
email_verified_at   timestamp NULL
password            varchar(255) NULL
                    (nullable for Google accounts)
mobile_number       varchar(255) NULL
role                enum('client','worker','admin')
                    DEFAULT 'client'
profile_photo_path  varchar(255) NULL
barangay_id         bigint unsigned NULL FK→barangays
google_id           varchar(255) NULL UNIQUE
is_google_account   tinyint(1) DEFAULT 0
is_active           tinyint(1) DEFAULT 1
remember_token      varchar(100) NULL
deleted_at          timestamp NULL (softDeletes)
created_at          timestamp NULL
updated_at          timestamp NULL
```

### Model
```php
// app/Models/User.php
use SoftDeletes;

protected $fillable = [
    'name', 'email', 'password',
    'mobile_number', 'role',
    'profile_photo_path', 'barangay_id',
    'google_id', 'is_google_account',
    'is_active', 'email_verified_at',
];

protected $hidden = [
    'password', 'remember_token',
    'google_id',
];

protected function casts(): array {
    return [
        'email_verified_at'  => 'datetime',
        'is_active'          => 'boolean',
        'is_google_account'  => 'boolean',
        'deleted_at'         => 'datetime',
    ];
}

// Relationships:
public function barangay(): BelongsTo
public function workerProfile(): HasOne
public function bookingsAsClient(): HasMany
public function bookingsAsWorker(): HasMany
public function messages(): HasMany
public function notifications(): HasMany
public function tokens(): HasMany (Sanctum)
```

---

## TABLE 3: otp_codes **Status: ✅ Exists**

### Important Note
```
Column is "used_at" (timestamp, nullable)
NOT "is_used" (boolean).
used_at IS NOT NULL = code has been used.
This is a superset — gives both the boolean
fact AND the timestamp in one column.
```

```php
Schema::create('otp_codes', function (Blueprint $table) {
    $table->id();
    $table->string('email');
    $table->string('code', 6);
    $table->enum('type', [
        'email_verification',
        'password_reset'
    ]);
    $table->timestamp('expires_at');
    $table->timestamp('used_at')->nullable();
    $table->timestamps();
});
```

### Model
```php
// app/Models/OtpCode.php
protected $fillable = [
    'email', 'code', 'type',
    'expires_at', 'used_at',
];

protected function casts(): array {
    return [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];
}

// Reusable scope:
public function scopeValidFor(
    Builder $query,
    string $email,
    string $type
): Builder {
    return $query
        ->where('email', $email)
        ->where('type', $type)
        ->whereNull('used_at')
        ->where('expires_at', '>', now())
        ->latest();
}
```

---

## TABLE 4: worker_profiles **Status: ✅ Exists**

```php
Schema::create('worker_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->text('bio')->nullable();
    $table->enum('verification_status', [
        'unverified',
        'pending',
        'approved',
        'rejected'
    ])->default('unverified');
    $table->enum('trust_tier', [
        'verified',
        'trusted',
        'flagged',
        'revoked'
    ])->nullable();
    $table->enum('availability_status', [
        'available',
        'busy',
        'offline'
    ])->default('offline');
    $table->decimal('average_rating', 3, 2)
          ->default(0.00);
    $table->integer('total_reviews')->default(0);
    $table->integer('completed_jobs')->default(0);
    $table->text('verification_remarks')->nullable();
    $table->foreignId('verified_by')
          ->nullable()
          ->constrained('users')
          ->nullOnDelete();
    $table->timestamp('verified_at')->nullable();
    $table->timestamps();
});
```

### Model
```php
// app/Models/WorkerProfile.php
protected $fillable = [
    'user_id', 'bio', 'verification_status',
    'trust_tier', 'availability_status',
    'average_rating', 'total_reviews',
    'completed_jobs', 'verification_remarks',
    'verified_by', 'verified_at',
];

protected function casts(): array {
    return [
        'average_rating' => 'decimal:2',
        'verified_at'    => 'datetime',
    ];
}

// Relationships:
public function user(): BelongsTo
public function verificationDocuments(): HasMany
public function serviceCategories(): BelongsToMany
public function jobPosts(): HasMany
public function bookings(): HasMany
public function verifier(): BelongsTo
```

---

## TABLE 5: service_categories **Status: ⏳ Pending**

```php
Schema::create('service_categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('icon')->nullable();
    // icon identifier string for Flutter
    // e.g. "electrical", "plumbing"
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### Seeder Data
```php
$categories = [
    ['name' => 'Electrical Works',
     'icon' => 'electrical'],
    ['name' => 'Plumbing',
     'icon' => 'plumbing'],
    ['name' => 'House Cleaning',
     'icon' => 'cleaning'],
    ['name' => 'Tutoring',
     'icon' => 'tutoring'],
    ['name' => 'Aircon Repair & Cleaning',
     'icon' => 'aircon'],
    ['name' => 'Carpentry',
     'icon' => 'carpentry'],
    ['name' => 'Painting',
     'icon' => 'painting'],
    ['name' => 'Masonry',
     'icon' => 'masonry'],
    ['name' => 'Gardening & Landscaping',
     'icon' => 'gardening'],
    ['name' => 'Cooking & Catering',
     'icon' => 'cooking'],
    ['name' => 'Caregiving',
     'icon' => 'caregiving'],
    ['name' => 'Laundry',
     'icon' => 'laundry'],
    ['name' => 'Welding',
     'icon' => 'welding'],
    ['name' => 'Auto Repair & Mechanic',
     'icon' => 'auto_repair'],
    ['name' => 'Computer Repair & IT',
     'icon' => 'computer'],
    ['name' => 'Barbering & Hairstyling',
     'icon' => 'barber'],
    ['name' => 'Massage & Wellness',
     'icon' => 'massage'],
];
```

### Model
```php
// app/Models/ServiceCategory.php
protected $fillable = [
    'name', 'icon', 'is_active'
];

protected function casts(): array {
    return [
        'is_active' => 'boolean',
    ];
}

// Relationships:
public function workerProfiles(): BelongsToMany
public function jobPosts(): HasMany
public function bookings(): HasMany
```

---

## TABLE 6: worker_service_categories **Status: ⏳ Pending (Pivot Table)**

```php
Schema::create('worker_service_categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('worker_profile_id')
          ->constrained('worker_profiles')
          ->cascadeOnDelete();
    $table->foreignId('service_category_id')
          ->constrained('service_categories')
          ->cascadeOnDelete();
    $table->timestamps();

    $table->unique([
        'worker_profile_id',
        'service_category_id'
    ]);
});
```

### Usage
```php
// WorkerProfile model:
public function serviceCategories(): BelongsToMany
{
    return $this->belongsToMany(
        ServiceCategory::class,
        'worker_service_categories'
    );
}
```

---

## TABLE 7: job_posts **Status: ⏳ Pending**

```php
Schema::create('job_posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('worker_profile_id')
          ->constrained('worker_profiles')
          ->cascadeOnDelete();
    $table->foreignId('service_category_id')
          ->constrained('service_categories')
          ->cascadeOnDelete();
    $table->string('title');
    $table->text('description');
    $table->decimal('rate_amount', 10, 2);
    $table->enum('rate_type', [
        'hourly',
        'daily',
        'weekly',
        'monthly',
        'per_session',
        'per_project'
    ]);
    $table->boolean('is_available')->default(true);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    // One post per category per worker
    $table->unique([
        'worker_profile_id',
        'service_category_id'
    ]);
});
```

### Column Notes
```
rate_amount:  The starting rate amount
              Shown as "From ₱X/[period]"
              Final price negotiated in chat

rate_type:    How the rate is measured
  hourly     → "₱X/hr"
  daily      → "₱X/day"
  weekly     → "₱X/wk"
  monthly    → "₱X/mo"
  per_session → "From ₱X/session"
  per_project → "From ₱X/project"

is_available: Worker's current availability
              for this specific service
              (Available Now vs By Schedule)

is_active:    Whether post is shown in feed
              Worker can toggle this on/off
              softDeletes for admin removal

UNIQUE constraint:
  worker_profile_id + service_category_id
  Enforces one post per category per worker
  If worker creates a new post in same
  category, old one is soft-deleted first
```

### Rate Display Helper
```php
// app/Models/JobPost.php

public function getRateDisplayAttribute(): string
{
    $amount = number_format($this->rate_amount, 2);
    return match($this->rate_type) {
        'hourly'      => "₱{$amount}/hr",
        'daily'       => "₱{$amount}/day",
        'weekly'      => "₱{$amount}/wk",
        'monthly'     => "₱{$amount}/mo",
        'per_session' => "From ₱{$amount}/session",
        'per_project' => "From ₱{$amount}/project",
    };
}
```

### Model
```php
// app/Models/JobPost.php
use SoftDeletes;

protected $fillable = [
    'worker_profile_id', 'service_category_id',
    'title', 'description', 'rate_amount',
    'rate_type', 'is_available', 'is_active',
];

protected $appends = ['rate_display'];

protected function casts(): array {
    return [
        'rate_amount'  => 'decimal:2',
        'is_available' => 'boolean',
        'is_active'    => 'boolean',
    ];
}

// Relationships:
public function workerProfile(): BelongsTo
public function serviceCategory(): BelongsTo
public function bookings(): HasMany
```

---

## TABLE 8: bookings **Status: ⏳ Pending**

```php
Schema::create('bookings', function (Blueprint $table) {
    $table->id();
    $table->string('booking_code')->unique();
    // Format: HB-YYYY-XXXXX e.g. HB-2026-00001
    $table->foreignId('client_id')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->foreignId('worker_id')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->foreignId('service_category_id')
          ->constrained('service_categories');
    $table->foreignId('job_post_id')
          ->nullable()
          ->constrained('job_posts')
          ->nullOnDelete();
    // nullable: booking linked to specific post
    // nullOnDelete: post can be deleted,
    //               booking record stays
    $table->text('notes')->nullable();
    $table->dateTime('scheduled_at');
    $table->enum('status', [
        'pending',
        'accepted',
        'declined',
        'active',
        'completed',
        'cancelled'
    ])->default('pending');
    $table->enum('cancelled_by', [
        'client', 'worker', 'admin'
    ])->nullable();
    $table->text('cancellation_reason')
          ->nullable();
    $table->boolean('is_client_tracking')
          ->default(false);
    $table->boolean('is_worker_tracking')
          ->default(false);
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->foreignId('force_cancelled_by')
          ->nullable()
          ->constrained('users')
          ->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
});
```

### Booking Code Generator (Model Boot)
```php
// app/Models/Booking.php

protected static function boot(): void
{
    parent::boot();

    static::creating(function ($booking) {
        $year = date('Y');
        $count = static::whereYear(
            'created_at', $year
        )->withTrashed()->count() + 1;

        $booking->booking_code =
            'HB-' . $year . '-' .
            str_pad($count, 5, '0', STR_PAD_LEFT);
        // e.g. HB-2026-00001
    });
}
```

### Tracking Fields Notes
```
is_client_tracking:
  true when client tapped "I'm on my way"
  false when client taps "I've Arrived"
  forced false when booking → completed

is_worker_tracking:
  true when worker tapped "I'm on my way"
  false when worker taps "I've Arrived"
  forced false when booking → completed

Both can be true simultaneously
(both parties traveling toward each other)

These flags control the map screen UI:
  true = show animated moving pin
  false = show static barangay center pin
```

### Model
```php
// app/Models/Booking.php
use SoftDeletes;

protected $fillable = [
    'booking_code', 'client_id', 'worker_id',
    'service_category_id', 'job_post_id',
    'notes', 'scheduled_at', 'status',
    'cancelled_by', 'cancellation_reason',
    'is_client_tracking', 'is_worker_tracking',
    'started_at', 'completed_at',
    'force_cancelled_by',
];

protected function casts(): array {
    return [
        'scheduled_at'       => 'datetime',
        'started_at'         => 'datetime',
        'completed_at'       => 'datetime',
        'is_client_tracking' => 'boolean',
        'is_worker_tracking' => 'boolean',
    ];
}

// Relationships:
public function client(): BelongsTo
public function worker(): BelongsTo
public function serviceCategory(): BelongsTo
public function jobPost(): BelongsTo
public function tracking(): HasMany
public function messages(): HasMany
public function ratings(): HasMany
public function reports(): HasMany
```

---

## TABLE 9: booking_tracking **Status: ⏳ Pending**

```php
Schema::create('booking_tracking', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')
          ->constrained('bookings')
          ->cascadeOnDelete();
    $table->enum('tracked_role', [
        'client', 'worker'
    ]);
    $table->decimal('latitude', 10, 7);
    $table->decimal('longitude', 10, 7);
    $table->decimal('accuracy', 8, 2)
          ->nullable();
    // GPS accuracy in meters
    $table->timestamp('recorded_at');
    // No updated_at — insert only table
    // No created_at — use recorded_at instead
});
```

### Usage Notes
```
High-frequency write table.
A new row is inserted every ~3 seconds
per tracking party while they are active.

Primary use: WebSocket broadcast (real-time)
Secondary use: This table as backup log

Only the most recent row per booking + role
matters for current location display.

Query for most recent location:
SELECT * FROM booking_tracking
WHERE booking_id = ?
  AND tracked_role = ?
ORDER BY recorded_at DESC
LIMIT 1

Consider periodic cleanup of old rows
to prevent table from growing too large.
After booking is completed, tracking rows
older than 7 days can be deleted.
```

---

## TABLE 10: verification_documents **Status: ⏳ Pending**

```php
Schema::create('verification_documents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('worker_profile_id')
          ->constrained('worker_profiles')
          ->cascadeOnDelete();
    $table->enum('document_type', [
        'government_id',
        'barangay_certificate',
        'selfie_with_id',
        'skill_certificate'
    ]);
    $table->string('file_path');
    // Stored in: storage/app/public/
    //            verifications/{user_id}/
    $table->enum('status', [
        'pending',
        'approved',
        'rejected'
    ])->default('pending');
    $table->text('remarks')->nullable();
    $table->timestamps();
});
```

### Note on File Storage
```
Files stored via Laravel Storage:
  Path: storage/app/public/
        verifications/{user_id}/{filename}
  Public URL via storage symlink:
  http://[host]/storage/verifications/
        {user_id}/{filename}

php artisan storage:link must be run
after deployment to create the symlink.
```

---

## TABLE 11: ratings_reviews **Status: ⏳ Pending**

```php
Schema::create('ratings_reviews', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')
          ->constrained('bookings')
          ->cascadeOnDelete();
    $table->foreignId('rated_by')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->foreignId('rated_user')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->unsignedTinyInteger('score');
    // 1 to 5 stars
    $table->text('comment')->nullable();
    $table->timestamps();

    $table->unique(['booking_id', 'rated_by']);
    // One review per person per booking
});
```

### Rating Recalculation
```php
// Called after every new rating is saved
// where rated_user is a worker:

$workerProfile = WorkerProfile::where(
    'user_id', $ratedUserId
)->first();

$stats = RatingReview::where(
    'rated_user', $ratedUserId
)->selectRaw(
    'AVG(score) as avg_score,
     COUNT(*) as total'
)->first();

$workerProfile->update([
    'average_rating' => round($stats->avg_score, 2),
    'total_reviews'  => $stats->total,
]);
```

### Visibility Rules
```
Client ratings of workers:
  → PUBLICLY visible on worker profile
  → Shown in Posts tab reviews section
  → Used to calculate average_rating

Worker ratings of clients:
  → NOT publicly visible
  → Used internally by admin only
  → Helps admin identify problematic clients
```

---

## TABLE 12: reports **Status: ⏳ Pending**

```php
Schema::create('reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')
          ->nullable()
          ->constrained('bookings')
          ->nullOnDelete();
    $table->foreignId('reported_by')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->foreignId('reported_user')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->enum('reason', [
        'no_show',
        'unsatisfactory_work',
        'misconduct',
        'non_payment',
        'unsafe_environment',
        'abusive_behavior',
        'false_information',
        'other'
    ]);
    $table->text('description');
    $table->json('evidence_paths')->nullable();
    // Array of file paths for uploaded photos
    $table->enum('status', [
        'under_review',
        'resolved',
        'dismissed'
    ])->default('under_review');
    $table->text('admin_remarks')->nullable();
    $table->enum('resolution_action', [
        'warning_issued',
        'account_suspended',
        'verification_revoked',
        'no_action'
    ])->nullable();
    $table->foreignId('resolved_by')
          ->nullable()
          ->constrained('users')
          ->nullOnDelete();
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();
});
```

### Evidence File Storage
```
Evidence photos stored via Laravel Storage:
  Path: storage/app/public/
        reports/{report_id}/{filename}
  Multiple photos per report
  Stored as JSON array in evidence_paths:
  ["reports/1/photo1.jpg",
   "reports/1/photo2.jpg"]
```

---

## TABLE 13: messages **Status: ⏳ Pending**

```php
Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')
          ->constrained('bookings')
          ->cascadeOnDelete();
    $table->foreignId('sender_id')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->foreignId('receiver_id')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->text('content');
    $table->string('attachment_path')
          ->nullable();
    $table->boolean('is_read')->default(false);
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

---

## TABLE 14: hanapbuhay_notifications **Status: ⏳ Pending**

```php
// Named hanapbuhay_notifications to avoid
// conflict with Laravel's built-in
// notifications table

Schema::create('hanapbuhay_notifications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->string('title');
    $table->text('body');
    $table->enum('type', [
        'booking_request',
        'booking_accepted',
        'booking_declined',
        'booking_completed',
        'booking_cancelled',
        'verification_approved',
        'verification_rejected',
        'verification_resubmit',
        'new_message',
        'new_rating',
        'report_resolved',
        'system_announcement',
        'trust_tier_updated'
    ]);
    $table->json('data')->nullable();
    // Additional data e.g. { booking_id: 1 }
    // Used for deep-linking in Flutter
    $table->boolean('is_read')->default(false);
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
});
```

---

## TABLE 15: device_tokens **Status: ⏳ Pending**

```php
Schema::create('device_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->string('fcm_token');
    $table->enum('device_type', [
        'android', 'ios', 'web'
    ])->default('android');
    $table->timestamps();

    $table->unique(['user_id', 'fcm_token']);
    // Prevent duplicate tokens per user
});
```

### Usage Notes
```
FCM token registered on app start:
  POST /api/user/fcm-token
  body: { fcm_token, device_type }

Updated when Firebase issues a new token.

Used by NotificationService to send
push notifications to all of a user's
registered devices.

One user can have multiple device tokens
(multiple devices or reinstalls).
```

---

## TABLE 16: admin_audit_logs **Status: ⏳ Pending**

```php
Schema::create('admin_audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admin_id')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->string('action');
    // e.g. 'approved_worker_verification'
    // e.g. 'suspended_user'
    // e.g. 'resolved_report'
    // e.g. 'force_cancelled_booking'
    $table->string('target_type')->nullable();
    // e.g. 'WorkerProfile', 'User',
    //      'Booking', 'Report', 'JobPost'
    $table->unsignedBigInteger('target_id')
          ->nullable();
    $table->json('details')->nullable();
    // Additional context for the action
    $table->string('ip_address')->nullable();
    $table->timestamps();
    // No softDeletes — audit logs are permanent
});
```

---

## Distance Computation Helper

### Haversine Formula (PHP)
```php
// app/Helpers/DistanceHelper.php

class DistanceHelper
{
    public static function haversine(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1))
           * cos(deg2rad($lat2))
           * sin($dLng / 2)
           * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 1);
        // Returns km rounded to 1 decimal
        // e.g. 2.3
    }
}
```

### Usage in Feed Query
```php
// For each job post in the feed,
// compute distance from client's barangay
// to worker's barangay:

$clientBarangay = auth()->user()->barangay;

$distance = $clientBarangay
    ? DistanceHelper::haversine(
        $clientBarangay->latitude,
        $clientBarangay->longitude,
        $workerBarangay->latitude,
        $workerBarangay->longitude
      )
    : null;

$distanceLabel = $distance !== null
    ? "~{$distance} km"
    : "Distance unavailable";
```

---

## Key Relationships Summary

```
User
├── belongsTo Barangay
├── hasOne WorkerProfile (if worker)
├── hasMany Bookings (as client_id)
├── hasMany Bookings (as worker_id)
├── hasMany Messages (as sender)
├── hasMany Messages (as receiver)
├── hasMany HanapbuhayNotifications
├── hasMany DeviceTokens
└── hasMany RatingReviews (given + received)

WorkerProfile
├── belongsTo User
├── hasMany VerificationDocuments
├── belongsToMany ServiceCategories
│   (through worker_service_categories)
├── hasMany JobPosts
└── hasMany Bookings (through worker user)

JobPost
├── belongsTo WorkerProfile
├── belongsTo ServiceCategory
└── hasMany Bookings

Booking
├── belongsTo User (client)
├── belongsTo User (worker)
├── belongsTo ServiceCategory
├── belongsTo JobPost (nullable)
├── hasMany BookingTracking
├── hasMany Messages
├── hasMany RatingReviews (max 2)
└── hasMany Reports

Barangay
└── hasMany Users
```

---

## Indexes to Add for Performance

```sql
-- Feed query performance
CREATE INDEX idx_job_posts_active
ON job_posts(is_active, deleted_at);

CREATE INDEX idx_job_posts_category
ON job_posts(service_category_id, is_active);

-- Booking queries
CREATE INDEX idx_bookings_client
ON bookings(client_id, status);

CREATE INDEX idx_bookings_worker
ON bookings(worker_id, status);

-- OTP lookup
CREATE INDEX idx_otp_email_type
ON otp_codes(email, type);

-- Notifications
CREATE INDEX idx_notifications_user
ON hanapbuhay_notifications(user_id, is_read);

-- Tracking (most recent per booking+role)
CREATE INDEX idx_tracking_booking_role
ON booking_tracking(booking_id, tracked_role,
                    recorded_at DESC);

-- Messages unread count
CREATE INDEX idx_messages_receiver_read
ON messages(receiver_id, is_read);
```

---

## CORS Configuration

```php
// config/cors.php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'],
// Tighten before production deployment
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

---

## Environment Variables Reference

```env
APP_NAME=HanapBuhay
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hanapbuhay
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=log
# Change to smtp for real email:
# MAIL_MAILER=smtp
# MAIL_HOST=smtp-relay.brevo.com
# MAIL_PORT=587
# MAIL_USERNAME=your_brevo_email
# MAIL_PASSWORD=your_brevo_smtp_key
# MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@hanapbuhay.ph
MAIL_FROM_NAME="HanapBuhay"

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/api/auth/google/callback

FIREBASE_PROJECT_ID=
FIREBASE_SERVER_KEY=
# Deferred to Phase 3

SOKETI_APP_ID=hanapbuhay-app
SOKETI_APP_KEY=hanapbuhay-key
SOKETI_APP_SECRET=hanapbuhay-secret
SOKETI_HOST=127.0.0.1
SOKETI_PORT=6001

BROADCAST_DRIVER=pusher
PUSHER_APP_ID=hanapbuhay-app
PUSHER_APP_KEY=hanapbuhay-key
PUSHER_APP_SECRET=hanapbuhay-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1
```
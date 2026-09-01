# HanapBuhay — Database Schema
**For: Laravel Developer (PM)**

---

> This document defines every table, column,
> relationship, and seeder for the HanapBuhay
> MySQL database.
>
> Build migrations IN THE ORDER they appear
> in this document. Some tables depend on
> others being created first.

---

## Migration Build Order

```
1.  barangays
2.  users
3.  worker_profiles
4.  verification_documents
5.  service_categories
6.  worker_service_categories (pivot)
7.  bookings
8.  booking_tracking
9.  ratings_reviews
10. reports
11. messages
12. notifications
13. otp_codes
14. admin_audit_logs
```

---

## Table 1: barangays

Stores all 31 barangays of Trinidad, Bohol
with their center coordinates.
This is a **seeded, static table** —
never modified by users.

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

### Barangay Seeder Data (All 31 Trinidad Barangays)

```php
// database/seeders/BarangaySeeder.php
$barangays = [
    ['name' => 'Banlasan',          'latitude' => 10.0244195, 'longitude' => 124.3051417],
    ['name' => 'Bongbong',          'latitude' => 10.0116126, 'longitude' => 124.3267320],
    ['name' => 'Catoogan',          'latitude' => 10.0450587, 'longitude' => 124.4035561],
    ['name' => 'Guinobatan',        'latitude' => 10.0736300, 'longitude' => 124.3562141],
    ['name' => 'Hinlayagan Ilaud',  'latitude' => 10.0336482, 'longitude' => 124.3475796],
    ['name' => 'Hinlayagan Ilaya',  'latitude' => 10.0304092, 'longitude' => 124.3407613],
    ['name' => 'Kauswagan',         'latitude' => 10.0257143, 'longitude' => 124.2627008],
    ['name' => 'Kinan-oan',         'latitude' => 10.0518790, 'longitude' => 124.3251786],
    ['name' => 'La Union',          'latitude' => 10.0560058, 'longitude' => 124.3713304],
    ['name' => 'La Victoria',       'latitude' => 10.0927281, 'longitude' => 124.3630097],
    ['name' => 'Mabuhay Cabigohan', 'latitude' => 10.0475238, 'longitude' => 124.3459718],
    ['name' => 'Mahagbu',           'latitude' => 10.0382731, 'longitude' => 124.3811724],
    ['name' => 'Manuel M. Roxas',   'latitude' => 10.0264936, 'longitude' => 124.3652967],
    ['name' => 'Poblacion',         'latitude' => 10.0800649, 'longitude' => 124.3446833],
    ['name' => 'San Isidro',        'latitude' => 10.0146793, 'longitude' => 124.2978160],
    ['name' => 'San Vicente',       'latitude' => 10.0610914, 'longitude' => 124.3953556],
    ['name' => 'Santo Tomas',       'latitude' => 10.0434725, 'longitude' => 124.3250846],
    ['name' => 'Soom',              'latitude' => 10.0616358, 'longitude' => 124.3942074],
    ['name' => 'Tagum Norte',       'latitude' => 10.0795559, 'longitude' => 124.3746942],
    ['name' => 'Tagum Sur',         'latitude' => 10.0709313, 'longitude' => 124.3757935],
];

// NOTE: Coordinates above are approximate center points.
// Verify and update with accurate coordinates
// from Google Maps before final deployment.
// Right-click any barangay center on Google Maps
// → "What's here?" → copy lat/lng
```

---

## Table 2: users

Main user table for all roles
(client, worker, admin).

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('mobile_number')->nullable();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password')->nullable();
    // nullable for Google OAuth users
    $table->enum('role', ['client', 'worker', 'admin'])
          ->default('client');
    $table->string('profile_photo_path')->nullable();
    $table->foreignId('barangay_id')
          ->nullable()
          ->constrained('barangays')
          ->nullOnDelete();
    $table->string('google_id')->nullable()->unique();
    $table->boolean('is_active')->default(true);
    $table->boolean('is_google_account')
          ->default(false);
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();
});
```

**Relationships:**
- `belongsTo` Barangay
- `hasOne` WorkerProfile (if role = worker)
- `hasMany` Bookings (as client)
- `hasMany` Bookings (as worker, through WorkerProfile)

---

## Table 3: worker_profiles

Extended profile for users with role = 'worker'.
One-to-one with users table.

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
    $table->string('verification_remarks')
          ->nullable();
    // Remarks from admin on reject/approve
    $table->foreignId('verified_by')
          ->nullable()
          ->constrained('users')
          ->nullOnDelete();
    $table->timestamp('verified_at')->nullable();
    $table->timestamps();
});
```

---

## Table 4: verification_documents

Stores document submissions from workers.

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
    $table->enum('status', [
        'pending',
        'approved',
        'rejected'
    ])->default('pending');
    $table->text('remarks')->nullable();
    $table->timestamps();
});
```

---

## Table 5: service_categories

Master list of service categories available
on the platform. Admin-managed.

```php
Schema::create('service_categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    // e.g. "Electrical", "Plumbing", "Tutoring"
    $table->string('icon')->nullable();
    // icon name for Flutter display
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### Service Category Seeder

```php
$categories = [
    'Electrical Works',
    'Plumbing',
    'House Cleaning',
    'Tutoring',
    'Aircon Repair & Cleaning',
    'Carpentry',
    'Painting',
    'Masonry',
    'Gardening & Landscaping',
    'Cooking & Catering',
    'Caregiving',
    'Laundry',
    'Welding',
    'Auto Repair & Mechanic',
    'Computer Repair & IT',
];
```

---

## Table 6: worker_service_categories (Pivot)

Many-to-many: workers can offer multiple
service categories.

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

---

## Table 7: bookings

Core booking/transaction table.

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
        'client',
        'worker',
        'admin'
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

**Key Notes:**
- `booking_code` is the human-readable ID
  shown in the app (HB-2026-00001)
- `is_client_tracking` and `is_worker_tracking`
  both control live location sharing
- Either or both can be true simultaneously
- Set to false when "I've Arrived" is tapped

---

## Table 8: booking_tracking

Stores real-time GPS coordinates during
live location tracking. High-frequency writes.

```php
Schema::create('booking_tracking', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')
          ->constrained('bookings')
          ->cascadeOnDelete();
    $table->enum('tracked_role', [
        'client',
        'worker'
    ]);
    // Which party is being tracked
    $table->decimal('latitude', 10, 7);
    $table->decimal('longitude', 10, 7);
    $table->decimal('accuracy', 8, 2)
          ->nullable();
    // GPS accuracy in meters
    $table->timestamp('recorded_at');
    // Use timestamp, not created_at
    // for performance
});

// Note: This table grows fast during tracking.
// Only keep last N records per booking
// or clear after booking is completed.
// No updated_at column — insert only.
```

**Important:** For live tracking, the most
recent row per booking + role is what matters.
Use a WebSocket broadcast instead of polling
this table. The table serves as a backup log.

---

## Table 9: ratings_reviews

Post-booking two-way rating system.
Both client and worker rate each other.

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

---

## Table 10: reports

Dispute and issue reports filed by
clients or workers.

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

---

## Table 11: messages

In-app chat messages tied to bookings.

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

## Table 12: notifications

In-app notification records.

```php
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
    // Additional data (e.g., booking_id)
    $table->boolean('is_read')->default(false);
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
});

// Note: Named 'hanapbuhay_notifications'
// to avoid conflict with Laravel's built-in
// notifications table
```

---

## Table 13: otp_codes

Stores OTP codes for email verification
and password reset.

```php
Schema::create('otp_codes', function (Blueprint $table) {
    $table->id();
    $table->string('email');
    $table->string('code', 6);
    $table->enum('type', [
        'email_verification',
        'password_reset'
    ]);
    $table->boolean('is_used')->default(false);
    $table->timestamp('expires_at');
    // OTP valid for 10 minutes
    $table->timestamps();
});
```

---

## Table 14: admin_audit_logs

Tracks all admin actions for accountability.

```php
Schema::create('admin_audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admin_id')
          ->constrained('users')
          ->cascadeOnDelete();
    $table->string('action');
    // e.g. "approved_worker_verification"
    $table->string('target_type')->nullable();
    // e.g. "WorkerProfile", "User", "Booking"
    $table->unsignedBigInteger('target_id')
          ->nullable();
    $table->json('details')->nullable();
    // Additional action details
    $table->string('ip_address')->nullable();
    $table->timestamps();
});
```

---

## Key Relationships Summary

```
User
├── belongsTo Barangay
├── hasOne WorkerProfile (workers only)
├── hasMany Bookings (as client)
├── hasMany Bookings (as worker)
├── hasMany Messages (as sender)
├── hasMany Messages (as receiver)
├── hasMany Notifications
└── hasMany Ratings (given and received)

WorkerProfile
├── belongsTo User
├── hasMany VerificationDocuments
├── belongsToMany ServiceCategories
│   (through worker_service_categories)
└── hasMany Bookings (through worker user)

Booking
├── belongsTo User (client)
├── belongsTo User (worker)
├── belongsTo ServiceCategory
├── hasMany BookingTracking
├── hasMany Messages
├── hasMany Ratings (max 2: client + worker)
└── hasMany Reports

Barangay
└── hasMany Users
```

---

## Distance Computation (Haversine)

This is a pure PHP/Laravel computation —
no special database extension needed.

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

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) *
             cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 1);
        // Returns distance in km, 1 decimal
        // e.g. 2.3
    }
}
```

### Usage in Worker Search:
```php
// In WorkerController@index:

$clientBarangay = auth()->user()->barangay;

$workers = WorkerProfile::with(['user.barangay',
    'serviceCategories'])
    ->where('verification_status', 'approved')
    ->when($request->barangay_id, function($q) use ($request) {
        $q->whereHas('user', function($q) use ($request) {
            $q->where('barangay_id', $request->barangay_id);
        });
    })
    ->get()
    ->map(function($worker) use ($clientBarangay) {
        $workerBarangay = $worker->user->barangay;
        $distance = null;

        if ($clientBarangay && $workerBarangay) {
            $distance = DistanceHelper::haversine(
                $clientBarangay->latitude,
                $clientBarangay->longitude,
                $workerBarangay->latitude,
                $workerBarangay->longitude
            );
        }

        return [
            'id' => $worker->id,
            'name' => $worker->user->name,
            'barangay' => $workerBarangay?->name,
            'distance_km' => $distance,
            'distance_label' => $distance
                ? "~{$distance} km"
                : 'Distance unavailable',
            // ... other fields
        ];
    });
```

---

## Booking Code Generator

```php
// In Booking model boot():

protected static function boot()
{
    parent::boot();

    static::creating(function ($booking) {
        $year = date('Y');
        $count = static::whereYear(
            'created_at', $year)->count() + 1;
        $booking->booking_code =
            'HB-' . $year . '-' .
            str_pad($count, 5, '0', STR_PAD_LEFT);
        // e.g. HB-2026-00001
    });
}
```

# HanapBuhay Backend — Progress Checklist

**Reference:** `hanapbuhay-docs/` (latest spec, supersedes `development-docs/`)
**Last Updated:** August 31, 2026
**Backend:** Laravel 13 · PHP 8.5 · MySQL · Sanctum

> **Legend:**
> - ✅ Done — implemented and route registered
> - ⚠️ Partial — file exists but feature is incomplete or missing from routes
> - ❌ Not Started — not yet built

---

## Phase 1 — Foundation

### Database Migrations

| Table | Status | Notes |
|---|---|---|
| `barangays` | ✅ Done | Seeded with all 20 Trinidad, Bohol barangays + OSM coordinates |
| `users` (Laravel default) | ✅ Done | |
| `cache` | ✅ Done | |
| `jobs` | ✅ Done | |
| `add_custom_fields_to_users` | ✅ Done | role, barangay_id, google_id, is_active, softDeletes, etc. |
| `otp_codes` | ✅ Done | Uses `used_at` timestamp (not boolean) |
| `worker_profiles` | ✅ Done | |
| `personal_access_tokens` (Sanctum) | ✅ Done | |
| `add_reset_token_to_otp_codes` | ✅ Done | |
| `verification_documents` | ✅ Done | Migration exists |
| `service_categories` | ✅ Done | Migration exists + seeded |
| `worker_service_categories` (pivot) | ✅ Done | Migration exists |
| `bookings` | ✅ Done | Migration exists |
| `booking_tracking` | ✅ Done | Migration exists |
| `ratings_reviews` | ✅ Done | Migration exists |
| `reports` | ✅ Done | Migration exists |
| `messages` | ✅ Done | Migration exists |
| `hanapbuhay_notifications` | ✅ Done | Migration exists |
| `device_tokens` | ✅ Done | Migration exists |
| `admin_audit_logs` | ✅ Done | Migration exists |
| `announcements` | ✅ Done | Migration + model + factory; used for system-wide announcements |

### Seeders

| Seeder | Status | Notes |
|---|---|---|
| `BarangaySeeder` | ✅ Done | All 20 barangays |
| `ServiceCategorySeeder` | ✅ Done | All 17 default categories |
| Admin user seeder | ✅ Done | `AdminUserSeeder` — `admin@hanapbuhay.com` / `Admin@1234!`; idempotent via `updateOrCreate` |

---

## Phase 2 — Authentication & User

### Models

| Model | Status | Notes |
|---|---|---|
| `User` | ✅ Done | SoftDeletes, relationships, casts |
| `Barangay` | ✅ Done | |
| `OtpCode` | ✅ Done | `scopeValidFor` scope present |
| `WorkerProfile` | ✅ Done | All relationships |
| `ServiceCategory` | ✅ Done | |
| `Booking` | ✅ Done | Auto booking_code generator in boot |
| `BookingTracking` | ✅ Done | |
| `VerificationDocument` | ✅ Done | |
| `RatingReview` | ✅ Done | |
| `Report` | ✅ Done | |
| `Message` | ✅ Done | |
| `HanapbuhayNotification` | ✅ Done | |
| `DeviceToken` | ✅ Done | |
| `AdminAuditLog` | ✅ Done | |
| `JobPost` | ✅ Done | SoftDeletes, `rate_display` accessor, relationships |
| `Announcement` | ✅ Done | `scopeActive` (excludes expired), `postedBy` relation |

### Auth Endpoints (`/api/auth/...`)

| Endpoint | Status | Notes |
|---|---|---|
| `POST /auth/register` | ✅ Done | Creates user + worker_profile, sends OTP email |
| `POST /auth/email/verify` | ✅ Done | Verifies OTP, returns Sanctum token |
| `POST /auth/email/resend-otp` | ✅ Done | Rate-limited (3/10 min) |
| `POST /auth/login` | ✅ Done | Returns token, handles unverified + suspended |
| `POST /auth/logout` | ✅ Done | Revokes current token only |
| `POST /auth/google` | ✅ Done | Server-side Google ID token verification via Socialite |
| `POST /auth/google/complete-profile` | ✅ Done | For new Google users (role=null) |
| `POST /auth/password/forgot` | ✅ Done | Rate-limited, always 200 (security) |
| `POST /auth/password/verify-otp` | ✅ Done | Returns short-lived reset_token |
| `POST /auth/password/reset` | ✅ Done | |

### User Profile Endpoints (`/api/user/...`)

| Endpoint | Status | Notes |
|---|---|---|
| `GET /user` | ✅ Done | Returns authenticated user + worker_profile if applicable |
| `POST /user/profile` (update) | ✅ Done | Updates name, mobile, barangay, profile photo |
| `POST /user/password` (change) | ✅ Done | Handles regular + Google-only accounts |
| `GET /user/sessions` | ✅ Done | Lists all active Sanctum tokens, flags current |
| `DELETE /user/sessions/{tokenId}` | ✅ Done | Revokes specific session; blocks self-revoke |
| `POST /user/fcm-token` | ✅ Done | Alias route pointing to `NotificationController::registerDevice`; idempotent |

---

## Phase 3 — Worker Features

### Worker Discovery / Feed Endpoints

| Endpoint | Status | Notes |
|---|---|---|
| `GET /api/feed` | ✅ Done | Client-only; paginated 15/page; sorted distance → trust tier → rating; filters: category, barangay, rate_type, verification, availability |
| `GET /api/workers` | ✅ Done | Worker search/list with Haversine distance via `WorkerSearchService` |
| `GET /api/workers/{workerProfileId}` | ✅ Done | Public worker profile detail |
| `GET /api/categories/{id}/workers` | ✅ Done | Browse workers by category with barangay + availability filters |
| `GET /api/service-categories` | ✅ Done | Public route, returns active categories sorted alphabetically |
| `GET /api/barangays` | ✅ Done | Public route, returns active barangays sorted alphabetically |
| `GET /api/ping` | ✅ Done | Health check, no auth required |

### Job Posts Endpoints (`/api/worker/posts`)

| Endpoint | Status | Notes |
|---|---|---|
| `POST /worker/posts` | ✅ Done | Creates post; replaces existing post in same category (soft-delete) |
| `GET /worker/posts` | ✅ Done | Lists worker's own posts; `include_inactive` flag supported |
| `PUT /worker/posts/{postId}` | ✅ Done | Ownership-checked update |
| `DELETE /worker/posts/{postId}` | ✅ Done | Soft-delete (deactivate); ownership-checked |

### Worker Profile & Verification Endpoints

| Endpoint | Status | Notes |
|---|---|---|
| `POST /worker/verification/submit` | ✅ Done | Uploads documents, sets status to pending |
| `GET /worker/verification/status` | ✅ Done | Returns per-document status |
| `POST /worker/profile` | ✅ Done | bio, availability_status, category_ids, portfolio_photos |

---

## Phase 4 — Bookings

### Booking Endpoints

| Endpoint | Status | Notes |
|---|---|---|
| `POST /bookings` | ✅ Done | Client only; creates booking; unverified workers allowed per spec (warning is client-side UI) |
| `GET /bookings` | ✅ Done | Lists bookings for authenticated user (client or worker) |
| `GET /bookings/{id}` | ✅ Done | |
| `POST /bookings/{id}/accept` | ✅ Done | Worker only |
| `POST /bookings/{id}/decline` | ✅ Done | Worker only |
| `POST /bookings/{id}/cancel` | ✅ Done | Client or worker |
| `POST /bookings/{id}/status` | ✅ Done | Spec §F6 — worker sets `active` or `completed` via unified endpoint |
| `POST /bookings/{id}/start` | ✅ Done | Worker marks job started → status: active (legacy) |
| `POST /bookings/{id}/complete` | ✅ Done | Worker marks completed (legacy) |
| `POST /bookings/{id}/rate` | ✅ Done | Submits rating; recalculates worker average (legacy) |
| `POST /api/ratings` | ✅ Done | Spec §H1 — booking_id in body; comment max:300 |
| Booking respond endpoint (`/respond`) | ✅ Done | `POST /bookings/{id}/respond` with `action: accept/decline` added alongside existing separate routes |

---

## Phase 5 — Live Tracking

### Tracking Endpoints

| Endpoint | Status | Notes |
|---|---|---|
| `POST /bookings/{id}/tracking/start` | ✅ Done | Sets `is_{role}_tracking = true`, broadcasts `LocationTrackingStarted` |
| `POST /bookings/{id}/tracking/update` | ✅ Done | Inserts `BookingTracking` row, broadcasts `LocationUpdated` (legacy URL) |
| `POST /bookings/{id}/tracking/location` | ✅ Done | Spec §G2 alias for update — REST fallback URL the Flutter dev expects |
| `POST /bookings/{id}/tracking/stop` | ✅ Done | Sets `is_{role}_tracking = false`, broadcasts `LocationTrackingStopped` |
| `GET /bookings/{id}/tracking` | ✅ Done | Returns current tracking flags + barangay coordinates |

### WebSocket / Soketi Events

| Event | Status | Notes |
|---|---|---|
| `LocationTrackingStarted` | ✅ Done | Event class exists, dispatched in TrackingController |
| `LocationUpdated` | ✅ Done | Event class exists, dispatched in TrackingController |
| `LocationTrackingStopped` | ✅ Done | Event class exists, dispatched in TrackingController |
| `NewMessage` | ✅ Done | Event class exists, dispatched in MessageService |
| `soketi.json` config | ✅ Done | Soketi config file present at root |
| Channel auth (`/api/broadcasting/auth`) | ✅ Done | Handled automatically by Laravel Sanctum broadcasting — no custom route needed |

---

## Phase 6 — Messaging

### Message Endpoints

| Endpoint | Status | Notes |
|---|---|---|
| `GET /messages` (inbox) | ✅ Done | Conversations list sorted by most recent message; includes unread_count per booking |
| `GET /bookings/{id}/messages` | ✅ Done | Paginated message thread; now also auto-marks unread as read |
| `POST /bookings/{id}/messages` | ✅ Done | Send message, broadcasts `NewMessage` event; attachment support added |
| Message attachment upload | ✅ Done | `attachment` field (jpeg/png ≤5MB); `attachment_url` in response |
| Read receipts (`is_read` / `read_at`) | ✅ Done | Auto-marked on GET thread (both spec and legacy URLs) |

---

## Phase 7 — Notifications

### Notification Endpoints

| Endpoint | Status | Notes |
|---|---|---|
| `POST /notifications/register-device` | ✅ Done | Stores FCM token via `updateOrCreate` |
| `GET /notifications` | ✅ Done | Paginated list, most recent first, includes unread_count |
| `POST /notifications/{id}/read` | ✅ Done | Mark single notification read; 404 on wrong owner |
| `POST /notifications/read-all` | ✅ Done | Marks all unread for current user; idempotent |

### Push Notifications (FCM)

| Feature | Status | Notes |
|---|---|---|
| FCM token storage (`device_tokens` table) | ✅ Done | Model + migration + endpoint done |
| `NotificationService` | ✅ Done | Service class exists |
| Actual FCM push sending (via kreait/firebase) | ✅ Done | `sendPush` wired and tested; invalid tokens auto-deleted on `MessagingException` |
| In-app notification creation (`hanapbuhay_notifications`) | ✅ Done | `notify()` called alongside `sendPush()` in BookingService (accept, decline, complete) and RatingService |

---

## Phase 8 — Reports

### Report Endpoints

| Endpoint | Status | Notes |
|---|---|---|
| `POST /reports` | ✅ Done | File report with evidence photos |
| `GET /reports` | ✅ Done | User's own filed reports |
| `GET /reports/{id}` | ✅ Done | Report detail with evidence paths |

---

## Phase 9 — Admin Panel

### Admin Authentication

| Feature | Status | Notes |
|---|---|---|
| Admin login (shared `POST /auth/login`) | ✅ Done | Role checked on login |
| Admin middleware (`EnsureAdmin`) | ✅ Done | Applied to all `/api/admin/*` routes |
| Admin logout (shared `POST /auth/logout`) | ✅ Done | |

### Admin — Dashboard

| Endpoint | Status | Notes |
|---|---|---|
| `GET /admin/dashboard` | ✅ Done | Returns aggregate stats via `AdminService::dashboardStats()` |

### Admin — Verification Management

| Endpoint | Status | Notes |
|---|---|---|
| `GET /admin/verifications` | ✅ Done | Lists worker verifications, filterable by status |
| `GET /admin/verifications/pending` | ✅ Done | Spec §K2 named sub-path alias — returns only pending |
| `POST /admin/verifications/{id}/review` | ✅ Done | Approve, reject, or request_resubmission |
| Request resubmission action (`/respond`) | ✅ Done | `action: request_resubmission` now accepted; sets `verification_status = resubmission_required` on profile + docs; notifies worker |
| Update worker trust tier | ✅ Done | `POST /admin/workers/{id}/trust-tier`; notifies worker; writes audit log |

### Admin — User Management

| Endpoint | Status | Notes |
|---|---|---|
| `GET /admin/users` | ✅ Done | Filterable by role, is_active/status, barangay, search |
| `GET /admin/users/{id}` | ✅ Done | Full user detail including worker_profile |
| `PATCH /admin/users/{id}/toggle-active` | ✅ Done | Legacy — suspend/reactivate, cannot self-deactivate |
| `POST /admin/users/{id}/toggle-status` | ✅ Done | Spec §K7 — explicit `action: suspend/reactivate` + `reason`; writes audit log |
| Account deletion queue | ✅ Done | `POST /user/delete-account` (request) + `DELETE /user/delete-account` (cancel) + `GET /admin/deletion-requests` + `POST /admin/deletion-requests/{id}/process` (anonymises PII, soft-deletes, audit logged) |

### Admin — Job Post Oversight

| Endpoint | Status | Notes |
|---|---|---|
| `GET /admin/posts` | ✅ Done | Lists all posts including soft-deleted; filterable by category_id + worker_profile_id |
| `DELETE /admin/posts/{id}` | ✅ Done | Hard-delete (forceDelete); writes audit log |

### Admin — Booking Oversight

| Endpoint | Status | Notes |
|---|---|---|
| `GET /admin/bookings` | ✅ Done | Filterable by status, category_id, date_from, date_to, search |
| `GET /admin/bookings/{id}` | ✅ Done | Full booking detail |
| `POST /admin/bookings/{id}/cancel` | ✅ Done | Force-cancel any non-terminal booking; notifies both parties; writes audit log |

### Admin — Reports & Disputes

| Endpoint | Status | Notes |
|---|---|---|
| `GET /admin/reports` | ✅ Done | List all reports, filterable by status |
| `GET /admin/reports/{id}` | ✅ Done | Full report detail with evidence |
| `PATCH /admin/reports/{id}/resolve` | ✅ Done | Resolve with action + admin notes |
| Apply resolution actions (suspend/revoke) | ✅ Done | `action` field on resolve endpoint: `suspend_user`, `revoke_trust_tier`, `warn_user` — enforced in DB inside transaction |

### Admin — Ratings Oversight

| Endpoint | Status | Notes |
|---|---|---|
| `GET /admin/ratings` | ✅ Done | Filterable by worker_id, client_id, score, direction, search |
| `DELETE /admin/ratings/{id}` | ✅ Done | Hard-delete; recalculates worker average; writes audit log |

### Admin — Audit Logs

| Endpoint | Status | Notes |
|---|---|---|
| `GET /admin/audit-logs` | ✅ Done | Filterable by admin_id, action, target_type, date_from, date_to; paginated at 25/page |
| Audit log writes on admin actions | ✅ Done | Written on: trust tier, force cancel, delete rating, create/update category, delete post |

### Admin — Platform Settings

| Endpoint | Status | Notes |
|---|---|---|
| `GET /api/admin/settings` | ✅ Done | Returns service_categories, report_reasons, notification_templates, active_announcement |
| `POST /api/admin/settings` | ✅ Done | `action=post_announcement` (notifies all users, audit logged) + `action=add_category` |

---

## Helpers, Middleware & Policies

| Item | Status | Notes |
|---|---|---|
| `DistanceHelper` (Haversine) | ✅ Done | `app/Helpers/DistanceHelper.php` |
| `EnsureAdmin` middleware | ✅ Done | |
| `EnsureWorker` middleware | ✅ Done | |
| `EnsureClient` middleware | ✅ Done | |
| `OtpMail` mailable | ✅ Done | |
| `BookingPolicy` | ✅ Done | `app/Policies/` — view, cancel, rate, tracking, messages |
| `ReportPolicy` | ✅ Done | |
| `AuthService` | ✅ Done | login, formatUser, completeProfile, forgot/reset password, resendOtp |
| `WorkerService` | ✅ Done | submitVerification, getVerificationStatus, updateProfile |
| `WorkerSearchService` | ✅ Done | getWorkers (with Haversine), getWorker |
| `BookingService` | ✅ Done | create, list, find, accept, decline, cancel, start, complete |
| `RatingService` | ✅ Done | rate + worker average recalculation |
| `MessageService` | ✅ Done | index, store + broadcasts NewMessage |
| `ReportService` | ✅ Done | create (with file upload), listForUser, find |
| `AdminService` | ✅ Done | dashboardStats, listVerifications, reviewVerification, listUsers, getUser, toggleActive, listBookings, getBooking, listReports, getReport, resolveReport |
| `NotificationService` | ✅ Done | FCM push + in-app notify() both wired and tested |

---

## Summary

### By Count

| Status | Count |
|---|---|
| ✅ Done | ~155 items |
| ⚠️ Partial | 0 items |
| ❌ Not Started | 0 items |

### What's Fully Done
- All database migrations and core seeders (including announcements table)
- Complete authentication flow (manual + Google OAuth + OTP + password reset + profile + sessions)
- Worker verification submission + admin review (approve/reject/request_resubmission)
- Worker profile management + job posts CRUD
- Worker search, public profile view, category browsing
- Complete booking lifecycle with all spec URLs (create, respond, status, cancel, rate)
- Live location tracking — all 4 endpoints including `/tracking/location` alias
- Per-booking messaging + global inbox + attachments + read receipts
- Reports (file, list, detail + admin resolve with side-effects)
- Notifications (FCM push + in-app center + register device on both spec URL and legacy URL)
- Account deletion (user request/cancel + admin queue + PII anonymisation)
- Admin panel: all endpoints fully spec-aligned including:
  - GET/POST `/admin/settings` (categories, report reasons, notification templates, announcements)
  - GET `/admin/verifications/pending` alias
  - POST `/admin/users/{id}/toggle-status` spec URL
  - POST `/admin/bookings/{id}/status` spec URL
  - POST `/api/ratings` spec URL
  - POST `/tracking/location` alias
  - All filter parameters on users, bookings, ratings, audit logs

### What Needs to Be Built

**High Priority (core app features):**
- [x] `JobPost` model + CRUD endpoints (`/api/worker/posts`) — ✅ Done (17 tests passing)
- [x] ~~`/api/feed` endpoint~~ — ✅ Done (17 tests passing)
- [x] ~~Public routes: `GET /api/barangays`, `GET /api/service-categories`, `GET /api/ping`~~ — ✅ Done (15 tests passing)
- [x] ~~`GET /api/categories/{id}/workers`~~ — ✅ Done (included above)

**User Profile:**
- [x] ~~User profile update + password change + sessions endpoints~~ — ✅ Done (19 tests passing)

**Notifications:**
- [x] ~~Notification center endpoints~~ — ✅ Done (15 tests passing)

**Admin — Missing Routes:**
- [x] ~~Admin missing routes~~ — ✅ Done (28 tests passing)
  - Trust tier update, force cancel, ratings oversight, audit logs, platform settings (categories), job post oversight

**To Verify (all resolved):**
- [x] ~~`request_resubmission` action in admin verification review~~ — ✅ Done
- [x] ~~Side-effect enforcement in `AdminService::resolveReport`~~ — ✅ Done
- [x] ~~Audit log writes happening on all admin actions~~ — ✅ Done
- [x] ~~FCM push delivery in `NotificationService`~~ — ✅ Done
- [x] ~~Message attachment upload handling in `MessageController`~~ — field supported in request + model; no file storage required per current spec
- [x] ~~`/api/broadcasting/auth` channel auth registered for Soketi~~ — handled by Laravel Sanctum broadcasting; no custom route needed

```markdown
# HanapBuhay API — Project Handoff Document

---

## 1. Project Overview

HanapBuhay is a barangay-verified skilled worker marketplace
scoped to Trinidad, Bohol, Philippines. Clients book local
workers (plumbers, electricians, etc.) verified through barangay
certificates and government IDs.

**Architecture:** Three separate codebases sharing one database.
- `hanapbuhay-api` — Laravel 13 (this repo, owner: Jay-ar)
- `hanapbuhay-web` — React admin panel (web dev teammate)
- `hanapbuhay-app` — Flutter mobile app (app dev teammate)

Only the API touches the database. Web and app communicate via
HTTP/JSON.

---

## 2. Technology Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| PHP | 8.4.25 |
| Database | MySQL 8.x (via Laragon 6) |
| Auth | Laravel Sanctum |
| OAuth | Laravel Socialite (Google) |
| Mail | Laravel Mail + Brevo SMTP |
| File storage | Laravel Storage (public disk) |
| WebSockets | Soketi 1.6.1 (self-hosted, Pusher-compatible) |
| Push notifications | kreait/laravel-firebase v7.2.1 (FCM) |
| Testing | PEST v4 |
| API style | JSON only, no Blade views |

---

## 3. Local Development Environment

- **OS:** Windows
- **PHP binary:** `C:\php85\php.exe` (PHP 8.4.25, full build)
- **Composer:** `C:\ProgramData\ComposerSetup\bin` (v2.10.2)
- **MySQL:** Laragon 6 (free) — used only for MySQL, not Apache
- **Server:** `php artisan serve` at `http://127.0.0.1:8000`
  (Laragon's Apache/PHP abandoned due to version conflicts)
- **WebSockets:** Soketi via nvm-windows Node v18.20.8
  (`soketi start --config=soketi.json` on port 6001)
- **Branch:** `develop` (main is protected, PR-only)
- **GitHub:** `albertii-alt/hanapbuhay-api`

**Soketi config (`soketi.json` in project root):**
```json
{
  "app_id": "hanapbuhay-app",
  "app_key": "hanapbuhay-key",
  "app_secret": "hanapbuhay-secret",
  "port": 6001
}
```

**Key `.env` values:**
```
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=hanapbuhay-app
PUSHER_APP_KEY=hanapbuhay-key
PUSHER_APP_SECRET=hanapbuhay-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1
FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
```

---

## 4. Database State

All migrations have been run. The database is fully seeded with:
- 31 Trinidad, Bohol barangays (with OSM lat/lng coordinates)
- 15 service categories

**Tables (all exist and are migrated):**

| Table | Notes |
|---|---|
| users | role enum: client, worker, admin; SoftDeletes; barangay_id FK |
| barangays | 31 rows seeded; lat/lng decimal(10,7) |
| worker_profiles | verification_status, average_rating, total_reviews |
| verification_documents | type, status, file_path; FK → worker_profiles |
| service_categories | 15 rows seeded |
| worker_service_categories | pivot: worker_profiles ↔ service_categories |
| otp_codes | type enum: email_verification, password_reset; reset_token |
| personal_access_tokens | Sanctum |
| bookings | booking_code HB-YYYY-XXXXX; status enum; tracking flags |
| booking_tracking | role enum: client, worker; lat/lng; recorded_at |
| ratings_reviews | unique(booking_id, rated_by); rated_user FK → users |
| reports | reason enum; evidence_paths JSON; status enum |
| messages | content (text); receiver_id; read_at; SoftDeletes |
| device_tokens | unique(user_id, fcm_token); device_type enum |
| hanapbuhay_notifications | renamed to avoid Laravel conflict |

**Important schema notes:**
- `messages` table: actual column is `content` (not `message`).
  The API request/response uses the key `message`; the service
  maps it to `content` internally.
- `messages` table has `receiver_id` (required FK) derived as
  the other party on the booking.
- `ratings_reviews`: column is `rated_user` (not `rated_user_id`).
- `booking_tracking`: column is `tracked_role` (not `role`).
  Broadcast payload still uses `role` as the key.
- Worker's FCM push is sent to `workerProfile→user`, not
  directly to `worker_id` — always resolve through the
  user relationship.

---

## 5. Installed Packages

```
laravel/sanctum
laravel/socialite
pusher/pusher-php-server
intervention/image (v4.3)
kreait/laravel-firebase (v7.2.1)
```

---

## 6. Architecture Patterns

All code follows these conventions (enforced throughout):

- **PSR-12** coding standard
- **Thin controllers** — business logic in Service classes
- **Form Request classes** for all input validation
- **Laravel Policies** for resource-level authorization
- **Eloquent relationships** — no raw joins
- **DB::transaction** for any multi-table writes
- **Eager loading** — no N+1 queries
- **try/catch** on file uploads, FCM calls, external APIs
- **Consistent JSON responses:**
  ```json
  // Success
  { "success": true, "message": "...", "data": { } }
  // Error
  { "success": false, "message": "...", "errors": { } }
  ```
- **HTTP status codes:** 201 created, 200 ok, 422 validation,
  401 unauthenticated, 403 forbidden, 404 not found

**Middleware aliases:**
- `client` → `EnsureClient` (role = client, else 403)
- `worker` → `EnsureWorker` (role = worker, else 403)
- `admin`  → `EnsureAdmin`  (role = admin, else 403)

**Broadcast channel:** `private-booking.{bookingId}`
Channel auth in `routes/channels.php` — only client_id or
worker_id on the booking may subscribe.

---

## 7. PEST Test Rules (Critical)

These rules prevent suite-wide crashes and must be followed
in every new test file:

1. **Never define global helper functions** inside a test file.
   Global function name collisions crash the entire PEST suite
   since all files load in one process.
2. **Use local closures** assigned to variables for shared setup,
   captured into each `it()` block via `use($closure)`.
3. **Available global helpers** from `tests/Pest.php`
   (call directly, never redefine):
   - `makeBookingClient()` — creates a verified client user
   - `makeApprovedBookingWorker()` — creates a verified +
     approved worker with worker_profile
4. **Use `RefreshDatabase`** on every test.
5. **Use `Event::fake()`** for broadcast assertions — never
   test actual Soketi WebSocket connections.
6. **Mock `NotificationService`** in tests that trigger FCM
   — never make real FCM network calls in tests.

---

## 8. API Endpoints (All Implemented)

### Auth
```
POST /api/auth/register
POST /api/auth/verify-otp
POST /api/auth/resend-otp
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/me
POST /api/auth/google
POST /api/auth/forgot-password
POST /api/auth/verify-reset-otp
POST /api/auth/reset-password
```

### Worker
```
POST /api/worker/verification/submit
GET  /api/worker/verification/status
PUT  /api/worker/profile
GET  /api/workers
GET  /api/workers/{workerProfileId}
```

### Bookings
```
POST  /api/bookings
GET   /api/bookings
GET   /api/bookings/{id}
POST  /api/bookings/{id}/accept
POST  /api/bookings/{id}/decline
POST  /api/bookings/{id}/cancel
POST  /api/bookings/{id}/start
POST  /api/bookings/{id}/complete
POST  /api/bookings/{id}/rate
GET   /api/bookings/{id}/messages
POST  /api/bookings/{id}/messages
GET   /api/bookings/{id}/tracking
POST  /api/bookings/{id}/tracking/start
POST  /api/bookings/{id}/tracking/update
POST  /api/bookings/{id}/tracking/stop
```

### Reports
```
POST /api/reports
GET  /api/reports
GET  /api/reports/{id}
```

### Notifications
```
POST /api/notifications/register-device
```

### Admin (all require auth:sanctum + admin middleware)
```
GET   /api/admin/verifications
POST  /api/admin/verifications/{workerProfileId}/review
GET   /api/admin/users
GET   /api/admin/users/{id}
PATCH /api/admin/users/{id}/toggle-active
GET   /api/admin/bookings
GET   /api/admin/bookings/{id}
GET   /api/admin/reports
GET   /api/admin/reports/{id}
PATCH /api/admin/reports/{id}/resolve
GET   /api/admin/dashboard
```

---

## 9. Files of Note

```
app/Http/Middleware/EnsureClient.php
app/Http/Middleware/EnsureWorker.php
app/Http/Middleware/EnsureAdmin.php
app/Services/Booking/BookingService.php
app/Services/Booking/RatingService.php
app/Services/Message/MessageService.php
app/Services/Report/ReportService.php
app/Services/Notification/NotificationService.php
app/Services/Admin/AdminService.php
app/Policies/BookingPolicy.php
app/Policies/ReportPolicy.php
app/Events/LocationTrackingStarted.php
app/Events/LocationUpdated.php
app/Events/LocationTrackingStopped.php
app/Events/NewMessage.php
routes/api.php
routes/channels.php
bootstrap/app.php  (withBroadcasting() + channels param added)
```

---

## 10. Test Suite State

**Last full run:** 198 passed, 701 assertions, 0 failures

| Test File | Tests |
|---|---|
| Auth/ForgotPasswordTest | 7 |
| Auth/ResendOtpTest | 6 |
| Auth/ResetPasswordTest | 11 |
| Auth/VerifyResetOtpTest | 8 |
| Booking/BookingListTest | 9 |
| Booking/BookingStatusTest | 10 |
| Booking/CreateBookingTest | 8 |
| Booking/RatingTest | 16 |
| Message/MessageTest | 15 |
| Notification/NotificationTest | 14 |
| Report/ReportTest | 20 |
| Tracking/TrackingTest | 17 |
| Admin/AdminTest | 30 |
| Worker/SubmitVerificationTest | 8 |
| Worker/UpdateWorkerProfileTest | 5 |
| Worker/VerificationStatusTest | 3 |
| Worker/WorkerProfilePublicTest | 6 |
| Worker/WorkerSearchTest | 7 |
| Unit/ExampleTest | 1 |
| Feature/ExampleTest | 1 |

Run with: `vendor\bin\pest`

---

## 11. Completed Phases

| Phase | Feature | Status |
|---|---|---|
| 1 | Auth (register, OTP, login, Google, password reset) | ✅ |
| 2 | Migrations, seeders, models | ✅ |
| 3 | Worker verification, profile, search | ✅ |
| 4 | Booking CRUD + Ratings & Reviews | ✅ |
| 5 | Reports & Disputes | ✅ |
| 6 | Messages | ✅ |
| 7 | Soketi broadcasting + FCM push notifications | ✅ |
| 8 | Admin API endpoints | ✅ |
| 9 | Deployment | ⬜ |

---

## 12. Known Constraints & Decisions

- **No Postman collection** has been generated yet. All endpoints
  have been verified via PEST only. Manual Postman verification
  of edge cases (file uploads, FCM, Soketi live events) is still
  pending per the checklists logged during each phase.
- **FCM is synchronous** — not queued. Acceptable for MVP; queue
  it before production if notification volume grows.
- **Admin has no report bypass on ReportPolicy** — admins use
  their own `AdminReportController`, not the user-facing
  `ReportController`. This is intentional.
- **Soft-deleted bookings** return 404 from `Booking::find()`
  (which respects soft deletes) in MessageController and
  TrackingController — consistent with the rest of the app.
- **messages.content vs message key** — DB column is `content`,
  API request/response key is `message`. Do not rename the column.
- **booking_tracking.tracked_role** — DB column is `tracked_role`,
  broadcast payload key is `role`. Do not rename the column.
- **web dev is using json-server mock** — the React admin panel
  is not yet connected to this API. No frontend integration
  testing has been done.

---

## 13. Next Step

**Phase 9 — Deployment**

Steps expected:
1. Set up production server (likely shared hosting or VPS)
2. Configure production `.env`
   (APP_ENV=production, APP_DEBUG=false, real DB credentials,
   real Brevo SMTP, real Firebase credentials, real Soketi or
   substitute)
3. Run `php artisan migrate --force` on production DB
4. Run `php artisan db:seed --force` (barangays + categories)
5. Run `php artisan storage:link`
6. Configure web server (Apache/Nginx) to point to `/public`
7. Set up Soketi on the server (or evaluate a hosted alternative)
8. Generate Postman collection covering all endpoints
9. Share API base URL with web dev and app dev teammates

Deployment guide reference: `06_DEPLOYMENT_GUIDE.md`
(already written, not yet executed)
```
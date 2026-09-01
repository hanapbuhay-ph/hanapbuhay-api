# HanapBuhay — Amazon Q Guide (Laravel Backend)
**For: Laravel Developer (PM)**

---

> This guide shows how to use Amazon Q effectively
> to build the HanapBuhay Laravel backend, based on
> the specs in 02_DATABASE_SCHEMA.md and
> 03_API_ENDPOINTS.md. Amazon Q works best when you
> give it small, specific, well-scoped prompts —
> not "build the whole backend" in one shot.

---

## General Rules for Prompting Amazon Q

```
1. One migration/model/controller at a time.
   Never ask for multiple unrelated features
   in a single prompt.

2. Always paste the exact schema from
   02_DATABASE_SCHEMA.md into your prompt.
   Don't let Amazon Q guess column names.

3. Always specify Laravel version (10.x) and
   that Sanctum is used for auth — Amazon Q
   sometimes defaults to Passport or session auth.

4. After Amazon Q generates code, always run:
   php artisan migrate (for migrations)
   php artisan route:list (for new routes)
   to verify before moving to the next prompt.

5. If Amazon Q's output doesn't match the
   response format convention in
   03_API_ENDPOINTS.md, paste the convention
   into the prompt explicitly.

6. Commit after each working feature.
   Don't let uncommitted Amazon Q output pile up —
   if something breaks, you want a clean rollback point.
```

---

## Phase 1 — Foundation

### Prompt: Barangays Migration + Seeder
```
Create a 3 migration for a "barangays" table
with these columns: id, name (string), latitude
(decimal 10,7), longitude (decimal 10,7), is_active
(boolean default true), timestamps.

Also create a BarangaySeeder that inserts these 31
Trinidad, Bohol barangays: [paste barangay array
from 02_DATABASE_SCHEMA.md]

Register the seeder in DatabaseSeeder.php.
```

### Prompt: Users Migration
```
Create a Laravel 13 migration to modify the default
"users" table to add: mobile_number (string nullable),
role (enum: client, worker, admin — default client),
profile_photo_path (string nullable), barangay_id
(foreign id nullable, references barangays, null on
delete), google_id (string nullable unique),
is_active (boolean default true), is_google_account
(boolean default false), soft deletes.

Keep existing name, email, password, email_verified_at,
remember_token, timestamps columns.
```

### Prompt: Auth Endpoints (Register + OTP)
```
Using Laravel 13 and Laravel Sanctum, create:
1. A RegisterController with a register() method that
   validates: name, email (unique), password (confirmed),
   mobile_number, role (client or worker), barangay_id
   (must exist in barangays table). Create the user,
   generate a 6-digit OTP, save it to an "otp_codes"
   table (email, code, type=email_verification,
   expires_at = now + 10 minutes), and send it via
   Laravel Mail.
2. An EmailVerificationController with verify() that
   checks the OTP against otp_codes, marks
   email_verified_at on the user, marks the OTP as
   used, and returns a Sanctum token.

Use this exact JSON response format for all responses:
[paste response format convention from
03_API_ENDPOINTS.md]

Routes should match: POST /api/auth/register,
POST /api/auth/verify-otp
```

### Prompt: Login + Google OAuth
```
Using Laravel 13, Sanctum, and Laravel Socialite,
create:
1. LoginController@login — validates email/password,
   checks email_verified_at is not null (else 403),
   returns Sanctum token.
2. GoogleAuthController with redirect() and callback()
   methods. On callback, find user by google_id or
   email. If new, create user with role=client,
   is_google_account=true, email_verified_at=now()
   (Google already verifies email), password=null.
   Return Sanctum token.

Match routes from 03_API_ENDPOINTS.md section 1.
```

---

## Phase 2 — Core Features

### Prompt: Worker Profile + Verification
```
Using the worker_profiles and verification_documents
schema from 02_DATABASE_SCHEMA.md, create:
1. WorkerProfile model with relationships to User,
   VerificationDocument, and ServiceCategory
   (many-to-many through worker_service_categories).
2. VerificationController@submit — accepts multipart
   form data for government_id, barangay_certificate,
   selfie_with_id (required) and skill_certificate
   (optional) files. Store via Laravel Storage in
   storage/app/public/verifications/{user_id}/.
   Save file paths to verification_documents table.
   Set worker_profiles.verification_status = 'pending'.

Match the route: POST /api/worker/verification/submit
```

### Prompt: Worker Search with Haversine Distance
```
Create a WorkerController@index method for
GET /api/workers that:
1. Only returns workers where verification_status
   = 'approved'
2. Accepts optional query params: barangay_id,
   category_id, min_rating
3. For each worker, computes distance_km using the
   Haversine formula between the authenticated
   client's barangay coordinates and the worker's
   barangay coordinates
4. Uses this exact DistanceHelper class:
   [paste DistanceHelper code from
   02_DATABASE_SCHEMA.md]
5. Returns paginated JSON matching the response
   format in 03_API_ENDPOINTS.md, including
   distance_km and distance_label fields
```

### Prompt: Booking CRUD
```
Using the bookings table schema from
02_DATABASE_SCHEMA.md, create a BookingController
with these methods, matching the routes and behavior
described in 03_API_ENDPOINTS.md section 6:
- store() — create booking, auto-generate booking_code
  in format HB-YYYY-XXXXX using the boot() logic from
  02_DATABASE_SCHEMA.md
- index() — list bookings scoped to the authenticated
  user (as client or worker)
- show()
- accept() — worker only, pending → accepted
- decline() — worker only, pending → declined
- cancel() — client or worker, any active status → cancelled
- start() — worker only, accepted → active
- complete() — worker only, active → completed,
  also set is_client_tracking and is_worker_tracking
  to false

Use policy-based authorization so only the client_id
or worker_id on the booking can access it.
```

---

## Phase 3 — Real-Time

### Prompt: Soketi Broadcasting Events
```
Using 3 broadcasting (configured for
Soketi/Pusher protocol), create these broadcast
events:
1. LocationTrackingStarted(bookingId, role)
2. LocationUpdated(bookingId, role, latitude, longitude)
3. LocationTrackingStopped(bookingId, role)

Each should broadcast on channel
"private-booking.{bookingId}".

Also create a TrackingController with:
- start() — POST /api/bookings/{id}/tracking/start,
  sets is_client_tracking or is_worker_tracking = true
  on the booking, fires LocationTrackingStarted
- update() — POST /api/bookings/{id}/tracking/update,
  inserts a row into booking_tracking, fires
  LocationUpdated (does NOT wait for DB write to
  broadcast — broadcast first, then insert)
- stop() — POST /api/bookings/{id}/tracking/stop,
  sets tracking flag to false, fires
  LocationTrackingStopped
- show() — GET /api/bookings/{id}/tracking, returns
  current tracking state plus both barangay
  coordinates for initial map render

Add channel authorization in routes/channels.php:
only the client_id or worker_id on that booking
can listen to private-booking.{id}.
```

### Prompt: Push Notifications (FCM)
```
Using kreait/laravel-firebase package, create a
NotificationService class with a method
sendPush(User $user, string $title, string $body,
array $data = []) that sends an FCM push notification
to all registered device tokens for that user.

Also create:
1. Migration for a "device_tokens" table: id,
   user_id (foreign), fcm_token (string), device_type
   (enum: android, ios, web), timestamps, unique on
   user_id + fcm_token
2. NotificationController@registerDevice for
   POST /api/notifications/register-device

Call NotificationService::sendPush() from the booking
accept/decline/complete methods and verification
approve/reject methods (I'll tell you exactly where
to hook it in for each).
```

---

## Phase 4 — Supporting Features

### Prompt: Ratings
```
Using ratings_reviews schema from
02_DATABASE_SCHEMA.md, create RatingController@store
for POST /api/bookings/{id}/rating. Validate score
1-5, unique per booking_id + rated_by (auth()->id()).
Only allow if booking status = 'completed' and
auth user is client_id or worker_id on it. After
saving, if the rated user has role=worker, recalculate
their worker_profiles.average_rating and
total_reviews from all their ratings_reviews rows.
```

### Prompt: Reports + Messages
```
Create two controllers matching
03_API_ENDPOINTS.md sections 9 and 10:
1. ReportController@store, @mine — using the reports
   schema, accepts evidence_paths as uploaded files
   (store in storage/app/public/reports/)
2. MessageController@index, @store, @markRead — using
   the messages schema, broadcasts NewMessage event
   on private-booking.{id} channel on store()
```

---

## Common Amazon Q Gotchas on This Project

```
1. Amazon Q may default to Laravel Breeze/Jetstream
   auth scaffolding — explicitly say "API only, no
   Blade views, Sanctum tokens only" in every auth
   prompt.
   Amazon Q may generate Laravel 10 syntax when
   prompted — always specify "Laravel 13, PHP 8.5"
   at the top of every prompt to prevent this.
   Laravel 11 has a simplified folder structure
   (no Http/Kernel.php, middleware registered
   differently) — if Amazon Q generates a
   Kernel.php file, reject it and ask again
   specifying Laravel 13.

2. Amazon Q may forget the response format convention
   between prompts — paste it again each time if
   output drifts.

3. Amazon Q may suggest Pusher's actual cloud service
   instead of Soketi — clarify "self-hosted Soketi,
   Pusher-compatible protocol, not Pusher cloud" if
   it starts asking for real Pusher API keys.

4. Amazon Q may not know about the custom
   "hanapbuhay_notifications" table name (renamed to
   avoid conflict with Laravel's built-in notifications
   table) — always specify it explicitly.

5. For file uploads, always specify
   "storage/app/public" + "php artisan storage:link"
   so Amazon Q doesn't put files somewhere
   inaccessible via URL.

6. Amazon Q sometimes writes migrations with
   $table->foreign('x_id')->references('id')->on('y')
   instead of the shorthand
   $table->foreignId('x_id')->constrained('y') —
   either works, but keep it consistent with
   02_DATABASE_SCHEMA.md style throughout the project.
```

---

## Suggested Prompt Order (Checklist)

```
[ ] Barangays migration + seeder
[ ] Users table modification migration
[ ] Register + OTP verification
[ ] Login + Google OAuth
[ ] Worker profile + verification submission
[ ] Worker search + Haversine distance
[ ] Booking CRUD (create, accept, decline, cancel,
    start, complete)
[ ] Soketi broadcast events + TrackingController
[ ] FCM push notification service
[ ] Ratings
[ ] Reports
[ ] Messages
[ ] Admin endpoints (verifications, users, bookings,
    reports, dashboard stats)
[ ] Postman collection covering every endpoint in
    03_API_ENDPOINTS.md
```
```
## Standing Instructions — Paste This Before Any Coding Prompt

> Paste this once at the start of a new Amazon Q session
> (or repeat it if code quality drifts mid-session).
> This keeps every generated file consistent, as if
> written by one disciplined senior developer —
> not a tutorial or a quick prototype.

---

### Prompt: Set the Standard

```
Act as a senior Laravel backend developer. For all
code you generate in this session, follow these
standards strictly:

CODE STYLE
- Follow PSR-12 coding standard
- Use camelCase for variables/methods, snake_case
  for database columns (Laravel convention)
- Type-hint all method parameters and return types
  wherever Laravel/PHP allows it
- No inline HTML/mixed logic — controllers stay thin,
  business logic goes in Service classes or Model
  scopes/methods when it grows beyond a few lines

VALIDATION
- Every controller method that accepts input uses
  Form Request classes (php artisan make:request),
  not inline $request->validate() for anything beyond
  a couple of trivial fields
- Validation messages should be clear and specific,
  not just "required"

ERROR HANDLING
- Wrap risky operations (file uploads, external API
  calls like Brevo/FCM/Google) in try/catch
- Return consistent JSON error responses matching
  the format in 03_API_ENDPOINTS.md — never let a
  raw Laravel exception/stack trace leak to the client
- Use proper HTTP status codes (422 validation,
  401 unauthenticated, 403 forbidden, 404 not found,
  500 server error) — not everything as 200 with an
  error flag in the body

AUTHORIZATION
- Use Laravel Policies for resource-level checks
  (e.g. "is this user allowed to view/modify this
  booking") — not manual if-checks scattered in
  controllers
- Use middleware for role-based route protection
  (client/worker/admin), not repeated checks inside
  every method

DATABASE
- Always use Eloquent relationships, not raw joins,
  unless there's a clear performance reason
- Use database transactions (DB::transaction) for
  any operation that writes to multiple tables
  together (e.g. creating a booking + notification)
- Never run queries inside a loop (N+1 problem) —
  use eager loading (::with())

NAMING & STRUCTURE
- Match table/column names exactly as defined in
  02_DATABASE_SCHEMA.md — do not rename or "improve"
  them
- Match route names/paths exactly as defined in
  03_API_ENDPOINTS.md
- One responsibility per class — if a controller
  method exceeds ~25-30 lines, extract logic into
  a Service class

COMMENTS
- Only comment WHY, not WHAT (code should be
  self-explanatory for the "what")
- No leftover placeholder comments like
  "// TODO: add logic here" in final output —
  either implement it or explicitly tell me it's
  incomplete and why

TESTING MINDSET
- After generating a feature, list the edge cases
  I should manually test in Postman before moving on
  (don't just say "done" — flag what could break)

Confirm you understand these standards before I give
you the first actual feature prompt.
```

---

### Reinforcement Prompt (use if output starts drifting)

```
That last response didn't fully follow the standards
we set — specifically: [call out what's wrong, e.g.
"no Form Request class used" or "raw try/catch missing
on the file upload"]. Please regenerate following the
professional coding standards from earlier in this
session.
```

---

### Optional: Add a Code Review Pass

```
After Amazon Q generates a feature, before committing,
run one more prompt:

"Review the code you just wrote against the standards
we set (PSR-12, Form Requests, policies, transactions,
no N+1 queries, consistent error responses). List any
violations and fix them."

This catches drift Amazon Q sometimes introduces after
several prompts in a long session.
```
```

---


# HanapBuhay — Feature Specs
**For: Laravel Developer (PM) + Whole Team**

---

> This document describes every feature from a
> product/behavior standpoint — what the user sees,
> what triggers what, and edge cases to handle.
> Pair this with 02_DATABASE_SCHEMA.md (data) and
> 03_API_ENDPOINTS.md (routes) when building.

---

## 1. Registration & Authentication

### Manual Registration (Email + Password)
```
User fills: name, email, password, mobile number,
role (client or worker), barangay (dropdown of
Trinidad's 31 barangays)

→ Account created, unverified
→ 6-digit OTP sent to email (via Brevo)
→ User enters OTP on next screen
→ On success: email_verified_at set, logged in
  automatically, Sanctum token issued

Edge cases:
- Wrong OTP: show error, allow retry
- Expired OTP (>10 min): must tap "Resend"
- Email already registered: show clear error,
  suggest "Forgot Password" instead
```

### Google Sign-In
```
User taps "Continue with Google"
→ Google account picker
→ On success: account created (or logged in if
  google_id already exists) with role=client
  by default
→ No OTP screen (Google already verified email)
→ Still asked to select barangay + mobile number
  if this is a new account (one-time setup screen)

Edge case:
- Existing manual account with same email signs in
  via Google: link accounts (set google_id on
  existing user) rather than creating duplicate
```

### Role Selection
```
Role is chosen at registration and is NOT
changeable later in-app (would require re-verification
flow, out of scope for now). If a client wants to
become a worker, they contact support/admin manually
for now — future enhancement.
```

### Password Reset
```
"Forgot Password" → enter email → OTP sent
→ enter OTP + new password → done, must log in again
```

---

## 2. Worker Verification

```
New worker account starts as verification_status =
'unverified'. Worker cannot appear in search results
or receive bookings until 'approved'.

Worker Profile Setup screen (before submitting docs):
- Bio (text, optional but encouraged)
- Service categories (multi-select from
  service_categories table)

Submit Verification screen:
- Upload: Government ID (required)
- Upload: Barangay Certificate (required)
- Upload: Selfie holding the ID (required)
- Upload: Skill Certificate (optional, e.g. TESDA cert)
→ On submit: verification_status = 'pending'
→ Admin notified (in web panel)
→ Worker sees "Under Review" status screen,
  cannot resubmit while pending

Admin reviews (web panel):
→ Views all 4 uploaded documents
→ Approve: verification_status = 'approved',
  verified_by + verified_at set, push notification
  sent to worker
→ Reject: verification_status = 'rejected',
  remarks required (shown to worker), push
  notification sent, worker CAN resubmit
  (new documents overwrite old ones)

Trust tier (separate from verification_status):
- Set manually by admin after some jobs completed
- 'verified' → baseline after approval
- 'trusted' → admin-awarded after good track record
- 'flagged' → admin sets after a report is upheld
- 'revoked' → worker banned from platform
  (cannot log in / appear in search)
```

---

## 3. Worker Search & Discovery (Client)

```
Client opens "Find Workers" screen:
Filters available:
- Barangay (dropdown, optional — defaults to "All")
- Service category (dropdown, optional)
- Minimum rating (optional)

NO distance-based filtering — distance is
DISPLAY ONLY, shown as "~X.X km · Barangay Name"
on each worker card, computed via Haversine between
client's and worker's registered barangay.

Worker card shows:
- Photo, name, primary service category
- Star rating + review count
- Distance label
- Trust tier badge (if 'trusted', show a badge icon)
- "View Profile" tap target

Worker Profile Detail screen:
- Full bio
- All service categories offered
- Rating breakdown + review list (paginated)
- Completed jobs count
- "Request Booking" button

Edge case:
- Client has no barangay set (shouldn't happen,
  required at registration) → distance shows
  "Distance unavailable"
```

---

## 4. Booking Flow

```
Client taps "Request Booking" on worker profile:
Booking form (simple, per finalized decision):
- Service category (pre-filled if worker offers
  only one, else dropdown of worker's categories)
- Preferred date/time (scheduled_at)
- Notes (optional, free text — "may need own tools"
  type context)
→ Submit → booking created, status = 'pending'
→ Worker receives push notification + in-app
  notification

Worker sees booking request:
- Client name, barangay, service category,
  scheduled date/time, notes
- Accept / Decline buttons

If Accepted:
→ status = 'accepted'
→ Client notified
→ Map screen becomes available to both parties
  (see section 5)

If Declined:
→ status = 'declined'
→ Client notified
→ Optional reason shown if worker provided one
→ Client can browse other workers

Cancellation (either party, while pending or accepted):
→ Requires a reason (free text)
→ status = 'cancelled', cancelled_by recorded
→ Other party notified

Starting the Job (Worker only, once accepted):
→ Worker taps "Start Job" when work begins
→ status = 'active', started_at recorded
→ (This is independent of live tracking — tracking
  is about TRAVELING to the location, not the job
  itself being in progress)

Completing the Job (Worker only, once active):
→ Worker taps "Mark as Completed"
→ status = 'completed', completed_at recorded
→ Both tracking flags forced to false (safety net,
  in case someone forgot to tap "I've Arrived")
→ Client prompted to leave a rating
```

---

## 5. Map & Live Location Tracking

> Finalized design: map-driven, no upfront decision
> on who travels. Fully bidirectional.

```
Map screen becomes accessible once booking status
is 'accepted' or 'active'. Not available while
'pending', 'declined', 'cancelled'. Optionally still
viewable (read-only, no tracking buttons) after
'completed' for reference.

What the map always shows:
- Client's registered barangay pin (blue)
- Worker's registered barangay pin (green)
- Straight-line distance label between the two pins
- A button that toggles based on current state:
  "I'm on my way" (not tracking) ↔
  "I've Arrived" (currently tracking)

Starting tracking:
User taps "I'm on my way"
→ App requests GPS permission if not granted
→ Flutter starts streaming device GPS coordinates
  every few seconds via the tracking/update endpoint
→ Own pin becomes a "live" moving pin
→ Other party's map updates in real-time via Soketi
  WebSocket broadcast (no polling)
→ Other party sees a toast/notification:
  "[Name] is on the way"

Stopping tracking:
User taps "I've Arrived"
→ GPS streaming stops
→ Pin freezes at last known location
→ Other party notified: "[Name] has arrived"

Both can be tracking simultaneously:
- If both are traveling toward each other (meeting
  in between), both pins move live at once — no
  conflict, they're independent flags
  (is_client_tracking / is_worker_tracking)

Destination pin logic:
- Always the OTHER party's registered barangay
  center — never changes mid-tracking, even though
  it's just an approximate area (not their exact
  house). This is intentional for privacy — exact
  home address is never shown, only barangay-level.

Safety/edge cases:
- If app is killed/crashes while tracking: tracking
  flag stays true until manually stopped or booking
  is completed (which force-stops both).
  Consider a stale-tracking timeout later
  (e.g. auto-stop if no update in 15 min) —
  flagged as a future enhancement, not required
  for capstone submission.
- GPS permission denied: show explanation, cannot
  tap "I'm on my way" until granted.
```

---

## 6. Ratings & Reviews

```
Available only after booking status = 'completed'.
Both client and worker are prompted to rate each
other (two separate rating actions, each one-directional).

Rating screen:
- 1-5 stars (required)
- Comment (optional, free text)
→ One rating per person per booking (enforced by
  unique constraint on booking_id + rated_by)

When a worker is rated:
→ worker_profiles.average_rating recalculated
  (mean of all their ratings_reviews scores)
→ total_reviews incremented

Reviews are visible on the worker's public profile
(client ratings of workers are shown; worker ratings
of clients are NOT shown publicly — used internally/
by admin only, to protect clients from public rating
exposure, since workers browsing clients isn't a
search feature in this app).
```

---

## 7. Reports & Disputes

```
Either party can file a report against the other,
tied to a specific booking (or standalone if about
general conduct, booking_id nullable).

Report form:
- Reason (dropdown: no_show, unsatisfactory_work,
  misconduct, non_payment, unsafe_environment,
  abusive_behavior, false_information, other)
- Description (required, free text)
- Evidence photos (optional, multiple)
→ status = 'under_review'
→ Admin notified

Admin reviews (web panel):
- Sees full report + evidence + booking context
  (if tied to one) + both users' profiles
- Resolves with: warning_issued, account_suspended,
  verification_revoked, or no_action
- Remarks required
- Action taken automatically applies:
  - account_suspended → is_active = false
  - verification_revoked → trust_tier = 'revoked'
    (worker only)
- Both parties see resolution outcome
  (not full admin remarks, just a summary)
- Logged to admin_audit_logs
```

---

## 8. In-App Messaging

```
Chat is scoped per booking — no general/global
messaging between users who don't have a booking
together (prevents spam/misuse).

Available once booking status = 'accepted'
through 'completed' (locked after, read-only).

Message features:
- Text messages
- Single file/image attachment per message
- Read receipts (is_read, read_at)
- Real-time delivery via Soketi
  (NewMessage event on private-booking.{id})

Notification badge shows unread count per booking
and a total unread count in the app's nav bar.
```

---

## 9. Notifications

```
Two channels, both used together:
1. In-app notification feed (hanapbuhay_notifications
   table) — always created, viewable in a
   notifications screen
2. Push notification (FCM) — sent alongside, best-
   effort (device may not have granted permission)

Notification types trigger from these events:
- booking_request        → worker
- booking_accepted       → client
- booking_declined       → client
- booking_completed      → client (prompts rating)
- booking_cancelled      → other party
- verification_approved  → worker
- verification_rejected  → worker
- verification_resubmit  → worker (after rejection,
  reminder if not resubmitted in X days — optional)
- new_message             → other party in booking
- new_rating              → rated user
- report_resolved         → both parties involved
- system_announcement     → all users (admin broadcast,
  future enhancement)
- trust_tier_updated      → worker
```

---

## 10. Admin Web Panel (React)

```
Admin Dashboard (landing page):
- Total users (clients / workers breakdown)
- Pending verifications count (badge/alert)
- Active bookings count
- Completed bookings this month
- Open reports count (badge/alert)

Verification Queue:
- List of pending worker_profiles
- Click into one → view all 4 documents,
  approve/reject with remarks

User Management:
- Searchable/filterable table (role, active status)
- Suspend / reactivate action per user
- View user's booking history

Booking Oversight:
- Read-only view of all bookings, filterable by
  status and date range
- Used for monitoring/support, not for admin to
  modify bookings directly

Reports Queue:
- List of under_review reports
- Click into one → full context, resolve action

Audit Log:
- Read-only table of admin_audit_logs, filterable
  by admin, action type, date
```

---

## Out of Scope (Explicitly Excluded)

```
- Payment processing / in-app wallet
  (all transactions handled offline/cash)
- Automated government ID verification
  (admin manual review only)
- In-app scheduling calendar/availability grid
  (worker just sets a simple available/busy/offline
  status, no time-slot booking calendar)
- Multi-language support beyond Taglish-friendly
  error messages (no full i18n system)
- Worker role switching after registration
- Automated stale-tracking timeout
  (flagged as future enhancement)
- Real government barangay boundary polygon
  (using single center-point coordinates only)
```
```

---

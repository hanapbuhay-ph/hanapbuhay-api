# HanapBuhay — App Functionalities **Document Type:** Mobile App Feature Reference **Platform:** Flutter (Android + iOS) **Audience:** App Developer, Project Manager, QA Tester **Last Updated:** September 2026

---

## Overview

The HanapBuhay mobile app is a single Flutter application with two role-based experiences: Client and Worker. After login, the app detects the user's role and renders the appropriate navigation, screens, and features.

Think of it like Upwork — one app, different experience depending on whether you are hiring or offering services.

The app connects to the Laravel 13 backend API for all data. No data is stored permanently on the device except the authentication token and user preferences.

---

## App Entry Flow

```
App opens
    │
    ▼
Splash Screen (2 seconds)
    │
    ├── First time user
    │       ▼
    │   Onboarding Slides (4 slides)
    │       ▼
    │   Login Screen
    │
    └── Returning user (token stored)
            ▼
        Role-appropriate Home Screen
```

---

## SECTION A: SHARED FUNCTIONALITIES
## (Used by Both Client and Worker)

---

### A1. Onboarding & Authentication

#### A1.1 Onboarding Slides
- 4 swipeable slides shown only on first launch
- Slides introduce the app concept:
  - Slide 1: What is HanapBuhay?
  - Slide 2: Find the Right Worker (Client angle)
  - Slide 3: Offer Your Skills (Worker angle)
  - Slide 4: Safe & Verified (trust angle)
- Skip button available on slides 1-3
- Get Started button on slide 4
- Pagination dots show current slide position
- After completion, hasSeenOnboarding = true saved to SharedPreferences (never shown again)

#### A1.2 Role Selection Screen
- First step of registration
- Two selectable cards:
  - "I want to hire" → Client
  - "I want to work" → Worker
- Only one can be selected at a time
- Selected card shows green border + checkmark
- Continue button disabled until a role is chosen

#### A1.3 Manual Registration
- Form fields:
  - Full Name (required)
  - Email Address (required, unique)
  - Password (required, min 8 characters)
  - Confirm Password (required, must match)
  - Mobile Number (required, Philippine format, collected for coordination — NOT OTP verified)
  - Barangay (required, dropdown of 20 Trinidad, Bohol barangays)
- Role badge shown at top (e.g. "Signing up as: Client") with "Change" link back to role selection
- Terms & Privacy checkbox required before submit
- On success: goes to Email Verification Screen
- Inline validation errors shown under each field

#### A1.4 Google Sign-In Registration
- "Continue with Google" button on Login Screen
- Google account picker opens
- On success (new user):
  - Goes to Role Selection Screen
  - Then Complete Your Profile Screen
- On success (existing user):
  - Goes directly to Home Screen
- No OTP verification required (Google already verified the email)

#### A1.5 Complete Your Profile Screen (Google Sign-In new users only)
- Pre-filled from Google:
  - Profile photo (editable, tap to change)
  - Full Name (editable)
  - Email (non-editable, greyed out, "Provided by Google" label shown)
- User must fill in:
  - Mobile Number (Philippine format)
  - Barangay (dropdown, required)
- Continue button → goes to Home Screen
- No OTP step after this screen

#### A1.6 Email Verification Screen (Manual registration only)
- 6 individual OTP input boxes
- Instructional text: "We sent a verification code to your email address [email]"
- NOT sent via SMS — email only
- Countdown timer (60 seconds)
- Resend Code link (active after timer ends)
- Verify button
- On success → goes to Home Screen

#### A1.7 Login Screen
- "Continue with Google" button (placed ABOVE the manual form)
- "or" divider with lines on both sides
- Manual login form (white card):
  - Email Address field
  - Password field (show/hide toggle)
  - "Forgot Password?" link (ONLY under manual form, NOT near Google button)
  - Log In button
- "Don't have an account? Sign Up" link at bottom

#### A1.8 Forgot Password Screen
- Three internal states:
  - State 1: Enter email → Send Reset Code
  - State 2: Enter 6-digit email OTP → Verify
  - State 3: Enter new password + confirm → Reset
- Code sent via email (not SMS)
- On success → redirects to Login Screen

#### A1.9 Security Settings Screen (Accessed from Profile tab)
- Change Password:
  - Current password + new password + confirm
  - Google accounts: "Set a Password" instead (adds password as backup login method)
- Linked Accounts:
  - Shows sign-in method
  - "Signed in with Google: [email]" if applicable
- Two-Factor Authentication (2FA):
  - Toggle to enable/disable
  - When enabled, email OTP required on login
- View Login Activity:
  - List of recent logins (date, time, device)
  - "Log out this device" action per session

---

### A2. Profile Management

#### A2.1 View Profile Tab
- Entry point to all account-related screens
- Shows:
  - Profile photo
  - Full name
  - Role badge (Client / Worker)
  - Verification badge (workers only)
- List rows:
  - Edit Profile
  - Security Settings
  - Notification Preferences
  - Help / FAQ
  - Log Out
- Worker-only additional rows:
  - Verification Status
  - Manage Job Posts
  - Portfolio & Skills

#### A2.2 Edit Profile Screen
- Editable fields:
  - Profile photo (tap to change, image picker)
  - Full Name
  - Mobile Number
  - Barangay (dropdown)
- Email is non-editable (shown greyed out for reference)
- Save Changes button
- Changes reflected immediately across app

#### A2.3 Notification Preferences
- Toggle switches per notification type:
  - Booking updates (on/off)
  - Messages (on/off)
  - Verification updates (on/off, workers only)
  - Platform announcements (on/off)
- Toggle switches per delivery channel:
  - In-app notifications (always on)
  - Push notifications (on/off)

---

### A3. In-App Messaging

#### A3.1 Chat Inbox (Bottom nav tab: Messages)
- List of all conversations
- Each conversation row shows:
  - Other party's photo and name
  - Last message preview
  - Timestamp of last message
  - Unread message count badge
  - Linked booking reference (e.g. "Re: Booking #HB-2026-00001")
- Conversations sorted by most recent
- Total unread count shown on Messages tab badge in bottom nav

#### A3.2 Chat Thread Screen
- Full conversation for a specific booking
- Shows booking reference card at top (booking code, service, status)
- Message bubbles:
  - Own messages: right-aligned, green
  - Other party: left-aligned, white card
  - Timestamps shown per message
  - Read receipts (single tick = sent, double tick = read)
- Input bar at bottom:
  - Text input field
  - Attach photo button
  - Send button
- Real-time delivery via WebSocket (Laravel Echo + Soketi)
- Chat is tied to a specific booking (no general messaging between users without a booking context)
- Available from booking accepted through completed (read-only after)

---

### A4. Notifications

#### A4.1 Notification Center (Bottom nav tab: Notifications)
- Chronological list of all notifications
- Grouped by: Today / Earlier
- Each notification row:
  - Icon (type-specific)
  - Title and body text
  - Timestamp
  - Unread indicator dot
  - Tap routes to relevant screen (e.g. booking notification → Booking Detail)
- "Mark all as read" action

#### A4.2 Push Notifications
- Delivered via Firebase Cloud Messaging (FCM)
- Works when app is in background or closed
- Tapping a push notification deep-links to the relevant screen inside the app
- FCM token registered/refreshed on app start via POST /api/user/fcm-token

---

### A5. Help & Support

#### A5.1 Help / FAQ Screen
- Search bar at top
- Expandable accordion FAQ items grouped by topic:
  - Account & Registration
  - Bookings
  - Verification (workers)
  - Rates & Payment
  - Safety & Trust
  - Technical Issues
- "Contact Support" button at bottom (opens email link or simple contact form)

---

## SECTION B: CLIENT FUNCTIONALITIES

---

### B1. Home Feed

#### B1.1 Client Home / Feed Screen (Bottom nav tab: Home)
- Greeting at top: "Hi, [Name]!" with notification bell icon
- Search bar (tappable → Worker Search screen)
- Quick Filter chips (upper right of feed): [ All ] [ Verified ] [ Unverified ]
  - All: shows all active job posts
  - Verified: shows only barangay-verified workers
  - Unverified: shows only unverified workers
  - Active chip highlighted in green
- Advanced Filters button (⚙️ icon): Opens bottom sheet with:
  - Service Category (multi-select chips)
  - Barangay (dropdown, 20 Trinidad barangays)
  - Rate Type (All / Hourly / Daily / Weekly / Monthly / Per Session / Per Project)
  - Availability (All / Available Now)
  - Apply Filters + Reset buttons
- Scrollable feed of job post cards below
- Feed sort order:
  1. Distance (nearest first)
  2. Verification tier (trusted → verified → unverified)
  3. Rating (higher rated first within same tier)

#### B1.2 Job Post Feed Card Each card in the feed shows:
- Worker profile photo (circular)
- Worker name
- Trust/verification badge: 🛡️ Barangay Verified (green) ⭐ Trusted (blue) ⚠️ Unverified (amber)
- Job post title (e.g. "Expert Aircon Cleaning & Repair")
- Service category tag
- Starting rate (e.g. "From ₱300/session")
- Barangay + distance (e.g. "Poblacion · ~1.2 km")
- First uploaded post image as a visual preview, when available
- Image count indicator when the post has multiple images
- Short description preview (2 lines max)
- Availability indicator (Available Now / Busy)
- Tapping the post content → Full Job Post Detail Screen
- Tapping the worker photo or name → Worker Profile Screen

#### B1.3 Full Job Post Detail Screen
- Opens from a client home feed card
- Shows the worker header, profile photo, name, trust badge, barangay, and distance
- Shows the complete post title, category, description, rate, and availability
- Shows all uploaded post images in a vertically scrolling ListView
- Images are displayed in their saved display order
- Includes a Book button that opens the booking request form pre-filled with this post
- Worker identity remains tappable and opens the Worker Profile Screen
- Loading, empty-image, not-found, and error states are required

---

### B2. Worker Discovery

#### B2.1 Browse by Category Screen (Accessed from search bar or dedicated tab)
- Client selects a service category from a grid of category cards
- After selecting → shows list of all workers offering that category
- Worker list card shows:
  - Profile photo, name, verification badge
  - Rating + review count
  - Barangay + distance
  - Starting rate for that category
- Tap worker card → Worker Profile Screen
- Filter options:
  - Barangay dropdown
  - Verified only toggle
  - Availability toggle

#### B2.2 Worker Profile View Screen
- Header area:
  - Profile photo (large)
  - Worker name
  - Verification badge + trust tier label
  - Star rating + review count
  - Barangay + distance
  - Completed jobs count
- Tab row: About | Posts | Reviews
- About tab:
  - Bio text
  - Availability status indicator (Available Now / Busy / Offline)
- Posts tab:
  - All of this worker's active job posts
  - Each post shows: title, category, rate, description, "Book" button
- Reviews tab:
  - Paginated list of reviews from past clients
  - Reviewer name, star score, comment, date
- If worker is unverified:
  - Amber warning banner visible at top: "⚠️ This worker is not yet barangay-verified"

---

### B3. Booking

#### B3.1 Send Booking Request Screen
- Reached by tapping "Book" on a job post inside the Worker Profile Screen
- Pre-filled from job post:
  - Worker summary card (photo, name)
  - Service category (non-editable)
  - Starting rate shown as reference (e.g. "Starting from ₱300/session")
- Client fills in:
  - Preferred date (date picker)
  - Preferred time (time picker)
  - Notes / description (e.g. "2 aircon units, 1HP each")
- If worker is unverified:
  - Warning modal appears BEFORE the form: "⚠️ Book Unverified Worker? This worker has not submitted barangay verification documents. HanapBuhay cannot guarantee their identity at this time."
  - Two buttons: "Proceed Anyway" or "Find Verified Worker"
  - Only shows booking form if client taps "Proceed Anyway"
- Send Request button at bottom
- On success → Booking History Screen with new booking showing status: Pending

#### B3.2 Booking History Screen (Bottom nav tab: Bookings)
- Segmented tabs: Upcoming | Ongoing | Completed | Cancelled
- Each tab shows filtered list of bookings
- Booking card shows:
  - Worker photo + name
  - Service category
  - Scheduled date and time
  - Status badge (color-coded)
  - Booking code (e.g. HB-2026-00001)
- Tap any card → Booking Detail Screen

#### B3.3 Booking Detail Screen
- Worker summary card:
  - Photo, name, verification badge
  - "Message" icon button → Chat Thread
- Booking information block:
  - Service category
  - Scheduled date and time
  - Notes
  - Starting rate from job post
  - Booking code
- Status timeline / stepper: Pending → Accepted → Active → Completed
- Map button (visible when status is Accepted or Active): Opens Map Screen (see B3.5)
- Conditional action buttons by status:
  - Pending: "Cancel Request" button
  - Accepted: Map button visible
  - Active: "Confirm Job Completed" button (appears once worker marks started)
  - Completed: "Rate & Review" button (if not yet reviewed)
  - Completed + reviewed: shows submitted review
- "File a Report" link (small, secondary, visible on all active status bookings)

#### B3.4 Cancel Booking
- Available while booking is Pending or Accepted
- Requires cancellation reason (free text)
- Confirmation dialog before cancelling
- Worker notified of cancellation
- Status updated to Cancelled

#### B3.5 Map Screen (Accessed from Booking Detail, status = Accepted or Active)
- Full-screen Google Maps view
- Always shows both pins:
  - Blue pin: Client's registered barangay center
  - Green pin: Worker's registered barangay center
- Distance label between pins
- Bottom card overlay:
  - Worker photo and name
  - "I'm on my way" button (starts client's GPS tracking)
  - "I've Arrived" button (stops client's GPS tracking, shown only when client is tracking)
- If worker is tracking (worker tapped "I'm on my way" on their side):
  - Green pin becomes animated/moving
  - Shows worker's real-time GPS location
  - Updates live via WebSocket
- If client is tracking:
  - Blue pin becomes animated/moving
  - Shows client's real-time GPS location
  - Worker sees this on their map
- Both can track simultaneously
- Tracking is fully user-initiated (no automatic GPS)

#### B3.6 Confirm Job Completion
- Button appears on Booking Detail when worker has marked job as Started
- Client taps "Confirm Job Completed"
- Confirmation dialog
- On confirm:
  - Booking status → Completed
  - Both tracking flags forced to false
  - Client prompted to Rate & Review

---

### B4. Ratings & Reviews

#### B4.1 Rate & Review Screen
- Appears after booking is Completed
- Shows worker photo and name
- 5-star rating selector (large, tappable stars)
- Written review text area (optional)
- Submit Review button
- One review per client per booking (cannot edit after submission)
- On submit:
  - Worker's average rating recalculated
  - Worker notified of new review
  - Returns to Booking Detail Screen showing submitted review

#### B4.2 View Own Submitted Reviews
- Accessible from Profile tab
- List of all reviews client has given
- Each row: worker name, star score, comment, date, booking reference

---

### B5. Reports

#### B5.1 File a Report Screen
- Accessible from Booking Detail ("File a Report" link)
- Form fields:
  - Booking reference (auto-filled, non-editable)
  - Reason (dropdown): No-show, Unsatisfactory Work, Misconduct, Non-payment, Unsafe Environment, Abusive Behavior, False Information, Other
  - Description (required, free text)
  - Attach Photo Evidence button (multiple photos, shows thumbnails)
- Submit Report button
- On submit → Report Status Screen

#### B5.2 Report Status Screen
- List of all reports client has filed
- Each row:
  - Short description
  - Date filed
  - Status badge: Under Review (amber) / Resolved (green) / Dismissed (gray)
  - Tap to expand:
    - Admin remarks / resolution notes
    - Resolution action taken

---

## SECTION C: WORKER FUNCTIONALITIES

---

### C1. Worker Home / Dashboard

#### C1.1 Worker Home Screen (Bottom nav tab: Home)
- Greeting at top: "Hi, [Name]!" with notification bell icon
- Verification banner (if not verified): Amber banner at top: "Your account isn't verified yet. Verified workers get more bookings." "Complete Verification" button → Verification Submission Screen
- Availability toggle card: Large prominent switch: [ Available ] [ Busy ] [ Offline ] Updates worker's status across the platform immediately (visible on feed cards to clients)
- "Your Active Posts" section: List of worker's current job posts Each post row shows:
  - Category + title
  - Rate
  - Availability toggle per post
  - "Edit" button → Edit Job Post Screen
  - "Deactivate" action (swipe or menu)
- Incoming Booking Requests section: List of pending booking requests Each request card shows:
  - Client name + photo
  - Service category
  - Scheduled date and time
  - Notes from client
  - "Accept" and "Decline" buttons inline
- Floating Action Button (FAB): "➕ New Post" → Create Job Post Screen

#### C1.2 Create Job Post Screen
- Form fields:
  - Service Category (dropdown, one post per category enforced — if worker already has a post in this category, shown a warning: "You already have a post in this category. Creating a new one will replace it.")
  - Post Title (required, string, e.g. "Expert Aircon Cleaning & Repair")
  - Description (required, text area, e.g. experience, tools, coverage area)
  - Starting Rate Amount (number input)
  - Rate Type (dropdown): Per Hour / Per Day / Per Week / Per Month / Per Session / Per Project
  - Availability (toggle: Available Now / By Schedule)
- Post button at bottom
- On success → returns to Worker Home with new post visible in "Your Active Posts"

#### C1.3 Edit Job Post Screen
- Same form as Create Job Post
- Pre-filled with existing post data
- Worker can update any field
- Save Changes button
- Deactivate Post option (red, bottom)
  - Deactivated posts disappear from client feed
  - Worker can reactivate at any time

---

### C2. Verification

#### C2.1 Verification Document Submission
- Step indicator (Step 1 of 3 / 2 of 3 / 3 of 3)
- Upload blocks (each with camera/gallery picker):
  - Government ID (required) Shows thumbnail once uploaded
  - Barangay Certificate / Clearance (required) Shows thumbnail once uploaded
  - Selfie Holding ID (required) Shows thumbnail once uploaded
  - Skill Certificate (optional) e.g. TESDA NC II certificate
- All 3 required uploads must be completed before Submit button activates
- On submit:
  - verification_status = 'pending'
  - Cannot resubmit while pending
  - Admin notified
  - Worker sees "Under Review" status

#### C2.2 Verification Status Screen (Accessible from Profile tab)
- Current status display:
  - Pending: spinner + "Your documents are under review. This usually takes 1-3 business days."
  - Approved: green checkmark + verification badge preview + trust tier shown
  - Rejected: red icon + admin remarks shown + "Resubmit Documents" button → Resubmission goes back to Document Submission Screen (pre-filled where possible)
- Shows per-document status if available: e.g. "Government ID: Approved ✓" "Barangay Certificate: Rejected ✗"
- Trust tier displayed: verified / trusted / flagged (with explanation of what each means)

---

### C3. Job / Booking Management

#### C3.1 Booking Schedule / History Screen (Bottom nav tab: Jobs)
- Segmented tabs: Upcoming | Ongoing | Completed | Cancelled
- Job card shows:
  - Client name + photo
  - Service category
  - Scheduled date and time
  - Status badge
  - Booking code
- Tap any card → Job Detail Screen

#### C3.2 Job Detail Screen
- Client summary card:
  - Photo, name
  - "Message" icon button → Chat Thread
- Booking information block:
  - Service category
  - Scheduled date and time
  - Notes from client
  - Starting rate from job post
  - Booking code
- Status timeline
- Map button (visible when Accepted or Active): Opens Map Screen (see C3.3)
- Conditional action buttons by status:
  - Accepted (not yet started): "Start Traveling" button → Starts worker's GPS tracking → Booking status stays Accepted → Worker's pin becomes live on client's map
  - Active (job in progress): "Mark Job as Completed" button
  - Completed: "Rate Client" button (if not yet reviewed)
- "File a Report" link (visible on all active status bookings)

#### C3.3 Map Screen (Worker View)
- Same map screen as client (B3.5) but from worker's perspective:
- Green pin: Worker's registered barangay (worker's own location reference)
- Blue pin: Client's registered barangay (destination when worker travels to client)
- "I'm on my way" button:
  - Starts worker's real GPS streaming
  - Client sees worker's pin move live
  - Worker gets navigation context (blue pin = destination)
- "I've Arrived" button:
  - Stops GPS streaming
  - Client notified "Worker has arrived!"
- If client is also tracking:
  - Blue pin becomes animated/moving
  - Shows client's real-time location

#### C3.4 Accept Booking Request
- Worker taps "Accept" on a booking request (from Worker Home or Job Detail)
- Confirmation dialog
- On accept:
  - Booking status → Accepted
  - Client notified immediately
  - Map screen becomes available to both

#### C3.5 Decline Booking Request
- Worker taps "Decline" on a booking request
- Optional: reason for declining (free text)
- Confirmation dialog
- On decline:
  - Booking status → Declined
  - Client notified

#### C3.6 Mark Job as Started
- Worker taps "Start Job" on Job Detail when work begins at client's location (or when client arrives at worker's location)
- Booking status → Active
- started_at timestamp recorded
- Note: this is separate from live tracking (tracking = traveling to location, start job = work has actually begun)

#### C3.7 Mark Job as Completed
- Worker taps "Mark as Completed" when work is done
- Booking status → Completed
- completed_at timestamp recorded
- Both GPS tracking flags forced to false (safety net in case either party forgot to tap "I've Arrived")
- Client prompted to Rate & Review
- Worker prompted to Rate Client

---

### C4. Portfolio & Skills Management

#### C4.1 Manage Job Posts (Accessible from Profile tab)
- List of all worker's job posts (active and inactive)
- Each post row shows:
  - Category, title, rate
  - Active / Inactive status toggle
  - Edit button → Edit Job Post Screen
- "Create New Post" button → Create Job Post Screen
- Maximum one post per service category (enforced at creation)

#### C4.2 Portfolio Photos (Within Profile tab / Worker Profile section)
- Grid of uploaded portfolio photos
- "Add Photo" tile (tap → image picker)
- Tap existing photo → options: Remove or View full size
- Photos shown on public worker profile (visible to clients browsing)

#### C4.3 Bio Management (Within Profile tab / Worker Profile section)
- Editable bio text area
- Saved alongside other profile info
- Shown on worker's public profile in the About tab

---

### C5. Ratings & Reviews (Worker Side)

#### C5.1 Rate Client Screen
- Appears after booking is Completed
- Shows client photo and name
- 5-star rating selector
- Written review text area (optional)
- Submit button
- One review per worker per booking
- Worker ratings of clients are NOT shown publicly — used internally by admin only

#### C5.2 View Own Received Reviews
- Accessible from Profile tab
- List of all reviews worker has received from clients
- Each row: client name, star score, comment, date, booking reference
- Overall average rating shown at top

---

### C6. Reports (Worker Side)

#### C6.1 File a Report Screen
- Same structure as Client's report screen (see B5.1) but for reporting a CLIENT
- Reason options adjusted for worker context: Non-payment, Abusive Behavior, Unsafe Work Environment, No-show (client didn't show up), False Information, Other

#### C6.2 Report Status Screen
- Same structure as Client's (see B5.2)
- Shows worker's own filed reports and their resolution status

---

## Screen Count Summary

```
Section A — Shared:
  A1. Auth / Onboarding:   9 screens
  A2. Profile Management:  3 screens
  A3. In-App Messaging:    2 screens
  A4. Notifications:       1 screen
  A5. Help & Support:      1 screen
  Subtotal:               16 screens

Section B — Client Only:
  B1. Home Feed:           2 screens
  B2. Worker Discovery:    2 screens
  B3. Booking:             6 screens
  B4. Ratings & Reviews:   2 screens
  B5. Reports:             2 screens
  Subtotal:               14 screens

Section C — Worker Only:
  C1. Worker Home:         3 screens
  C2. Verification:        2 screens
  C3. Job Management:      6 screens
  C4. Portfolio & Skills:  3 screens
  C5. Ratings & Reviews:   2 screens
  C6. Reports:             2 screens
  Subtotal:               18 screens

TOTAL UNIQUE SCREENS:     48 screens
```

---

## Section Gate Rule (Critical for App Dev)

```
App Dev must complete AND receive PM approval
on each section before starting the next:

Section 0 (Auth/Onboarding) → PM approves
        ↓
Section 1 (Client screens)  → PM approves
        ↓
Section 2 (Worker screens)  → PM approves
        ↓
Section 3 (Shared utilities) → PM approves

"Complete" means: all screens in the section
are running on the emulator, visually match
the Stitch HTML references, pass basic
interaction testing, and PM has signed off.

Do NOT start the next section without
explicit PM approval.
```

---

## API Connection Notes for App Dev

```
Android Emulator → Backend:
  http://10.0.2.2:8000/api
  (special Android alias for host machine)

Physical Device (same WiFi) → Backend:
  http://[PM local IP]:8000/api

Remote (different network):
  ngrok URL provided by PM

All API calls require header:
  Authorization: Bearer {token}
  (except public endpoints like /barangays,
  /service-categories, /ping)

Token stored in:
  FlutterSecureStorage
  key: 'auth_token'

On 401 response:
  Clear token → redirect to Login Screen
```

---

## Real-Time Connection Notes for App Dev

```
WebSocket used for:
  - Live location tracking (Map Screen)
  - In-app messaging (Chat Thread)
  - Future: push notification delivery

Technology:
  Laravel Echo + Soketi
  Pusher protocol (compatible with Soketi)

Flutter package:
  pusher_channels_flutter

Private channels (require auth token):
  private-booking.{bookingId}
    → LocationTrackingStarted event
    → LocationUpdated event
    → LocationTrackingStopped event
    → NewMessage event

Channel auth endpoint:
  POST /api/broadcasting/auth
  (handled by Laravel Sanctum automatically)
```
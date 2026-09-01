# HanapBuhay — App Worker Wireframe Specifications **Document Type:** Worker Mobile UI Specification **Platform:** Flutter (Android + iOS) **Audience:** App Developer, UI/UX Designer **Last Updated:** August 2026

---

## How to Use This Document

This document describes the layout, components, and behavior of every screen in the Worker experience of the HanapBuhay mobile app.

For shared screens (Login, Registration, Chat, Notifications, etc.) refer to 05_APP_CLIENT_WIREFRAME.md — those screens are described once under the Client wireframe since they are identical for both roles. This document covers Worker-specific screens only plus notes on how shared screens differ for workers.

Each screen description includes:
- Purpose
- Layout Zones (top to bottom)
- Key Elements
- Interactions / Navigation
- States (empty, loading, error)

---

## Design System Reference

```
Frame size:       375 x 812px (iPhone 14 standard)
Background:       #F5F5F5
Card style:       White (#FFFFFF), borderRadius 16px
                  BoxShadow: color #1A000000,
                  blurRadius 8, offset Offset(0,2)
Primary green:    #2E9B2E
Dark green:       #1D7A37
Font headings:    Poppins SemiBold / Bold
Font body:        Inter Regular / Medium / SemiBold
Button height:    52px, borderRadius 12px
Input height:     50px, borderRadius 10px
Screen padding:   16px horizontal
Bottom nav:       64px height + safe area
```

---

## Worker Bottom Navigation

```
Tab 1: Home         🏠  → Worker Home Screen
Tab 2: Jobs         💼  → Booking Schedule Screen
Tab 3: Messages     💬  → Chat Inbox Screen
Tab 4: Notifications 🔔 → Notification Center
Tab 5: Profile      👤  → Profile Tab Screen
```

Active tab: icon + label in #2E9B2E Inactive tab: icon + label in #9E9E9E Bottom nav background: white, top border 1px #E0E0E0

---

## SCREEN W1: Worker Home Screen **Route:** /worker/home **Bottom nav tab:** Home (active)

### Purpose Main landing screen for workers. Shows their availability status, active job posts, and incoming booking requests.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ SafeArea                            │
│                                     │
│ "Hi, [Name]! 👋"    [🔔 bell icon]  │
│ Poppins SemiBold 20px               │
│                                     │
│ ┌─── VERIFICATION BANNER ─────────┐ │
│ │ ⚠️ Your account isn't verified  │ │
│ │ yet. Verified workers get more  │ │
│ │ bookings.                       │ │
│ │ [Complete Verification →]       │ │
│ └─────────────────────────────────┘ │
│ (hidden if worker is verified)      │
│                                     │
│ ┌─── AVAILABILITY CARD ───────────┐ │
│ │ "Your Status"                   │ │
│ │ [Available] [Busy] [Offline]    │ │
│ │ Segmented toggle, large         │ │
│ └─────────────────────────────────┘ │
│                                     │
│ "Your Active Posts"   [+ New Post]  │
│ Poppins SemiBold 16px               │
│                                     │
│ [Job Post Card 1]                   │
│ [Job Post Card 2]                   │
│ ... (scrollable)                    │
│ "No active posts" empty state       │
│                                     │
│ "Incoming Requests"                 │
│ Poppins SemiBold 16px               │
│                                     │
│ [Booking Request Card 1]            │
│ [Booking Request Card 2]            │
│ ... (scrollable)                    │
│ "No pending requests" empty state   │
│                                     │
│ ──────── BOTTOM NAV ────────────── │
└─────────────────────────────────────┘

[➕ FAB] — floating bottom right
"New Post" label, green background
Positioned above bottom nav
```

### Verification Banner
```
Background:     #FFF8E1 (amber tint)
Border left:    4px solid #F9A825
Padding:        12px 16px
Border radius:  12px
Icon:           ⚠️ amber warning
Text:           Inter Medium 13px #1A1A1A
Button:         "Complete Verification →"
                Inter SemiBold 13px #2E9B2E
Tap button:     → Verification Submission Screen
Dismiss:        NOT dismissible
                (stays until worker is verified)
Hidden when:    verification_status = 'approved'
```

### Availability Card
```
White card, borderRadius 16px, shadow
Padding: 20px

Label: "Your Status"
Inter SemiBold 13px #6B6B6B

Segmented control (3 options):
  [Available] [Busy] [Offline]

  Available:
    Active bg: #E8F5E9, text #2E9B2E
    Means: visible on client feed,
           can receive bookings

  Busy:
    Active bg: #FFF8E1, text #F9A825
    Means: visible on feed but marked
           "Currently Busy"

  Offline:
    Active bg: #F5F5F5, text #6B6B6B
    Means: job posts still visible
           but marked Offline,
           bookings discouraged

Tap any option:
  → Immediately updates via API
  → PATCH /api/worker/profile
    body: { availability_status: "..." }
  → Success: brief green snackbar
    "Status updated to Available"
```

### Job Post Card (in "Your Active Posts")
```
White card, borderRadius 12px, shadow
Padding: 16px

Row layout:
  Left:
    Category icon (colored circle, 40x40)
  Center (Expanded):
    Post title: Inter SemiBold 14px #1A1A1A
    Category: Inter Regular 12px #6B6B6B
    Rate: "From ₱X/[period]"
           Inter SemiBold 13px #2E9B2E
  Right:
    Availability dot:
      Green = Available
      Amber = By Schedule
    "Edit" text button: #2E9B2E

Long press on card:
  Bottom sheet options:
    Edit Post → Edit Job Post Screen
    Deactivate Post → confirmation dialog
    Cancel
```

### Booking Request Card
```
White card, borderRadius 16px
Border left: 4px solid #2E9B2E (pending)
Padding: 16px

TOP ROW:
  Client avatar (40x40 circle)
  + Client name (Inter SemiBold 14px)
  + Barangay (Inter Regular 12px #6B6B6B)

MIDDLE ROW:
  Category tag (green chip)
  Scheduled date + time (Inter Medium 13px)

NOTES ROW (if notes provided):
  Note text preview (1 line, truncated)
  Inter Regular 13px #6B6B6B italic

BOTTOM ROW:
  [✓ Accept] button — green filled, flex 1
  [✗ Decline] button — red outlined, flex 1
  Gap: 12px between buttons

Accept tap:
  Confirmation dialog:
    "Accept this booking request?"
    [Accept] [Cancel]
  On confirm: → API call
  On success: card removed from list,
              green snackbar "Booking accepted!",
              booking appears in Jobs tab

Decline tap:
  Bottom sheet:
    "Reason for declining (optional)"
    Text input field
    [Decline Request] red button
  On confirm: → API call
  On success: card removed, amber snackbar
```

### Empty States
```
No active posts:
  Icon: 📋
  Title: "No active posts yet"
  Subtitle: "Create a post to start
             receiving bookings"
  Button: "Create Your First Post"
          → Create Job Post Screen

No incoming requests:
  Icon: 📭
  Title: "No pending requests"
  Subtitle: "New booking requests
             will appear here"
```

---

## SCREEN W2: Create Job Post Screen **Route:** /worker/posts/create **Accessed from:** FAB on Home, Manage Posts

### Purpose Worker creates a new service listing with title, description, category, and rate.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "Create Post"           │
│ AppBar: white bg, no elevation      │
│                                     │
│ SingleChildScrollView               │
│ Padding: 16px                       │
│                                     │
│ ┌─── FORM CARD ───────────────────┐ │
│ │                                 │ │
│ │ Service Category *              │ │
│ │ [Dropdown selector]             │ │
│ │ (shows 20 categories)           │ │
│ │ ⚠️ "Already have a post in      │ │
│ │ this category. Creating a new   │ │
│ │ one will replace it."           │ │
│ │ (shown if duplicate detected)   │ │
│ │                                 │ │
│ │ Post Title *                    │ │
│ │ [Text input]                    │ │
│ │ e.g. "Expert Aircon Cleaning"   │ │
│ │                                 │ │
│ │ Description *                   │ │
│ │ [Multi-line text area]          │ │
│ │ Min 3 lines shown               │ │
│ │                                 │ │
│ │ Starting Rate *                 │ │
│ │ [₱] [Number input]              │ │
│ │                                 │ │
│ │ Rate Type *                     │ │
│ │ [Per Hour  ] ▾                  │ │
│ │ Dropdown options:               │ │
│ │   Per Hour / Per Day / Per Week │ │
│ │   Per Month / Per Session /     │ │
│ │   Per Project                   │ │
│ │                                 │ │
│ │ Availability                    │ │
│ │ [Available Now ●──] toggle      │ │
│ │ Available Now / By Schedule     │ │
│ │                                 │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [Post This Service] ← green button  │
│ Full width, height 52px             │
│                                     │
│ Bottom safe area padding            │
└─────────────────────────────────────┘
```

### Key Elements
```
Service Category dropdown:
  Shows all active service categories
  from GET /api/service-categories
  If worker selects a category they already
  have a post for → warning text shown below
  dropdown (not a blocker, just a warning —
  on submit, old post is replaced)

Post Title input:
  Max 100 characters
  Character count shown (e.g. "23/100")
  Placeholder: "e.g. Expert Aircon Cleaning
  & Repair"

Description textarea:
  Max 500 characters
  Character count shown
  Placeholder: "Describe your service,
  experience, and what's included..."
  Min height: 120px

Starting Rate:
  Philippine peso (₱) prefix
  Numeric keyboard
  Decimal allowed (e.g. 150.50)
  Placeholder: "0.00"

Rate Type dropdown:
  Per Hour → displayed as "₱X/hr"
  Per Day → displayed as "₱X/day"
  Per Week → displayed as "₱X/wk"
  Per Month → displayed as "₱X/mo"
  Per Session → displayed as "From ₱X/session"
  Per Project → displayed as "From ₱X/project"

Availability toggle:
  Available Now: green dot, post marked
                 as available immediately
  By Schedule: amber dot, post marked
               as "By Schedule" on feed
```

### Interactions
```
Post button:
  Disabled if required fields empty
  On tap: validates all fields
  Shows loading spinner on button
  On success:
    → Returns to Worker Home
    → Green snackbar: "Post created!"
    → New post visible in "Your Active Posts"
  On error:
    → Red snackbar with error message

Back button:
  If form has unsaved content:
    "Discard changes?" dialog
    [Discard] [Keep Editing]
```

---

## SCREEN W3: Edit Job Post Screen **Route:** /worker/posts/{id}/edit **Accessed from:** Edit button on post card, Manage Posts Screen

### Purpose Worker edits an existing job post.

### Layout
```
Same layout as Create Job Post Screen
with these differences:

AppBar title: "Edit Post"
All fields pre-filled with existing data
Additional option at bottom (before Post button):

┌─── DANGER ZONE ──────────────────────┐
│ [Deactivate This Post]               │
│ Red text button, no background       │
│ "Your post will be hidden from       │
│  the client feed"                    │
└──────────────────────────────────────┘

Save button label: "Save Changes"
```

### Deactivate Post Interaction
```
Tap "Deactivate This Post":
  Confirmation bottom sheet:
    Title: "Deactivate this post?"
    Body: "Your post will be hidden from
           the client feed. You can
           reactivate it anytime."
    [Deactivate] — red button
    [Keep Active] — outlined button

On confirm:
  → API call: DELETE /api/worker/posts/{id}
  → Returns to Worker Home
  → Amber snackbar: "Post deactivated"
  → Post no longer visible in feed
```

---

## SCREEN W4: Manage Posts Screen **Route:** /worker/posts **Accessed from:** Profile tab

### Purpose Worker views and manages all their job posts including inactive ones.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "Manage Posts"          │
│                                     │
│ [+ Create New Post] button          │
│ Green outlined, top right           │
│                                     │
│ "Active Posts" section label        │
│                                     │
│ [Post Card 1 — active]              │
│ [Post Card 2 — active]              │
│                                     │
│ "Inactive Posts" section label      │
│                                     │
│ [Post Card 3 — inactive, greyed]    │
│                                     │
└─────────────────────────────────────┘
```

### Post Card (Manage Posts version)
```
White card (inactive: gray tint bg), padding 16px

LEFT: Category icon circle
CENTER:
  Post title: Inter SemiBold 14px
  Category: Inter Regular 12px #6B6B6B
  Rate: Inter SemiBold 13px #2E9B2E
  (gray if inactive: #9E9E9E)
RIGHT:
  Status badge pill:
    Active → green pill "Active"
    Inactive → gray pill "Inactive"
  Chevron right icon → opens Edit Screen

Active post additional actions (swipe left):
  Red "Deactivate" action revealed

Inactive post additional actions (swipe left):
  Green "Reactivate" action revealed
  Tap Reactivate:
    → API: PATCH /api/worker/posts/{id}
      body: { is_active: true }
    → Post moved to Active section
    → Green snackbar: "Post reactivated"
```

---

## SCREEN W5: Verification Document Submission **Route:** /worker/verification/submit **Accessed from:** Verification banner, Profile tab → Verification Status

### Purpose Worker submits identity documents for admin review and barangay verification.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]  "Verification"           │
│                                     │
│ Step indicator: [1]──[2]──[3]       │
│ (Step 1 of 3)                       │
│                                     │
│ "Verify your identity"              │
│ Poppins SemiBold 20px               │
│                                     │
│ "Upload your documents to get       │
│ barangay-verified and unlock        │
│ more bookings."                     │
│ Inter Regular 14px #6B6B6B          │
│                                     │
│ ┌─── DOCUMENT UPLOAD CARD ────────┐ │
│ │                                 │ │
│ │ 📄 Government ID *              │ │
│ │ [Upload Area — tap to upload]   │ │
│ │ [Thumbnail if uploaded] [✓]     │ │
│ │                                 │ │
│ │ 📋 Barangay Certificate *       │ │
│ │ [Upload Area — tap to upload]   │ │
│ │ [Thumbnail if uploaded] [✓]     │ │
│ │                                 │ │
│ │ 🤳 Selfie Holding Your ID *     │ │
│ │ [Upload Area — tap to upload]   │ │
│ │ [Thumbnail if uploaded] [✓]     │ │
│ │                                 │ │
│ │ 🏆 Skill Certificate (Optional) │ │
│ │ [Upload Area — tap to upload]   │ │
│ │ e.g. TESDA NC II certificate    │ │
│ │                                 │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [Submit for Review]                 │
│ Green button, disabled until all    │
│ 3 required docs uploaded            │
│                                     │
└─────────────────────────────────────┘
```

### Upload Area Component
```
Default state (no file):
  Dashed border: 2px dashed #E0E0E0
  Border radius: 12px
  Background: #F5F5F5
  Center content:
    Upload icon (gray)
    "Tap to upload" Inter Regular 14px #9E9E9E
    "JPG, PNG — max 5MB" caption
  Height: 100px

Uploaded state:
  Thumbnail image fills the area
  Green checkmark overlay (top right corner)
  "Tap to replace" caption below

Tap upload area:
  Bottom sheet picker:
    [📷 Take Photo]
    [🖼️ Choose from Gallery]
    [Cancel]

Upload in progress:
  Linear progress bar below the area
  "Uploading..." text
```

### Submit Button States
```
Disabled (not all required docs uploaded):
  Background: #E0E0E0
  Text: #9E9E9E
  Not tappable

Enabled (all 3 required docs uploaded):
  Background: #2E9B2E
  Text: white
  Tappable

Loading (after tap):
  Shows CircularProgressIndicator
  white, size 20px

Success:
  → Navigates to Verification Status Screen
  → Status shows "pending"
```

---

## SCREEN W6: Verification Status Screen **Route:** /worker/verification/status **Accessed from:** Profile tab, Verification banner

### Purpose Worker checks the current status of their verification application.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "Verification Status"   │
│                                     │
│ ┌─── STATUS CARD ─────────────────┐ │
│ │                                 │ │
│ │ [Status illustration/icon]      │ │
│ │ Large centered icon             │ │
│ │                                 │ │
│ │ Status title                    │ │
│ │ Poppins SemiBold 20px           │ │
│ │                                 │ │
│ │ Status description              │ │
│ │ Inter Regular 14px #6B6B6B      │ │
│ │                                 │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─── DOCUMENT STATUS LIST ────────┐ │
│ │ Government ID        ✓ Approved │ │
│ │ Barangay Certificate ✓ Approved │ │
│ │ Selfie with ID       ✗ Rejected │ │
│ │ Skill Certificate    — N/A      │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─── ADMIN REMARKS ───────────────┐ │
│ │ (shown if rejected)             │ │
│ │ "Selfie image is too blurry.    │ │
│ │ Please retake with better       │ │
│ │ lighting."                      │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [Resubmit Documents] button         │
│ (shown only if rejected)            │
│                                     │
│ ┌─── TRUST TIER CARD ─────────────┐ │
│ │ (shown only if approved)        │ │
│ │ 🛡️ Barangay Verified            │ │
│ │ "Your account is verified.      │ │
│ │ Clients can see your posts      │ │
│ │ with the verified badge."       │ │
│ └─────────────────────────────────┘ │
│                                     │
└─────────────────────────────────────┘
```

### Status States
```
Pending:
  Icon:  🕐 amber colored clock
  Title: "Under Review"
  Desc:  "Your documents are being reviewed
          by our barangay admin. This usually
          takes 1–3 business days."
  No action button shown

Approved:
  Icon:  ✅ green shield checkmark
  Title: "Verified!"
  Desc:  "Your account is barangay-verified.
          Your posts now show the verified
          badge to clients."
  Trust tier card shown below

Rejected:
  Icon:  ❌ red X circle
  Title: "Verification Rejected"
  Desc:  "Please review the admin's remarks
          and resubmit your documents."
  Admin remarks card shown
  [Resubmit Documents] green button shown
  → Tap: goes to Submission Screen
    with previous data pre-filled
    where possible

Not submitted (unverified):
  Icon:  📋 gray document
  Title: "Not Yet Verified"
  Desc:  "Submit your documents to get
          barangay-verified and rank
          higher in client searches."
  [Submit Documents Now] green button
  → Submission Screen
```

---

## SCREEN W7: Job Schedule Screen **Route:** /worker/jobs **Bottom nav tab:** Jobs (active)

### Purpose Worker views all their bookings organized by status.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ "My Jobs"                           │
│ Poppins SemiBold 22px               │
│                                     │
│ [Upcoming][Ongoing][Completed][Cancelled]│
│ Segmented tab row                   │
│ Active tab: green underline + text  │
│                                     │
│ Scrollable list of job cards        │
│                                     │
│ [Job Card 1]                        │
│ [Job Card 2]                        │
│ ...                                 │
│                                     │
│ Empty state if no bookings in tab   │
│                                     │
│ ──────── BOTTOM NAV ────────────── │
└─────────────────────────────────────┘
```

### Job Card
```
White card, borderRadius 16px, shadow
Padding: 16px

TOP ROW:
  Client avatar (40x40 circle)
  + Client name: Inter SemiBold 14px
  + Client barangay: Inter Regular 12px #6B6B6B
  RIGHT: Status badge pill (color-coded)

MIDDLE ROW:
  Category chip (green outlined)
  Scheduled date: Inter Medium 13px
  Scheduled time: Inter Regular 12px #6B6B6B

BOOKING CODE ROW:
  "#HB-2026-00001" Inter Regular 12px #9E9E9E

Tap anywhere on card:
  → Job Detail Screen
```

### Status Badge Colors
```
Pending   → gray bg, gray text
Accepted  → blue bg, blue text
Active    → green bg, white text
Completed → solid green bg, white text
Cancelled → red bg, red text
Declined  → gray bg, gray text
```

---

## SCREEN W8: Job Detail Screen **Route:** /worker/jobs/{id} **Accessed from:** Job Schedule Screen

### Purpose Full detail view of a specific booking with all worker actions available.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "#HB-2026-00001"        │
│            [Status badge]           │
│                                     │
│ ┌─── CLIENT CARD ─────────────────┐ │
│ │ [Avatar 52px]                   │ │
│ │ Client Name (SemiBold)          │ │
│ │ Barangay · Distance             │ │
│ │                    [💬 Message] │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─── BOOKING INFO ────────────────┐ │
│ │ Service Category                │ │
│ │ Job Post Title (if linked)      │ │
│ │ Starting Rate                   │ │
│ │ 📅 Scheduled: Date + Time       │ │
│ │ 📝 Notes: client's notes        │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─── STATUS TIMELINE ─────────────┐ │
│ │ ●─────●─────●─────●             │ │
│ │ Pending Accepted Active Complete │ │
│ │ (timestamps below each step)    │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [MAP BUTTON]                        │
│ (shown when Accepted or Active)     │
│ White card with map preview icon    │
│ "View Map & Track Location →"       │
│ → Map Screen                        │
│                                     │
│ ┌─── ACTION AREA ─────────────────┐ │
│ │ (changes based on status)       │ │
│ │                                 │ │
│ │ ACCEPTED:                       │ │
│ │   [Start Traveling] green       │ │
│ │   [Mark Job as Started] outlined│ │
│ │                                 │ │
│ │ ACTIVE:                         │ │
│ │   [Mark Job as Completed] green │ │
│ │                                 │ │
│ │ COMPLETED:                      │ │
│ │   [Rate Client] green           │ │
│ │   (if not yet rated)            │ │
│ │   OR: submitted review shown    │ │
│ │                                 │ │
│ │ PENDING:                        │ │
│ │   [Accept] green                │ │
│ │   [Decline] red outlined        │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [File a Report] — small text link   │
│ Inter Regular 13px #D32F2F          │
│ (visible on all active statuses)    │
│                                     │
└─────────────────────────────────────┘
```

### Action Button Behaviors
```
Start Traveling:
  Tap → immediately opens Map Screen
  Worker's GPS tracking starts
  Client sees worker pin move on their map

Mark Job as Started:
  Tap → confirmation dialog:
    "Mark this job as started?"
    "This confirms you have arrived and
    work has begun."
    [Start Job] [Cancel]
  On confirm:
    status → active
    started_at recorded
    Client notified: "Worker has started
    the job."

Mark Job as Completed:
  Tap → confirmation dialog:
    "Mark this job as completed?"
    [Complete Job] [Cancel]
  On confirm:
    status → completed
    completed_at recorded
    Both GPS tracking flags → false
    Client notified + prompted to rate
    Worker prompted to rate client

Rate Client:
  Tap → Rate Client Screen

Accept (from pending):
  Same as booking request accept flow

Decline (from pending):
  Same as booking request decline flow
```

---

## SCREEN W9: Map Screen (Worker View) **Route:** /worker/jobs/{id}/map **Accessed from:** Job Detail Screen, "Start Traveling" button

### Purpose Worker views both location pins and optionally starts live GPS sharing.

### Layout
```
┌─────────────────────────────────────┐
│ ← [Back]   "Track Location"        │
│                                     │
│ ┌─── GOOGLE MAPS FULL WIDTH ──────┐ │
│ │                                 │ │
│ │  🟢 [Your barangay pin]         │ │
│ │     "Your Location"             │ │
│ │     (Poblacion)                 │ │
│ │                                 │ │
│ │  🔵 [Client barangay pin]       │ │
│ │     "Client Location"           │ │
│ │     (Calanggaman)               │ │
│ │                                 │ │
│ │  ──── distance line ────        │ │
│ │     "~2.3 km apart"             │ │
│ │     Inter Medium 12px           │ │
│ │     (label on map)              │ │
│ │                                 │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─── BOTTOM CARD OVERLAY ─────────┐ │
│ │                                 │ │
│ │ Client avatar + name            │ │
│ │ Booking code + category         │ │
│ │                                 │ │
│ │ TRACKING STATUS:                │ │
│ │ If not tracking:                │ │
│ │   "Client can't see you yet"    │ │
│ │   Inter Regular 13px #6B6B6B    │ │
│ │                                 │ │
│ │ If tracking:                    │ │
│ │   🟢 "Sharing your location"    │ │
│ │   Inter SemiBold 13px #2E9B2E   │ │
│ │   animated pulsing green dot    │ │
│ │                                 │ │
│ │ [I'm on my way] button          │ │
│ │ (shown when NOT tracking)       │ │
│ │ Full width, green, height 52px  │ │
│ │                                 │ │
│ │ [I've Arrived ✓] button         │ │
│ │ (shown when tracking)           │ │
│ │ Full width, green, height 52px  │ │
│ │                                 │ │
│ └─────────────────────────────────┘ │
└─────────────────────────────────────┘
```

### Map Pins
```
Green pin (🟢):
  Worker's registered barangay center
  Label: "Your Location"
  Sub-label: barangay name
  When tracking: animated pulsing ring
                 around pin + moves with
                 real GPS location

Blue pin (🔵):
  Client's registered barangay center
  Label: "Client Location"
  Sub-label: client's barangay name
  Static — does not move unless client
  is also tracking
  When client tracking: animated pulsing
                        ring + moves with
                        client's real GPS
```

### "I'm on my way" Interaction
```
Tap button:
  → Requests GPS permission if not granted
  → If denied: show explanation dialog
    "Location permission is needed to
    share your location with the client."
    [Open Settings] [Cancel]
  → If granted:
    POST /api/bookings/{id}/tracking/start
    body: { role: "worker" }
    Flutter begins streaming GPS every
    3 seconds via WebSocket:
    Event: LocationUpdated
    Channel: private-booking.{id}
    Payload: { role, latitude, longitude }
    Button changes to "I've Arrived ✓"
    Bottom card shows "Sharing your location"
    Green pulsing indicator
    Worker pin becomes live/moving on map
    Client receives push notification:
    "Worker is on the way!"
```

### "I've Arrived" Interaction
```
Tap button:
  → GPS streaming stops
  → POST /api/bookings/{id}/tracking/stop
    body: { role: "worker" }
  → Worker pin freezes at last location
  → Button changes back to "I'm on my way"
  → Client receives notification:
    "Worker has arrived!"
  → Bottom card: "Tracking stopped"
```

### If Client is Also Tracking
```
Blue pin becomes animated/moving
Shows client's real-time GPS location
Client is heading toward worker's
barangay pin (blue pin moves toward green)
No additional UI change needed —
just the pin animates
```

---

## SCREEN W10: Rate Client Screen **Route:** /worker/jobs/{id}/rate **Accessed from:** Job Detail Screen (after booking completed)

### Purpose Worker rates and optionally reviews the client after a completed job.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "Rate Client"           │
│                                     │
│ "How was your experience            │
│ with this client?"                  │
│ Poppins SemiBold 18px center        │
│                                     │
│ [Client avatar 80x80 circle]        │
│ [Client name] Inter SemiBold 16px   │
│ [Booking ref] Inter Regular 12px    │
│ All centered                        │
│                                     │
│ ┌─── STAR RATING ─────────────────┐ │
│ │                                 │ │
│ │   ★  ★  ★  ★  ★               │ │
│ │   (large tappable stars, 48px)  │ │
│ │   1  2  3  4  5                 │ │
│ │                                 │ │
│ │ "Tap to rate" hint              │ │
│ │ (hidden after star tapped)      │ │
│ │                                 │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─── COMMENT CARD ────────────────┐ │
│ │ Leave a comment (optional)      │ │
│ │ [Multi-line text area]          │ │
│ │ "Share your experience..."      │ │
│ │ Max 300 characters              │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [Submit Review] green button        │
│ Disabled until at least 1 star      │
│ selected                            │
│                                     │
│ [Skip for now] text link            │
│ Inter Regular 13px #6B6B6B          │
│ center                              │
│                                     │
└─────────────────────────────────────┘
```

### Interactions
```
Star tap:
  Stars fill from left to selected star
  All stars to the right become outlined
  Selected star slightly enlarges (scale 1.2)
    on tap then returns to normal

Submit Review:
  POST /api/ratings
  body: { booking_id, score, comment }
  On success:
    Green snackbar: "Review submitted!"
    → Returns to Job Detail Screen

Skip for now:
  No API call
  → Returns to Job Detail Screen
  Review can be submitted later
  from Job Detail Screen
```

---

## SCREEN W11: Portfolio & Skills Screen **Route:** /worker/portfolio **Accessed from:** Profile tab

### Purpose Worker manages their bio and portfolio photos.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "Portfolio & Bio"       │
│                                     │
│ "Bio"                               │
│ Inter SemiBold 14px                 │
│                                     │
│ [Multi-line text area]              │
│ "Tell clients about your            │
│ experience and skills..."           │
│ Max 500 characters                  │
│                                     │
│ [Save Bio] green outlined button    │
│                                     │
│ ─────────────────────────────────── │
│                                     │
│ "Portfolio Photos"                  │
│ Inter SemiBold 14px                 │
│                                     │
│ PHOTO GRID (3 columns):             │
│ [Photo][Photo][Photo]               │
│ [Photo][Photo][+ Add]               │
│                                     │
│ Each photo tile: 110x110px          │
│ Add tile: dashed border, + icon     │
│                                     │
│ Tap existing photo:                 │
│ Bottom sheet:                       │
│   [View Full Size]                  │
│   [Remove Photo]                    │
│   [Cancel]                          │
│                                     │
│ Tap + Add tile:                     │
│   Bottom sheet picker:              │
│   [Take Photo] [Choose from Gallery]│
│                                     │
└─────────────────────────────────────┘
```

---

## SCREEN W12: Worker Profile Tab **Route:** /worker/profile **Bottom nav tab:** Profile (active)

### Purpose Worker's account hub — access to all profile-related screens.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ "My Profile"                        │
│ Poppins SemiBold 22px               │
│                                     │
│ ┌─── PROFILE SUMMARY CARD ────────┐ │
│ │ [Avatar 72px]                   │ │
│ │ Worker Name  Poppins SemiBold   │ │
│ │ [🛡️ Barangay Verified] badge    │ │
│ │ ⭐ 4.8 (23 reviews)             │ │
│ │ Barangay name                   │ │
│ └─────────────────────────────────┘ │
│                                     │
│ WORKER SECTION:                     │
│ ┌─────────────────────────────────┐ │
│ │ 🛡️ Verification Status    →     │ │
│ │ 📋 Manage Posts            →    │ │
│ │ 🖼️ Portfolio & Bio         →    │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ACCOUNT SECTION:                    │
│ ┌─────────────────────────────────┐ │
│ │ 👤 Edit Profile            →    │ │
│ │ 🔒 Security Settings       →    │ │
│ │ 🔔 Notification Preferences →   │ │
│ │ ❓ Help / FAQ              →    │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [Log Out] — red text, centered      │
│ Inter SemiBold 14px #D32F2F         │
│                                     │
│ ──────── BOTTOM NAV ────────────── │
└─────────────────────────────────────┘
```

### List Row Style
```
Each row:
  Leading icon (green tint circle, icon inside)
  Title: Inter Medium 14px #1A1A1A
  Trailing: chevron right icon #9E9E9E
  Height: 56px
  Divider between rows: 1px #F5F5F5

Tap any row → navigates to that screen

Log Out tap:
  Confirmation dialog:
    "Log out of HanapBuhay?"
    [Log Out] red button
    [Cancel] outlined button
  On confirm:
    → POST /api/auth/logout
    → Clear stored token
    → Navigate to Login Screen
    → Clear all navigation stack
```

---

## SCREEN W13: File a Report Screen (Worker) **Route:** /worker/report/create **Accessed from:** Job Detail Screen

### Purpose Worker files a complaint against a client.

### Layout (top to bottom)
```
┌─────────────────────────────────────┐
│ ← [Back]   "File a Report"         │
│                                     │
│ ┌─── BOOKING REFERENCE ───────────┐ │
│ │ #HB-2026-00001 (auto-filled)    │ │
│ │ Client name + category          │ │
│ │ Non-editable, gray bg           │ │
│ └─────────────────────────────────┘ │
│                                     │
│ Reason for Report *                 │
│ [Dropdown selector]                 │
│ Options:                            │
│   Non-payment                       │
│   Abusive Behavior                  │
│   Unsafe Work Environment           │
│   No-show (client didn't appear)    │
│   False Information                 │
│   Other                             │
│                                     │
│ Description *                       │
│ [Multi-line text area]              │
│ "Describe what happened..."         │
│ Min 20 characters required          │
│                                     │
│ Evidence Photos (Optional)          │
│ [+ Add Photos] button               │
│ Photo thumbnails shown after added  │
│ Max 5 photos                        │
│                                     │
│ [Submit Report] green button        │
│                                     │
└─────────────────────────────────────┘
```

---

## SCREEN W14: Report Status Screen (Worker) **Route:** /worker/reports **Accessed from:** Profile tab

### Purpose Worker tracks status of reports they've filed.

### Layout
```
┌─────────────────────────────────────┐
│ ← [Back]   "My Reports"            │
│                                     │
│ Scrollable list of report cards     │
│                                     │
│ ┌─── REPORT CARD ─────────────────┐ │
│ │ Reason tag (colored)            │ │
│ │ "Re: Booking #HB-2026-00001"    │ │
│ │ Client name reported            │ │
│ │ Date filed                      │ │
│ │ Status badge:                   │ │
│ │   Under Review → amber          │ │
│ │   Resolved → green              │ │
│ │   Dismissed → gray              │ │
│ │                                 │ │
│ │ [Expanded when tapped:]         │ │
│ │   Admin remarks (if resolved)   │ │
│ │   Resolution action taken       │ │
│ └─────────────────────────────────┘ │
│                                     │
│ Empty state:                        │
│   "No reports filed"                │
│                                     │
└─────────────────────────────────────┘
```

---

## Shared Screens (Worker Version Notes)

The following screens are shared between Client and Worker but have minor differences for the Worker role. Full specs in 05_APP_CLIENT_WIREFRAME.md:

### Chat Inbox + Chat Thread
```
Identical to client version.
Conversations tied to bookings where
worker is the worker_id.
```

### Notification Center
```
Identical to client version.
Worker-specific notification types:
  - New booking request received
  - Booking cancelled by client
  - Verification approved / rejected
  - Trust tier updated
  - Report resolved
  - New message received
  - Job marked as completed
    (when client confirms)
```

### Edit Profile Screen
```
Identical to client version.
Same fields: photo, name, mobile, barangay.
```

### Security Settings Screen
```
Identical to client version.
Change password / 2FA / login activity.
```

### Notification Preferences Screen
```
Same structure as client.
Worker-specific toggles:
  - New booking requests (on/off)
  - Verification updates (on/off)
  - Trust tier updates (on/off)
```

### Help / FAQ Screen
```
Same structure as client.
Worker-specific FAQ topics:
  - How to get verified
  - How to create job posts
  - How trust tiers work
  - How ratings affect your ranking
```

---

## Worker Flow Summary

```
Registration:
  Role Selection → Account Details →
  Email Verification → Worker Home
  (verification banner shown)

First-time verification:
  Verification banner → Submit Documents →
  Verification Status (pending) →
  Wait for admin approval →
  Push notification "Verified!" →
  Verification badge appears

Creating first job post:
  Worker Home FAB → Create Post →
  Fill form → Post →
  Post visible in client feed

Receiving and completing a booking:
  Push notification (new request) →
  Worker Home (request card) →
  Accept →
  Chat with client →
  Map screen → "I'm on my way" →
  GPS tracking starts →
  Arrive → "I've Arrived" →
  "Mark Job as Started" →
  Complete work →
  "Mark as Completed" →
  Rate client
```
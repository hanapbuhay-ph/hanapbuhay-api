# HanapBuhay — Web Wireframe Specifications **Document Type:** Admin Web Panel UI Specification **Platform:** React.js (browser) **Audience:** Web Developer, UI/UX Designer **Last Updated:** August 2026

---

## How to Use This Document

This document describes the layout, components, and behavior of every screen in the HanapBuhay Admin Web Panel. Each screen description includes:

- Purpose — what the screen is for
- Layout Zones — how the screen is divided
- Key Elements — what must appear
- Interactions — what happens on user actions
- States — empty, loading, error variations

---

## Global Layout (All Screens Except Login)

```
┌─────────────────────────────────────────────┐
│                  TOPBAR                      │
│  [KC Logo + "Katulong Connect" brand]        │
│  [Page Title]              [Bell] [Avatar]   │
├──────────────┬──────────────────────────────┤
│              │                              │
│   SIDEBAR    │        MAIN CONTENT          │
│   (260px)    │        (flexible)            │
│              │                              │
│  Nav items   │  Page-specific content       │
│  grouped by  │  scrolls here                │
│  section     │                              │
│              │                              │
│  [Log Out]   │                              │
│  (pinned     │                              │
│   bottom)    │                              │
└──────────────┴──────────────────────────────┘
```

### Sidebar Navigation Structure
```
OVERVIEW
  • Dashboard

VERIFICATION
  • Worker Verification

COMMUNITY
  • User Accounts
  • Job Posts
  • Booking Monitoring
  • Reports & Disputes
  • Ratings & Reviews

SYSTEM
  • Platform Settings
  • Audit Log

ACCOUNT
  • Account Settings

[Log Out] — pinned at bottom
```

### Sidebar Styling
```
Background:       Dark green (#1D7A37)
Active item:      White background at 16% opacity
                  + white dot indicator
Active text:      White, SemiBold
Inactive text:    White at 82% opacity, Medium
Section labels:   White at 50% opacity, uppercase,
                  letter-spacing 1.2
Logo area:        KC green rounded square + brand name
Width:            260px, fixed
```

### Topbar Styling
```
Background:       White (#FFFFFF)
Bottom border:    1px solid #E0E0E0
Height:           76px
Left:             Page title (Poppins SemiBold 22px)
Right:            Notification bell icon
                  + Admin avatar + name + role label
```

### Main Content Area
```
Background:       Light gray (#F5F5F5)
Padding:          32px horizontal, 28px vertical
Cards:            White, border-radius 14px,
                  box-shadow 0 2px 8px rgba(0,0,0,0.1)
```

---

## Screen 1: Admin Login

**Purpose:** Authenticate admin before accessing panel.

### Layout
```
┌────────────────────────────────────────────┐
│  LEFT PANEL (560px)    │  RIGHT PANEL       │
│  Dark green bg         │  White bg          │
│                        │                    │
│  [Logo mark 120x120]   │  "Welcome back,    │
│  "HanapBuhay"          │   Admin"           │
│  Admin Portal tagline  │                    │
│                        │  [Email field]     │
│                        │  [Password field]  │
│                        │  [Forgot pw link]  │
│                        │  [Log In button]   │
│                        │  [2FA note]        │
└────────────────────────────────────────────┘
```

### Key Elements
```
Left Panel:
  - HanapBuhay logo (120x120)
  - "HanapBuhay" in Poppins Bold 30px white
  - "Admin Portal — Barangay-Verified Community
    Worker Marketplace" in Inter Regular 15px
    white at 85% opacity

Right Panel (centered form, max-width 400px):
  - "Welcome back, Admin" Poppins SemiBold 26px
  - Subtitle: "Log in to manage verifications,
    bookings, and community reports."
    Inter Regular 14px #6B6B6B
  - Email Address label + input field
  - Password label + input field (show/hide)
  - "Forgot Password?" right-aligned link
    Inter SemiBold 13px primary green
  - "Log In" button full-width green
  - 2FA note (light green bg):
    "Two-Factor Authentication will be
    requested if enabled on this account."
```

### States
```
Loading:    Log In button shows spinner
Error:      Red error message below form
            "Invalid email or password"
2FA active: After credentials → OTP input screen
            6-box OTP + resend + verify button
```

---

## Screen 2: Dashboard

**Purpose:** Landing page — platform overview at a glance.

### Layout
```
[Topbar: "Dashboard" | Bell + Avatar]
│
├── Welcome subtitle text
│
├── [STAT CARDS ROW — 4 cards equal width]
│   Total Users | Pending Verifications |
│   Active Bookings | Open Disputes
│
└── [LOWER ROW]
    ├── Recent Activity Panel (left, ~58%)
    └── Pending Verifications Panel (right, ~38%)
```

### Stat Cards
```
Each card (white, border-radius 14px, shadow):
  - Card label (Inter Medium 13px #6B6B6B)
  - Icon box (green tint bg, emoji/icon)
  - Large number (Poppins SemiBold 28px)
  - Delta text:
    Green = positive ("+38 this week")
    Amber = needs attention ("Needs review")

Cards:
  1. Total Users          → total user count
  2. Pending Verifications → count, amber delta
  3. Active Bookings      → count, green delta
  4. Open Disputes        → count, amber delta
```

### Recent Activity Panel
```
White card, padding 22px:
  - "Recent Activity" Poppins SemiBold 16px
  - List of activity rows (max 8 shown):
    Each row:
      Colored dot (green/amber/blue)
      + Activity text (Inter Medium 13.5px)
      + Timestamp (Inter Regular 12px #6B6B6B)
  Activity types:
    Green dot: verification approved,
               booking completed
    Amber dot: dispute filed, account flagged
    Blue dot:  new verification submitted,
               new user registered
```

### Pending Verifications Panel
```
White card, padding 22px:
  - "Pending Verifications" Poppins SemiBold 16px
  - List of up to 5 pending workers:
    Each row:
      Circular avatar (green tint placeholder)
      + Worker name (Inter SemiBold 13.5px)
      + Category + submission time
        (Inter Regular 12px #6B6B6B)
  - "View All" link at bottom
    → Worker Verification screen
```

---

## Screen 3: Worker Verification (Queue)

**Purpose:** Admin reviews and processes worker verification document submissions.

### Layout
```
[Topbar: "Worker Verification"]
│
├── Filter bar:
│   [Search by name/email]
│   [Status: All / Pending / Approved / Rejected]
│   [Date range picker]
│
└── Table of verification submissions
    Columns:
    Worker Name | Barangay | Email |
    Submitted | Time Elapsed | Status | Action
```

### Table Rows
```
Each row:
  - Worker avatar + name
  - Barangay name
  - Email
  - Submission date
  - Time elapsed (e.g. "2 hours ago")
  - Status badge:
    Pending  → amber pill
    Approved → green pill
    Rejected → red pill
  - "Review" button → opens Document Review Modal
```

### Document Review Modal / Detail View
```
Full-page view (or large modal) showing:

TOP SECTION:
  Worker info card:
    Avatar | Name | Email | Mobile | Barangay
    Registration date | Current status

DOCUMENT VIEWER SECTION:
  4 document panels side by side (or 2x2 grid):
    1. Government ID
       [Image thumbnail — click to full screen]
       Status badge per document
    2. Barangay Certificate / Clearance
       [Image thumbnail — click to full screen]
    3. Selfie Holding ID
       [Image thumbnail — click to full screen]
    4. Skill Certificate (if submitted)
       [Image thumbnail — click to full screen]
       "Not submitted" placeholder if missing

FULL SCREEN IMAGE VIEWER:
  Opens on document click
  Supports zoom in/out
  Navigation arrows (prev/next document)
  Close button

ACTION SECTION (bottom):
  [Approve button — green]
  [Reject button — red]
  [Request Resubmission button — amber]

  If Reject or Request Resubmission selected:
    Remarks textarea appears (required)
    "Submit Decision" button
```

### States
```
Empty state: "No pending verifications.
             All submissions have been reviewed."
             + checkmark illustration

Loading: Skeleton rows in table
```

---

## Screen 4: User Account Management

**Purpose:** Admin views, searches, and manages all registered user accounts.

### Layout
```
[Topbar: "User Accounts"]
│
├── Filter bar:
│   [Search by name or email]
│   [Role: All / Client / Worker / Admin]
│   [Status: All / Active / Suspended]
│   [Barangay dropdown]
│
└── Users table
    Columns:
    User | Role | Barangay | Registered |
    Status | Verification (workers) | Actions
```

### Table Rows
```
Each row:
  - Avatar + name + email (stacked)
  - Role badge (Client/Worker/Admin)
  - Barangay name
  - Registration date
  - Status badge (Active green / Suspended red)
  - Verification badge (workers only):
    Verified / Pending / Unverified / Revoked
  - Actions dropdown:
    "View Profile"
    "Suspend Account" (if active)
    "Reactivate Account" (if suspended)
    "Process Deletion Request" (if requested)
```

### User Profile Detail View
```
Opened when "View Profile" clicked:
  Full page or slide-over panel

HEADER CARD:
  Large avatar | Name | Role badge
  Email | Mobile | Barangay
  Registered date | Last login date
  Status badge | Verification badge (workers)

WORKER SECTION (workers only):
  Trust tier badge
  Verification status + verified date
  Average rating + review count
  Completed jobs count
  Active job posts count
  [View Verification Documents] button

ACTIVITY SUMMARY:
  Total bookings (as client or worker)
  Completed bookings
  Filed reports count
  Received reports count

BOOKING HISTORY (recent 5):
  Booking code | Other party | Category |
  Date | Status

ACTION BUTTONS (bottom):
  [Suspend Account] — red outlined
  [Reactivate Account] — green (if suspended)
```

---

## Screen 5: Job Post Oversight

**Purpose:** Admin monitors all worker job posts on the platform.

### Layout
```
[Topbar: "Job Posts"]
│
├── Filter bar:
│   [Search by worker name or post title]
│   [Category dropdown]
│   [Barangay dropdown]
│   [Status: All / Active / Inactive]
│   [Verification: All / Verified / Unverified]
│
└── Job posts table
    Columns:
    Worker | Category | Post Title |
    Rate | Barangay | Posted | Status | Actions
```

### Table Rows
```
Each row:
  - Worker avatar + name + verification badge
  - Service category tag
  - Post title (truncated if long)
  - Rate display
    (e.g. "From ₱300/session")
  - Worker's barangay
  - Posted date
  - Status badge (Active green / Inactive gray)
  - Actions:
    "View Detail"
    "Deactivate Post" (if active)
```

### Job Post Detail View
```
Slide-over panel or modal:
  Worker info card (photo, name, badge)
  Post Title (Poppins SemiBold)
  Service Category tag
  Description (full text)
  Rate amount + rate type
  Availability status
  Posted date
  Total bookings generated from this post
  [Deactivate Post] button — red outlined
  [View Worker Profile] link
```

---

## Screen 6: Booking Monitoring

**Purpose:** Admin oversees all bookings across the platform.

### Layout
```
[Topbar: "Booking Monitoring"]
│
├── Filter bar:
│   [Search by booking code, client, or worker]
│   [Status dropdown]
│   [Category dropdown]
│   [Date range picker]
│
└── Bookings table
    Columns:
    Booking Code | Client | Worker |
    Category | Scheduled | Status | Created | Actions
```

### Table Rows
```
Each row:
  - Booking code (e.g. HB-2026-00001)
    monospace font, clickable
  - Client avatar + name
  - Worker avatar + name
  - Service category
  - Scheduled date and time
  - Status badge (color-coded):
    Pending  → gray
    Accepted → blue
    Active   → green pulsing
    Completed → solid green
    Cancelled → red
  - Created date
  - "View Detail" button
```

### Booking Detail View
```
Full page:

HEADER:
  Booking code (large) | Status badge
  Created date | Last updated

TWO-COLUMN LAYOUT:
  Left column:
    Client card:
      Avatar, name, barangay, email, mobile
    Worker card:
      Avatar, name, barangay, email,
      verification badge

  Right column:
    Booking Info card:
      Service category
      Job post title (if linked)
      Starting rate
      Scheduled date and time
      Notes from client
      started_at (if applicable)
      completed_at (if applicable)

STATUS TIMELINE:
  Horizontal stepper:
  Pending → Accepted → Active → Completed
  (shows timestamps at each step)

MESSAGES LOG:
  Read-only chat view
  Shows all messages exchanged
  for this booking

REPORTS SECTION:
  Any reports filed related to this booking
  Each report: reason, filed by, status

ACTIONS (bottom):
  [Force Cancel Booking] — red
  (only if status is not completed/cancelled)
  Requires reason input before confirming
```

---

## Screen 7: Reports & Dispute Management

**Purpose:** Admin reviews and resolves disputes filed by clients or workers.

### Layout
```
[Topbar: "Reports & Disputes"]
│
├── Status tabs:
│   [Under Review (X)] [Resolved] [Dismissed]
│   (Under Review tab shows count badge)
│
├── Filter bar:
│   [Search by report ID or user name]
│   [Reason category dropdown]
│   [Date range picker]
│
└── Reports table
    Columns:
    ID | Filed By | Reported User |
    Reason | Booking | Status | Date | Actions
```

### Table Rows
```
Each row:
  - Report ID
  - Filed by (avatar + name)
  - Reported user (avatar + name)
  - Reason (colored tag):
    No-show, Misconduct, etc.
  - Booking code (if linked, clickable)
  - Status badge
  - Date filed
  - "Review" button → Report Detail View
```

### Report Detail View
```
Full page:

REPORT HEADER:
  Report ID | Status badge | Date filed

TWO PARTIES SECTION:
  Side by side cards:
  [Filed By]          [Reported User]
  Avatar, name        Avatar, name
  Role badge          Role badge + trust tier
  Profile link        Profile link
  Prior reports       Prior reports count
  count filed         received

REPORT CONTENT:
  Reason category (large colored tag)
  Description (full text, scroll if long)

EVIDENCE SECTION:
  Photo evidence grid
    (click to full-screen image viewer)
  "No evidence submitted" if empty

LINKED BOOKING SECTION (if applicable):
  Booking code + status
  Service category + scheduled date
  "View Full Booking" link

CHAT LOG SECTION:
  Read-only message history
  from the linked booking

RESOLUTION SECTION (if Under Review):
  Resolution Action dropdown:
    Warning Issued
    Account Suspended
    Verification Revoked
    No Action (Dismiss)
  Admin Remarks textarea (required)
  [Resolve Report] button — green

RESOLUTION RECORD (if already resolved):
  Action taken (colored tag)
  Admin remarks
  Resolved by (admin name)
  Resolved at (timestamp)
```

---

## Screen 8: Ratings & Reviews Oversight

**Purpose:** Admin monitors and moderates all ratings and reviews on the platform.

### Layout
```
[Topbar: "Ratings & Reviews"]
│
├── Filter bar:
│   [Search by reviewer or reviewed name]
│   [Score filter: All / 5★ / 4★ / 3★ / 2★ / 1★]
│   [Direction: All / Client→Worker / Worker→Client]
│   [Date range picker]
│
└── Reviews table
    Columns:
    Reviewer | Reviewed | Score |
    Comment | Booking | Date | Actions
```

### Table Rows
```
Each row:
  - Reviewer (avatar + name)
  - Reviewed (avatar + name)
  - Star score (filled stars visual)
  - Comment preview (truncated)
  - Booking code (clickable)
  - Date submitted
  - "Remove" button → confirmation dialog
    (requires removal reason)
```

---

## Screen 9: Platform Settings

**Purpose:** Admin configures platform-wide settings and content.

### Layout
```
[Topbar: "Platform Settings"]
│
├── SECTION: Service Categories
│   List + [Add Category] button
│
├── SECTION: Report Reason Categories
│   List + [Add Reason] button
│
├── SECTION: Notification Templates
│   List of editable templates
│
└── SECTION: Platform Announcements
    [Post New Announcement] button
    + History of past announcements
```

### Service Categories Section
```
White card:
  Table rows:
    Icon | Category Name | Status | Actions
    (Edit name/icon, Activate/Deactivate)
  [+ Add Category] button at top right
  Add Category modal:
    Name field (required)
    Icon identifier field
    [Save] button
```

### Report Reason Categories Section
```
White card:
  List of current reasons with:
    Reason label | Status | Edit | Deactivate
  [+ Add Reason] button
  Add modal: Reason label field + Save
```

### Notification Templates Section
```
White card:
  List of template types:
    Verification Approved
    Verification Rejected
    Booking Accepted
    Booking Declined
    Booking Completed
    Report Resolved
    Trust Tier Updated
  Each row has [Edit] button
  Edit modal:
    Template title field
    Template body textarea
    [Save Changes] button
```

### Platform Announcements Section
```
White card:
  [Post New Announcement] button (top right)
  New Announcement modal:
    Title field
    Body textarea
    Expiry date (optional date picker)
    [Post] button

Past Announcements table:
  Title | Posted date | Expiry | Actions
  (View / Delete past announcements)
```

---

## Screen 10: Audit Log

**Purpose:** Read-only record of all admin actions.

### Layout
```
[Topbar: "Audit Log"]
│
├── Filter bar:
│   [Search by admin name or action]
│   [Admin dropdown — filter by specific admin]
│   [Action type dropdown]
│   [Target type: User/Booking/Verification/Post]
│   [Date range picker]
│
└── Audit log table
    Columns:
    Timestamp | Admin | Action |
    Target Type | Target ID | Details
```

### Table Rows
```
Each row:
  - Timestamp (date + time, precise)
  - Admin avatar + name
  - Action label
    (e.g. "approved_worker_verification")
  - Target type tag
    (User / WorkerProfile / Booking /
     JobPost / Report)
  - Target ID (clickable → opens target)
  - Details summary
    (e.g. "Verified: Liza Dimaano")
```

### States
```
Read-only — no edit or delete actions
Empty state: "No audit logs found for
             the selected filters."
```

---

## Screen 11: Account Settings

**Purpose:** Admin manages their own account.

### Layout
```
[Topbar: "Account Settings"]
│
├── SECTION: Profile
│   [Profile photo] [Name] [Email] [Mobile]
│   [Save Changes button]
│
├── SECTION: Security
│   Change Password row
│   Two-Factor Authentication row + toggle
│   Linked Accounts row (info only)
│
└── SECTION: Login Activity
    List of recent login sessions
    Each row: date, device, IP, [Log out] button
```

---

## Shared UI Components (Web Panel)

### Status Badges
```
Pending          → amber background, amber text
Approved/Active  → green background, green text
Rejected/Banned  → red background, red text
Under Review     → blue background, blue text
Inactive/Dismissed → gray background, gray text
```

### Action Buttons
```
Primary action:    Green bg, white text
Destructive:       Red outlined, red text
Secondary:         Gray outlined, dark text
Confirm dialog:    Always shown before destructive
                   actions (suspend, revoke, cancel)
```

### Empty States
```
Each table/list has an empty state:
  Illustration (simple icon or image)
  Title text
  Subtitle explaining why empty
  Optional action button
```

### Loading States
```
Tables:   Skeleton rows (animated gray bars)
Cards:    Skeleton card with shimmer effect
Buttons:  Spinner replaces text while loading
```

### Pagination
```
All tables paginated at 20 rows per page
Shows: "Showing X–Y of Z results"
Previous / Next buttons
Page number selector
```

### Confirmation Dialogs
```
All destructive actions require confirmation:
  Modal with:
    Action title ("Suspend this account?")
    Warning description
    Reason input (for most actions)
    [Confirm] button (red for destructive)
    [Cancel] button
```

---

## Color Reference (Web Panel)

```
Sidebar bg:         #1D7A37 (dark green)
Primary green:      #2E9B2E
Light green tint:   #E8F5E9
Page background:    #F5F5F5
Card white:         #FFFFFF
Topbar white:       #FFFFFF
Border:             #E0E0E0
Text primary:       #1A1A1A
Text secondary:     #6B6B6B
Text hint:          #9E9E9E
Warning amber:      #F9A825
Warning bg:         #FFF8E1
Error red:          #D32F2F
Error bg:           #FFEBEE
Info blue:          #1565C0
Info bg:            #E3F2FD
```

## Typography Reference (Web Panel)

```
Headings:   Poppins SemiBold / Bold
Body text:  Inter Regular / Medium
Labels:     Inter SemiBold
Buttons:    Poppins SemiBold
Captions:   Inter Regular, smaller size
```
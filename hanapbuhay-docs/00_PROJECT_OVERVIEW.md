# HanapBuhay — Project Overview

**Document Type:** Master Project Reference
**Last Updated:** September 2026
**Status:** Active Development

---

## What is HanapBuhay?

HanapBuhay is a community-based skilled worker marketplace — a mobile app (Flutter) and admin web panel (React) that connects residents of Trinidad, Bohol with local skilled workers for home services, repairs, tutoring, caregiving, and other skilled labor.

The name "HanapBuhay" comes from the Filipino word for "livelihood" — reflecting the platform's mission of helping community members find work and find workers within their own neighborhood.

The platform's core trust layer is barangay-document verification — every worker must submit a valid government ID and barangay certificate before being fully verified, giving clients confidence in who they are hiring.

---

## Project Scope

```
Municipality:  Trinidad, Bohol ONLY
Barangays:     20 barangays of Trinidad, Bohol
               (seeded in database with OSM
               center coordinates)
Target Users:  Residents of Trinidad, Bohol only
               (both clients and workers must
               register with a Trinidad barangay)
Stage:         Capstone project — pilot system
               for one municipality
```

---

## The Core Problem It Solves

In Trinidad, Bohol, informal skilled workers (electricians, plumbers, tutors, caregivers, barbers, aircon technicians, etc.) are typically hired through word-of-mouth or Facebook groups. This means:

- No way to verify if someone is trustworthy before letting them into your home
- No accountability if work is poor or someone does not show up
- Workers struggle to find clients beyond their existing personal network
- No organized way to compare rates or skills

HanapBuhay solves this by giving workers a platform to post their services with rates, and giving clients a verified, organized feed of available workers nearby.

---

## Project Name History

```
Original proposed name: Katulong Connect
Current official name:  HanapBuhay
Status: Name change approved by adviser
All documentation and code uses HanapBuhay
```

---

## Team Structure

| Role | Person | Tools | Responsibility |
|---|---|---|---|
| Project Manager | Otrebla (Alberto) | Laravel + Amazon Q | Backend API, project coordination |
| UI/UX Designer | TBD | Figma + Google Stitch | Screen designs, design system |
| App Developer | TBD | Flutter + Gemini (Android Studio) | Mobile app (Client + Worker) |
| Web Developer | TBD | React + Laravel | Admin web panel |
| QA Tester | TBD | Manual testing | Functional + visual QA |

---

## Tech Stack — Locked In, Do Not Change

| Layer | Technology | Notes |
|---|---|---|
| Mobile App | Flutter (Dart) | Single app, role-based |
| Backend API | Laravel 13 (PHP 8.5) | REST API only, no Blade views |
| Database | MySQL | Via Laragon locally |
| API Auth | Laravel Sanctum | Bearer tokens |
| Google OAuth | Laravel Socialite | Server-side Google token verification |
| Email | Laravel Mail + Brevo | Free SMTP, email OTP only |
| Push Notifications | Firebase FCM | Phase 3, deferred |
| Real-Time | Laravel Echo + Soketi | WebSocket for live location tracking |
| Maps | Google Maps Flutter | Live tracking + static barangay pins |
| Admin Web Panel | React.js | Consumes same Laravel API |
| Local Dev Server | Laragon (MySQL only) + php artisan serve | Apache not used |
| Code Gen (Backend) | Amazon Q (VS Code) | PM uses this |
| Code Gen (Mobile) | Gemini Code Assist (Android Studio) | App Dev uses this |
| Version Control | Git + GitHub | Org: github.com/hanapbuhay-ph |

---

## Repositories

```
GitHub Organization: github.com/hanapbuhay-ph

Backend API:   github.com/hanapbuhay-ph/hanapbuhay-api
               Language: PHP / Laravel 13
               Branch: develop (active development)
               Branch: main (protected, PR only)

Mobile App:    github.com/hanapbuhay-ph/hanapbuhay-app
               Language: Dart / Flutter
               Branch: develop (active development)
               Branch: main (protected, PR only)

Admin Web:     github.com/hanapbuhay-ph/hanapbuhay-web
               Language: JavaScript / React
               Status: Not started yet
```

---

## User Roles

| Role | Platform | Description |
|---|---|---|
| Client | Mobile App | Browses worker feed, books services, tracks workers, leaves reviews |
| Worker | Mobile App | Posts job listings with rates, receives bookings, submits verification |
| Admin | Web Panel only | Verifies workers, manages disputes, oversees platform |

### Single App, Two Roles

HanapBuhay is one Flutter app with role-based experiences — similar to how Upwork works. After login, the app detects the user's role and shows the appropriate UI and navigation. Role is chosen during registration and is not changeable after the fact.

---

## Key Architecture Decisions

### 1. Worker Job Posts (Feed Model)

Workers actively post job listings (one per service category) that appear in the client's home feed. This replaces a purely search-based discovery model.

```
Worker posts: "Aircon Cleaning — From ₱300/session"
Client sees this in their feed
Client taps the post → views the full post and its images
Client taps the worker identity → views the worker profile
Client taps "Book" on the full post
Booking form opens pre-filled
```

Each job post may include up to 10 ordered service images. The first image is
used as the feed-card preview, while the full post displays all images in a
vertically scrollable list. Post media belongs to the job post, not the
worker profile.

### 2. Distance Computation

```
Method: Haversine formula
Basis:  Client's registered barangay center
        coordinates vs Worker's registered
        barangay center coordinates
Result: Shown on feed cards as "~X.X km · Barangay Name"
GPS:    NOT used for browsing/search
        Only used during live tracking
```

### 3. Live Location Tracking (Map-Driven)

```
Trigger:     User taps "I'm on my way" on map screen
Who tracks:  Either party — client OR worker
             (whoever is traveling)
             Both can track simultaneously if meeting
             in between
Destination: Other party's registered
             barangay center coordinates (static pin)
Stops when:  Traveling party taps "I've Arrived"
Technology:  WebSocket via Laravel Echo + Soketi
```

### 4. Barangay-Based Location

```
Registration: Both Client and Worker select
              their barangay from a dropdown
              of 20 Trinidad barangays
Usage:        Distance computation (Haversine)
              Live tracking destination pin
              Feed sorting (nearest first)
No GPS:       Required for browsing or registration
              Only required for live tracking
              (user-initiated, not automatic)
```

### 5. Rate Types for Job Posts

```
Workers set a starting rate per job post.
Rate type options:
  - Per Hour    (e.g. ₱150/hr)
  - Per Day     (e.g. ₱500/day)
  - Per Week    (e.g. ₱2,500/wk)
  - Per Month   (e.g. ₱8,000/mo)
  - Per Session (e.g. From ₱200/session)
                → for barbers, massage, aircon cleaning
  - Per Project (e.g. From ₱5,000/project)
                → for carpentry, renovation, custom work

Display:  "From ₱X/[period]" on all cards
          Final price negotiated via in-app chat
          Platform does NOT process payment
```

### 6. Trust and Verification System

```
Verification Status:
  unverified → pending → approved → rejected

Trust Tier (separate from verification):
  verified  → baseline after admin approval
  trusted   → admin-awarded after good track record
  flagged   → admin sets after upheld report
  revoked   → banned, posts hidden, cannot book

Feed Visibility:
  verified  → 🛡️ green badge, ranked higher
  trusted   → ⭐ blue badge, ranked highest
  unverified → ⚠️ amber badge, ranked lower but visible
  flagged/revoked → hidden from feed entirely

Booking Unverified Worker:
  → Warning modal before booking form opens
  → "Book Anyway" or "Find Verified Worker"
```

### 7. No Payment Processing

```
Out of scope entirely.
All transactions handled offline/cash.
Platform shows rates as reference only.
No in-app wallet, escrow, or payment gateway.
```

### 8. Email OTP (Not SMS)

```
Reason: SMS OTP costs money per message
Solution: Email OTP via Brevo (free SMTP)
Applies to: Registration verification,
            Password reset
Google sign-in: No OTP needed
                (Google already verified email)
Mobile number: Collected but NOT OTP-verified
               (contact/coordination purpose only)
```

### 9. Authentication Flow

```
Manual path:
Register → Email OTP → Login → Home

Google path:
Google Sign-In → (new user) Role Selection
→ Complete Profile (mobile + barangay)
→ Home

Tokens: Laravel Sanctum Bearer tokens
        Stored securely on device
```

---

## App Navigation Structure

### Client Bottom Navigation (5 tabs)

```
🏠 Home        → Feed of worker job posts
📅 Bookings    → Booking history and active bookings
💬 Messages    → In-app chat inbox (per booking)
🔔 Notifications → Notification center
👤 Profile     → Account settings and profile
```

### Worker Bottom Navigation (5 tabs)

```
🏠 Home        → Worker dashboard
                 (active posts + incoming requests)
💼 Jobs        → Booking schedule and history
💬 Messages    → In-app chat inbox (per booking)
🔔 Notifications → Notification center
👤 Profile     → Profile, portfolio, verification,
                 manage posts
```

---

## Feed Filter System (Client Home)

### Quick Filter (always visible, upper right of feed)

```
[ All ] [ Verified ] [ Unverified ]
Three tappable chips — active one in green
Controls which verification tier is shown
```

### Advanced Filters (⚙️ button → bottom sheet)

```
Service Category  (multi-select chips)
Barangay          (dropdown, 20 Trinidad barangays)
Rate Type         (All / Hourly / Daily / Weekly /
                  Monthly / Per Session / Per Project)
Availability      (All / Available Now)
```

### Feed Sort Order

```
1. Distance (nearest first — primary)
2. Verification tier (trusted → verified → unverified)
3. Rating (higher rated first within same tier)
```

---

## Service Categories

```
Electrical Works
Plumbing
House Cleaning
Tutoring
Aircon Repair & Cleaning
Carpentry
Painting
Masonry
Gardening & Landscaping
Cooking & Catering
Caregiving
Laundry
Welding
Auto Repair & Mechanic
Computer Repair & IT
Barbering & Hairstyling
Massage & Wellness
(Admin can add more via web panel)
```

---

## Design System

```
Primary Font:   Poppins (headings, buttons)
Secondary Font: Inter (body, labels, inputs)

Colors:
  Primary Green:    #2E9B2E
  Dark Green:       #1D7A37
  Light Green:      #E8F5E9
  Background:       #F5F5F5
  Card White:       #FFFFFF
  Text Primary:     #1A1A1A
  Text Secondary:   #6B6B6B
  Text Hint:        #9E9E9E
  Border:           #E0E0E0
  Success:          #2E7D32
  Warning:          #F9A825
  Error:            #D32F2F
  Info:             #1565C0

Style Reference:  Tarsi app
  (clean cards, generous whitespace,
  modern sans-serif, card-based layout)

Logo:  Filipino boy mascot with salakot hat
       holding magnifying glass, house icon
       outline behind, green gradient background
       "H" letter on salakot hat
```

---

## Development Phases

```
Phase 1 — Foundation (Auth + Users)
  Barangays migration + seeder ✅
  Users table migration ✅
  OTP codes table ✅
  Worker profiles table ✅
  Register endpoint ✅
  Email OTP verification ✅
  Login endpoint (next)
  Logout endpoint
  Google OAuth
  Forgot/Reset password
  Get authenticated user

Phase 2 — Core Features
  Service categories seeder
  Job posts migration + CRUD
  Worker search by category
  Client feed with Haversine distance
  Verification document upload
  Booking CRUD
  Booking status transitions
  Map screen + live location tracking

Phase 3 — Supporting Features
  Ratings and reviews
  Reports and disputes
  In-app messaging
  Push notifications (FCM)
  Notifications center
  FCM device token management

Phase 4 — Admin Web Panel
  Admin auth
  Verification queue
  User management
  Booking oversight
  Reports resolution
  Audit logs
  Platform settings

Phase 5 — Polish + Testing
  API response consistency
  Error handling improvements
  Postman collection finalization
  QA testing all flows
  Railway deployment preparation
```

---

## Local Development Setup

```
PM Laptop (Backend + API):
  Laravel 13 runs via: php artisan serve
  Available at: http://127.0.0.1:8000
  Database: MySQL via Laragon
             (only MySQL used, not Apache)
  PHP: 8.5 via php.new / Herd Lite
  Composer: via php.new / Herd Lite

App Dev Laptop (Flutter):
  Emulator connects to backend via:
  http://10.0.2.2:8000/api
  (Android emulator special alias for
  host machine localhost)

  Physical device on same WiFi:
  http://[PM local IP]:8000/api

  Remote (different network):
  Use ngrok → ngrok http 8000
  Share the https://xxx.ngrok-free.app URL

Web Dev Laptop (React):
  Same WiFi: http://[PM local IP]:8000/api
  Remote: ngrok URL

API Base URL constant in Flutter:
  lib/config/app_constants.dart
  → ApiConstants.baseUrl
  Change this one value to switch
  between local, ngrok, and Railway
```

---

## Future Deployment Plan

```
Current: Local only (Laragon + php artisan serve)
Future:  Railway.app (when ready for live demo)
         Laravel + MySQL both on Railway
         Free tier sufficient for capstone demo
         Deploy only when all core features stable
```

---

## Important Constraints and Limitations

```
1. No automated government database verification
   Admin manually reviews submitted documents

2. No real-time GPS for browsing
   Only barangay center coordinates used
   for distance computation during search

3. No payment processing
   All transactions handled offline/cash

4. No SMS OTP
   Email OTP only (cost constraint)

5. Scope limited to Trinidad, Bohol
   No multi-municipality support
   No freeform address input

6. Worker role not switchable after registration
   Future enhancement only

7. Firebase FCM deferred to Phase 3
   kreait/laravel-firebase has PHP 8.5
   compatibility issues at time of development

8. One job post per service category per worker
   Worker edits existing post instead of
   creating duplicate in same category

9. Live tracking is user-initiated only
   No automatic background GPS tracking
   User must tap "I'm on my way" to start
```

---

## Document Index

```
00_PROJECT_OVERVIEW.md      ← This file
01_WEB_FUNCTIONALITIES.md   ← Admin web panel features
02_APP_FUNCTIONALITIES.md   ← Mobile app features
03_WEB_WIREFRAME.md         ← Admin web UI specs
04_APP_WORKER_WIREFRAME.md  ← Worker mobile UI specs
05_APP_CLIENT_WIREFRAME.md  ← Client mobile UI specs
06_DATABASE_SCHEMA.md       ← All table definitions
07_API_ENDPOINTS.md         ← Full API contract
08_BACKEND_STATUS.md        ← What is built vs pending
```
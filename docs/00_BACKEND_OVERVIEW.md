# HanapBuhay — Backend Overview
**For: Laravel Developer (PM)**
**Last Updated: 2026**

---

## What is This Backend?

This is the **centralized Laravel REST API** that
serves as the single backend for both:

- **hanapbuhay-app** — Flutter mobile app
  (Client and Worker roles)
- **hanapbuhay-web** — React admin web panel

Neither Flutter nor React touches the database
directly. All data flows through this Laravel API.

---

## Project Scope

```
Municipality:  Trinidad, Bohol ONLY
Barangays:     31 barangays of Trinidad, Bohol
               (seeded in database)
Workers:       Must be Trinidad residents
Clients:       Must be Trinidad residents
Language:      Filipino community context
               (Taglish-friendly error messages)
```

---

## Tech Stack

| Layer | Technology | Purpose |
|---|---|---|
| Framework | Laravel 13.x | Core API framework |
| Language | PHP 8.5 | Server-side language |
| Database | MySQL 8.x | Primary data store |
| Auth Tokens | Laravel Sanctum | API token issuance |
| Google Auth | Laravel Socialite | Google OAuth handling |
| Email | Laravel Mail + Brevo | Free email OTP |
| Push Notifs | Firebase Cloud Messaging | Free mobile push |
| Real-Time | Laravel Echo + Soketi | WebSocket for live tracking |
| File Storage | Laravel Storage (local) | Document/photo uploads |
| Dev Server | Laragon (local) | Local development |
| Prod Server | Railway (later) | Final deployment |
| API Testing | Postman | Endpoint testing |
| Code Gen | Amazon Q | AI-assisted coding |

---

## Development Environment

```
Tool:     Laragon (Windows) for Apache + MySQL
          php.new / Herd Lite for PHP + Composer
URL:      http://hanapbuhay.test/api
          OR
          http://localhost/hanapbuhay-api/public/api

Database: MySQL via Laragon
          GUI: HeidiSQL (bundled with Laragon)
          OR phpMyAdmin (bundled with Laragon)

PHP:      8.5 (via php.new / Herd Lite)
          Installed at:
          C:\Users\iza\.config\herd-lite\bin
Composer: Installed via php.new
Laravel:  Installer 5.31.1 (via php.new)
```

### How Teammates Connect to Your Local Laravel

```
Flutter Dev (Android Emulator):
  http://10.0.2.2/hanapbuhay-api/public/api
  (10.0.2.2 = host machine on Android emulator)

Flutter Dev (Physical Android Device):
  http://YOUR_LOCAL_IP/hanapbuhay-api/public/api
  (e.g., http://192.168.1.5/hanapbuhay-api/public/api)
  Both must be on same WiFi network.

Web Dev (React on same or different machine):
  http://YOUR_LOCAL_IP/hanapbuhay-api/public/api
  Same WiFi network required.

Remote teammates (not on same WiFi):
  Use ngrok for a temporary public URL:
  ngrok http 80
  → gives https://abc123.ngrok.io
  → share this URL with teammates
  → URL changes each session (free tier)
```

---

## Repository

```
GitHub Org:  github.com/hanapbuhay
Your repo:   github.com/hanapbuhay/hanapbuhay-api
Branch flow: feature/* → develop → main
```

---

## Key Design Decisions

### 1. Distance Computation
```
Method: Haversine formula
Basis:  Client's registered barangay center
        coordinates vs Worker's registered
        barangay center coordinates
Usage:  Shown on worker cards during search
        as "~X.X km · Barangay Name"
No real-time GPS used for browsing
```

### 2. Live Location Tracking
```
Triggered by: User tapping "I'm on my way"
              button on the map screen
Who tracks:   The party who is traveling
              (either client or worker)
Destination:  Other party's registered
              barangay center coordinates
              (static pin on map)
Stops when:   Traveling party taps
              "I've Arrived"
Technology:   WebSocket via Laravel Echo
              + Soketi (free, self-hosted)
```

### 3. Map Screen (Both Parties See)
```
Always visible during active booking
Shows:
  - Client's barangay pin (blue)
  - Worker's barangay pin (green)
  - Distance between them
  - "I'm on my way" button (triggers tracking)
  - "I've Arrived" button (stops tracking)
  - Live moving pin for whoever is tracking
Both can travel simultaneously if needed
```

### 4. Barangay Scope
```
Only Trinidad, Bohol barangays
in the barangays table
Registration requires selecting
a Trinidad barangay
No freeform address input
```

### 5. Verification
```
Admin-assisted manual review
Worker submits: Gov ID + Barangay cert
               + Selfie with ID
Admin reviews via web panel
No automated government DB integration
```

### 6. Authentication
```
Manual: Email + Password
        → Email OTP verification (free)
        → Via Brevo SMTP (free tier)
Google: Google OAuth via Socialite
        → No OTP needed (Google verifies email)
        → Mobile number still collected
          (not OTP-verified)
Tokens: Laravel Sanctum Bearer tokens
```

### 7. Payment
```
Out of scope entirely.
No payment processing.
No in-app wallet.
Transactions handled offline/cash.
```

---

## User Roles

| Role | Access | Platform |
|---|---|---|
| Client | Book workers, track, rate | Flutter mobile app |
| Worker | Offer services, verify, track | Flutter mobile app |
| Admin | Manage platform, verify workers | React web panel |

---

## Reference Documents (in /docs folder)

| File | Purpose |
|---|---|
| `00_BACKEND_OVERVIEW.md` | This file |
| `01_LOCAL_SETUP_GUIDE.md` | Laragon + Laravel setup |
| `02_DATABASE_SCHEMA.md` | All tables and relationships |
| `03_API_ENDPOINTS.md` | All API routes with request/response |
| `04_AMAZON_Q_GUIDE.md` | How to use Amazon Q for Laravel |
| `05_FEATURE_SPECS.md` | Feature-by-feature build specs |
| `06_DEPLOYMENT_GUIDE.md` | Laragon local + ngrok + Railway later |

---

## Development Phases

```
PHASE 1 — Foundation (Week 1)
  Database setup + migrations
  Barangay seeder (31 Trinidad barangays)
  Auth endpoints (register, login, logout,
  Google auth, email OTP)

PHASE 2 — Core Features (Week 2-3)
  Worker verification endpoints
  Worker search + distance computation
  Booking CRUD endpoints
  File upload (ID documents, portfolio)

PHASE 3 — Real-Time (Week 3-4)
  Soketi WebSocket server setup
  Live location tracking endpoints
  Push notification setup (FCM)

PHASE 4 — Supporting Features (Week 4-5)
  Ratings and reviews
  Reports and disputes
  In-app messaging
  Notifications

PHASE 5 — Polish + Testing (Week 5-6)
  API response consistency cleanup
  Error handling improvements
  Postman collection finalization
  Prepare for Railway deployment
```

# HanapBuhay API

**Backend REST API for the HanapBuhay platform** — a community-based skilled worker marketplace connecting residents of Trinidad, Bohol with local service providers.

HanapBuhay (*Filipino for "livelihood"*) lets clients browse verified workers, book services, track jobs in real time, and communicate — all within their barangay community. Workers post their services with rates and receive bookings directly through the app.

> **Scope:** Trinidad, Bohol only — all 20 barangays are seeded with coordinates. This is a capstone project pilot system.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3+ |
| Database | MySQL |
| Authentication | Laravel Sanctum (Bearer tokens) |
| Google OAuth | Laravel Socialite |
| Push Notifications | Firebase FCM via `kreait/laravel-firebase` |
| Real-Time / WebSocket | Laravel Echo + Soketi (`pusher/pusher-php-server`) |
| File Storage | Laravel Storage (public disk) |
| Testing | Pest PHP |
| Email | Laravel Mail (Brevo SMTP in production) |

---

## Prerequisites

Before setting up locally, make sure you have:

- **PHP 8.3+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`
- **Composer 2+**
- **MySQL 8+** (via Laragon recommended)
- **Node.js 18+** (only needed if running Soketi locally)
- **Git**

---

## Local Setup

```bash
# 1. Clone the repository
git clone https://github.com/hanapbuhay-ph/hanapbuhay-api.git
cd hanapbuhay-api

# 2. Install PHP dependencies
composer install

# 3. Copy environment file
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Configure your .env (see Environment Variables section below)

# 6. Create the databases
#    Create two MySQL databases:
#      hanapbuhay       → main app database
#      hanapbuhay_test  → test database

# 7. Run migrations and seed the database
php artisan migrate --seed

# 8. Create the storage symlink (for public file access)
php artisan storage:link

# 9. Start the development server
php artisan serve
```

The API will be available at `http://127.0.0.1:8000/api`.

---

## Environment Variables

Copy `.env.example` to `.env` and configure the following:

### Database
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hanapbuhay
DB_USERNAME=root
DB_PASSWORD=
```

### Mail (OTP emails)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your_brevo_username
MAIL_PASSWORD=your_brevo_password
MAIL_FROM_ADDRESS=noreply@hanapbuhay.com
MAIL_FROM_NAME="HanapBuhay"
```

> For local development, set `MAIL_MAILER=log` to write emails to `storage/logs/laravel.log` instead of actually sending them.

### Google OAuth
```env
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://localhost
```

### Firebase (Push Notifications)
```env
FIREBASE_CREDENTIALS=path/to/firebase-service-account.json
```

> Download your Firebase service account JSON from the Firebase Console → Project Settings → Service Accounts.

### Soketi (WebSocket — Real-Time Tracking & Messaging)
```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=hanapbuhay
PUSHER_APP_KEY=hanapbuhay-key
PUSHER_APP_SECRET=hanapbuhay-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1
```

---

## Running the Project

### API server
```bash
php artisan serve
```

### Queue worker (required for jobs like notification dispatch)
```bash
php artisan queue:work
```

### Soketi WebSocket server (required for live tracking and real-time messaging)
```bash
# Install Soketi globally (one-time)
npm install -g @soketi/soketi

# Start Soketi using the project config
soketi start --config=soketi.json
```

---

## Testing

The test suite uses **Pest PHP** against a dedicated `hanapbuhay_test` MySQL database.

### Configure the test database
In `phpunit.xml` (already configured):
```xml
<env name="DB_DATABASE" value="hanapbuhay_test"/>
```

Create the database manually:
```sql
CREATE DATABASE hanapbuhay_test;
```

Run migrations on the test database:
```bash
php artisan migrate --env=testing --force
```

### Run the full test suite
```bash
vendor\bin\pest
```

### Run a specific test file
```bash
vendor\bin\pest tests/Feature/Booking/CreateBookingTest.php
```

**Current status:** 444 tests · 1536 assertions · 0 failures

---

## API Overview

All endpoints are prefixed with `/api`. Full contract: [`hanapbuhay-docs/07_API_ENDPOINTS.md`](hanapbuhay-docs/07_API_ENDPOINTS.md)

| Section | Base Path | Description |
|---|---|---|
| **A — Public** | `/api/ping`, `/api/barangays`, `/api/service-categories` | Health check, reference data — no auth needed |
| **B — Auth** | `/api/auth/*` | Register, OTP verify, login, Google OAuth, password reset, logout |
| **C — User Profile** | `/api/user/*` | Get profile, update, change password, sessions, FCM token |
| **D — Worker Discovery** | `/api/feed`, `/api/workers/*`, `/api/categories/*` | Client home feed, worker search, browse by category |
| **E — Job Posts** | `/api/worker/posts` | Worker CRUD for service listings |
| **F — Bookings** | `/api/bookings/*` | Full booking lifecycle — create, respond, status, cancel, rate |
| **G — Live Tracking** | `/api/bookings/{id}/tracking/*` | GPS tracking start/update/stop/show |
| **H — Ratings & Reports** | `/api/ratings`, `/api/reports` | Submit reviews, file disputes |
| **I — Messaging** | `/api/messages/*` | Chat inbox, message thread, send with attachments |
| **J — Notifications** | `/api/notifications/*` | Notification center, mark read, FCM device registration |
| **K — Admin** | `/api/admin/*` | Full admin panel: verifications, users, bookings, reports, ratings, audit logs, settings |

### Authentication
Protected endpoints require a Sanctum Bearer token:
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

### Default Admin Account (seeded)
```
Email:    admin@hanapbuhay.com
Password: Admin@1234!
```

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/        # One folder per domain (Auth, Booking, Admin, etc.)
│   ├── Middleware/         # EnsureAdmin, EnsureWorker, EnsureClient
│   └── Requests/           # Form request validation classes
├── Models/                 # Eloquent models
├── Services/               # Business logic layer (one folder per domain)
│   ├── Auth/
│   ├── Admin/
│   ├── Booking/
│   ├── Feed/
│   ├── JobPost/
│   ├── Message/
│   ├── Notification/
│   ├── Report/
│   └── Worker/
├── Events/                 # WebSocket events (LocationUpdated, NewMessage, etc.)
├── Helpers/                # DistanceHelper (Haversine formula)
├── Mail/                   # OtpMail
└── Policies/               # BookingPolicy, ReportPolicy

database/
├── migrations/             # All table migrations
├── seeders/                # BarangaySeeder, ServiceCategorySeeder, AdminUserSeeder
└── factories/              # Model factories for testing

tests/
└── Feature/                # Pest feature tests (one folder per domain)

hanapbuhay-docs/            # Project documentation
├── 00_PROJECT_OVERVIEW.md
├── 06_DATABASE_SCHEMA.md
└── 07_API_ENDPOINTS.md     # ← Full API contract
```

---

## Key Business Rules

- **Scope:** Only Trinidad, Bohol residents can register (barangay dropdown limited to 20 barangays).
- **Worker verification:** Workers submit government ID + barangay certificate before being verified. Unverified workers can still be booked (client is warned via UI).
- **Job posts:** One post per service category per worker — creating a new post in the same category soft-deletes the old one.
- **Booking lifecycle:** `pending → accepted/declined → active → completed` (or `cancelled` at any non-terminal stage).
- **Distance sorting:** The client feed sorts workers by Haversine distance between barangay centers — no real-time GPS used for the feed.
- **Account deletion:** Compliant with RA 10173 (Data Privacy Act of the Philippines) — PII is anonymised on deletion, booking records are preserved.

---

## Related Repositories

| Repo | Description |
|---|---|
| [`hanapbuhay-api`](https://github.com/hanapbuhay-ph/hanapbuhay-api) | This repository — Laravel backend |
| [`hanapbuhay-app`](https://github.com/hanapbuhay-ph/hanapbuhay-app) | Flutter mobile app (client + worker) |
| [`hanapbuhay-web`](https://github.com/hanapbuhay-ph/hanapbuhay-web) | React admin web panel |

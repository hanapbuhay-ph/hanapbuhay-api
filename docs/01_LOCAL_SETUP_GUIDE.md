# HanapBuhay — Local Setup Guide
**For: Laravel Developer (PM)**

---

> Complete every step in order.
> Do not skip any step.
> This only needs to be done once.

---

## Prerequisites

```
[ ] Windows 10 or 11
[ ] Laragon Full (latest version)
    Download: https://laragon.org/download/
    Choose: Laragon Full (includes MySQL,
    Apache — we use Laragon for web server
    and database ONLY, not for PHP)
[ ] php.new (PHP + Composer + Laravel installer)
    Already installed at:
    C:\Users\iza\.config\herd-lite\bin
    PHP 8.5.0, Composer 2.8.12,
    Laravel Installer 5.31.1  
[ ] Git installed
    Download: https://git-scm.com
[ ] VS Code installed
    Download: https://code.visualstudio.com
[ ] Amazon Q extension in VS Code
[ ] Postman installed
    Download: https://www.postman.com
```

---

## Step 1: Install and Configure Laragon

### 1.1 Install Laragon
```
1. Download Laragon Full installer
2. Run installer as Administrator
3. Default install path: C:\laragon
4. Click through installation
5. Launch Laragon
```

### 1.2 PHP is Managed by php.new (Not Laragon)
PHP is installed via php.new at:
C:\Users\iza\.config\herd-lite\bin

We do NOT use Laragon's bundled PHP.
Laragon is used ONLY for:
- Apache web server (serves .test domain)
- MySQL database

Verify PHP is working in any terminal:
php --version
→ Should show PHP 8.5.0
```

### 1.3 Enable Required PHP Extensions
```
In Laragon:
1. Right-click tray → PHP → php.ini
2. Find and uncomment (remove ;) these lines:
   extension=fileinfo
   extension=gd
   extension=intl
   extension=mbstring
   extension=openssl
   extension=pdo_mysql
   extension=zip
3. Save php.ini
4. Reload Laragon
```

### 1.4 Start Laragon Services
```
In Laragon panel:
1. Click "Start All"
2. Verify Apache and MySQL show green
```

---

## Step 2: Clone the Repository

```bash
# Open Laragon terminal (Laragon panel → Terminal)
# Navigate to Laragon's www folder
cd C:/laragon/www

# Clone the repository
git clone https://github.com/hanapbuhay/hanapbuhay-api.git

# Enter project folder
cd hanapbuhay-api

# Switch to develop branch
git checkout develop
```

---

## Step 3: Install Laravel

```bash
# Inside hanapbuhay-api folder:

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate
```

---

## Step 4: Configure Virtual Host in Laragon

Laragon auto-detects folders in www/ and
creates virtual hosts automatically.

```
1. Reload Laragon (right-click tray → Reload)
2. Your app is now accessible at:
   http://hanapbuhay-api.test
   (Laragon uses folder name as subdomain)

3. Verify by opening browser:
   http://hanapbuhay-api.test
   Should show Laravel welcome page
```

If virtual host doesn't appear:
```
Laragon tray → Apache → sites-enabled
→ Check hanapbuhay-api.test is listed
→ If not: Laragon tray → Reload
```

---

## Step 5: Create the Database

```
Option A: Using HeidiSQL (Recommended)
1. Open HeidiSQL from Laragon panel
2. Connect with:
   Host: 127.0.0.1
   User: root
   Password: (empty — Laragon default)
   Port: 3306
3. Right-click → "Create new" → "Database"
4. Name: hanapbuhay
5. Collation: utf8mb4_unicode_ci
6. Click OK

Option B: Using phpMyAdmin
1. Open phpMyAdmin from Laragon panel
   (or http://localhost/phpmyadmin)
2. Click "New" in left sidebar
3. Database name: hanapbuhay
4. Collation: utf8mb4_unicode_ci
5. Click "Create"
```

---

## Step 6: Configure .env File

Open `.env` in VS Code and update:

```env
APP_NAME=HanapBuhay
APP_ENV=local
APP_KEY=base64:xxxx (already generated)
APP_DEBUG=true
APP_URL=http://hanapbuhay-api.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hanapbuhay
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your_brevo_email@email.com
MAIL_PASSWORD=your_brevo_smtp_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@hanapbuhay.ph
MAIL_FROM_NAME="HanapBuhay"

GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://hanapbuhay-api.test/api/auth/google/callback

FIREBASE_PROJECT_ID=your_firebase_project_id
FIREBASE_SERVER_KEY=your_fcm_server_key

SOKETI_APP_ID=hanapbuhay-app
SOKETI_APP_KEY=hanapbuhay-key
SOKETI_APP_SECRET=hanapbuhay-secret
SOKETI_HOST=127.0.0.1
SOKETI_PORT=6001

BROADCAST_DRIVER=pusher
PUSHER_APP_ID=hanapbuhay-app
PUSHER_APP_KEY=hanapbuhay-key
PUSHER_APP_SECRET=hanapbuhay-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1
```

---

## Step 7: Install Laravel Packages

```bash

composer require laravel/sanctum
composer require laravel/socialite
composer require kreait/laravel-firebase
composer require pusher/pusher-php-server
composer require intervention/image:"^4.0"

Note on Laravel 13 differences:
- Sanctum is already included in Laravel 13
  by default — you may not need to install it
  separately. Check if it's already in your
  composer.json before running that line.
- intervention/image version 4.x is required
  for PHP 8.5 compatibility (version 2.x and
  earlier 3.x builds do not support PHP 8.5
  properly)
- No need to publish Sanctum separately in
  Laravel 13 — it's pre-configured

Verify Sanctum is already included:
cat composer.json | grep sanctum
If it appears → skip the sanctum install line
```

---

## Step 8: Run Migrations and Seeders

```bash
# Run all migrations (creates all tables)
php artisan migrate

# Run seeders (populates barangays table)
php artisan db:seed --class=BarangaySeeder

# Verify in HeidiSQL/phpMyAdmin:
# hanapbuhay database should have tables:
# users, barangays, worker_profiles,
# verification_documents, bookings,
# booking_tracking, service_categories,
# ratings_reviews, reports, messages,
# notifications, personal_access_tokens,
# password_reset_tokens, failed_jobs
```

---

## Step 9: Set Up Soketi (WebSocket Server)

Soketi is a free self-hosted Pusher-compatible
WebSocket server for real-time features.

```bash
# Install Soketi globally via npm
# (npm comes with Laragon)
npm install -g @soketi/soketi

# Verify installation
soketi --version

# Create soketi config file
# in your project root: soketi.json
```

Create `soketi.json` in project root:
```json
{
  "debug": true,
  "port": 6001,
  "appManager.driver": "array",
  "appManager.array.apps": [
    {
      "id": "hanapbuhay-app",
      "key": "hanapbuhay-key",
      "secret": "hanapbuhay-secret",
      "webhooks": []
    }
  ]
}
```

Run Soketi (keep this terminal open):
```bash
soketi start --config=soketi.json
```

---

## Step 10: Configure Laravel Broadcasting

```bash
# In config/broadcasting.php
# Verify 'default' is set to 'pusher'
# (our Soketi uses Pusher protocol)

# Test broadcasting is working:
php artisan tinker
# In tinker:
event(new \App\Events\TestEvent());
# Check Soketi terminal for connection logs
```

---

## Step 11: Set Up Storage

```bash
# Create storage symlink
# (allows public access to uploaded files)
php artisan storage:link

# Verify:
# http://hanapbuhay-api.test/storage/
# should be accessible
```

---

## Step 12: Verify Everything Works

```bash
# Run built-in Laravel server check
php artisan about

# Test API is responding
# Open Postman or browser:
# GET http://hanapbuhay-api.test/api/ping
# Should return: { "message": "HanapBuhay API is running" }
```

Add a quick ping route to `routes/api.php`:
```php
Route::get('/ping', function () {
    return response()->json([
        'message' => 'HanapBuhay API is running',
        'version' => '1.0.0',
        'scope' => 'Trinidad, Bohol',
    ]);
});
```

---

## Step 13: Set Up Brevo (Free Email)

```
1. Go to https://www.brevo.com
2. Sign up for free account
3. Go to: Settings → SMTP & API
4. Copy SMTP settings:
   Host: smtp-relay.brevo.com
   Port: 587
   Login: your registered email
   Password: your SMTP key
5. Paste into .env MAIL_* fields
6. Test email:
```

```bash
php artisan tinker
Mail::raw('Test from HanapBuhay', function($m) {
    $m->to('your@email.com')
      ->subject('Test Email');
});
```

---

## Step 14: Set Up Google OAuth

```
1. Go to https://console.cloud.google.com
2. Create new project: "HanapBuhay"
3. Go to: APIs & Services → Credentials
4. Click: "Create Credentials" → "OAuth client ID"
5. Application type: Web application
6. Authorized redirect URIs:
   http://hanapbuhay-api.test/api/auth/google/callback
7. Copy Client ID and Client Secret
8. Paste into .env GOOGLE_* fields
```

---

## Step 15: Initial Commit

```bash
git add .
git commit -m "Initial Laravel setup with dependencies and config"
git push origin develop
```

---

## Daily Development Commands

```bash
# Start Laragon (click Start All in panel)

# Start Soketi WebSocket server
soketi start --config=soketi.json

# Run migrations after adding new migration files
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Refresh all migrations + seeders
php artisan migrate:fresh --seed

# Clear all caches
php artisan optimize:clear

# View all registered routes
php artisan route:list

# Open Laravel REPL for quick testing
php artisan tinker

# Create new controller
php artisan make:controller Auth/LoginController

# Create new model + migration
php artisan make:model WorkerProfile -m

# Create new seeder
php artisan make:seeder BarangaySeeder

# Run specific seeder
php artisan db:seed --class=BarangaySeeder

# Create new event (for broadcasting)
php artisan make:event WorkerLocationUpdated

# View application logs
# Check: storage/logs/laravel.log
```

---

## Folder Structure After Setup

```
hanapbuhay-api/
│
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginController.php
│   │   │   │   ├── RegisterController.php
│   │   │   │   ├── GoogleAuthController.php
│   │   │   │   ├── EmailVerificationController.php
│   │   │   │   └── PasswordResetController.php
│   │   │   ├── Worker/
│   │   │   │   ├── WorkerController.php
│   │   │   │   └── VerificationController.php
│   │   │   ├── Client/
│   │   │   │   └── ClientController.php
│   │   │   ├── BookingController.php
│   │   │   ├── TrackingController.php
│   │   │   ├── RatingController.php
│   │   │   ├── ReportController.php
│   │   │   ├── MessageController.php
│   │   │   ├── NotificationController.php
│   │   │   └── Admin/
│   │   │       ├── AdminAuthController.php
│   │   │       ├── AdminVerificationController.php
│   │   │       ├── AdminUserController.php
│   │   │       ├── AdminBookingController.php
│   │   │       ├── AdminReportController.php
│   │   │       └── AdminDashboardController.php
│   │   └── Middleware/
│   │       ├── AdminOnly.php
│   │       └── EnsureEmailVerified.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Barangay.php
│   │   ├── WorkerProfile.php
│   │   ├── VerificationDocument.php
│   │   ├── ServiceCategory.php
│   │   ├── Booking.php
│   │   ├── BookingTracking.php
│   │   ├── Rating.php
│   │   ├── Report.php
│   │   ├── Message.php
│   │   └── Notification.php
│   └── Events/
│       └── WorkerLocationUpdated.php
│       └── ClientLocationUpdated.php
│
├── database/
│   ├── migrations/
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── BarangaySeeder.php
│       └── ServiceCategorySeeder.php
│
├── routes/
│   └── api.php
│
├── docs/          ← All MD files here
├── soketi.json
└── .env
```

<?php

use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminDeletionController;
use App\Http\Controllers\Admin\AdminJobPostController;
use App\Http\Controllers\Admin\AdminRatingController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWorkerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\VerificationController as AdminVerificationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Feed\FeedController;
use App\Http\Controllers\JobPost\JobPostController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Rating\RatingController;
use App\Http\Controllers\Worker\VerificationController;
use App\Http\Controllers\Worker\WorkerController;
use App\Http\Controllers\Worker\WorkerProfileController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Message\MessageController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Tracking\TrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes — no authentication required
|--------------------------------------------------------------------------
*/
Route::get('ping',               [PublicController::class, 'ping']);
Route::get('barangays',          [PublicController::class, 'barangays']);
Route::get('service-categories', [PublicController::class, 'serviceCategories']);

Route::prefix('auth')->group(function () {
    Route::post('register',        [RegisterController::class, 'register']);
    Route::post('login',           [AuthController::class, 'login']);
    Route::post('google',          [GoogleAuthController::class, 'handleGoogleToken']);
    Route::post('email/verify',    [EmailVerificationController::class, 'verify']);
    Route::post('email/resend-otp',   [AuthController::class, 'resendOtp']);
    Route::post('password/forgot',      [AuthController::class, 'forgotPassword']);
    Route::post('password/verify-otp',  [AuthController::class, 'verifyResetOtp']);
    Route::post('password/reset',       [AuthController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes — valid Sanctum token required
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout',                       [AuthController::class, 'logout']);
    Route::post('auth/google/complete-profile',      [GoogleAuthController::class, 'completeProfile']);
    Route::get('user',                               [AuthController::class, 'me']);
    Route::post('user/profile',                      [AuthController::class, 'updateProfile']);
    Route::post('user/password',                     [AuthController::class, 'changePassword']);
    Route::get('user/sessions',                      [AuthController::class, 'sessions']);
    Route::delete('user/sessions/{tokenId}',         [AuthController::class, 'revokeSession']);
    // Spec alias — FCM token update
    Route::post('user/fcm-token',                    [NotificationController::class, 'registerDevice']);
    // Account deletion (user side)
    Route::post('user/delete-account',               [AuthController::class, 'requestDeletion']);
    Route::delete('user/delete-account',             [AuthController::class, 'cancelDeletionRequest']);

    // Client home feed
    Route::get('feed',                               [FeedController::class, 'index']);

    // Worker search — any authenticated user
    Route::get('workers',                            [WorkerController::class, 'index']);
    Route::get('workers/{workerProfileId}',          [WorkerController::class, 'show']);
    Route::get('categories/{categoryId}/workers',    [WorkerController::class, 'byCategory']);
});

/*
|--------------------------------------------------------------------------
| Booking Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('bookings',                  [BookingController::class, 'index']);
    Route::get('bookings/{id}',             [BookingController::class, 'show']);
    Route::post('bookings/{id}/cancel',     [BookingController::class, 'cancel']);
    Route::post('bookings/{id}/rate',       [BookingController::class, 'rate']);
    // Spec-defined unified status endpoint
    Route::post('bookings/{id}/status',     [BookingController::class, 'updateStatus']);
});

Route::middleware(['auth:sanctum', 'client'])->group(function () {
    Route::post('bookings', [BookingController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'worker'])->group(function () {
    Route::post('bookings/{id}/accept',   [BookingController::class, 'accept']);
    Route::post('bookings/{id}/decline',  [BookingController::class, 'decline']);
    Route::post('bookings/{id}/respond',  [BookingController::class, 'respond']); // spec unified endpoint
    Route::post('bookings/{id}/start',    [BookingController::class, 'start']);
    Route::post('bookings/{id}/complete', [BookingController::class, 'complete']);
});

/*
|--------------------------------------------------------------------------
| Ratings Routes (spec URL: POST /api/ratings)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('ratings', [RatingController::class, 'store']); // spec §H1
});

/*
|--------------------------------------------------------------------------
| Report Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('reports',       [ReportController::class, 'store']);
    Route::get('reports',        [ReportController::class, 'index']);
    Route::get('reports/{id}',   [ReportController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Message Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Spec URLs
    Route::get('messages',              [MessageController::class, 'inbox']);
    Route::get('messages/{bookingId}',  [MessageController::class, 'thread']);
    Route::post('messages/{bookingId}', [MessageController::class, 'sendViaSpecUrl']);

    // Legacy URLs (kept for backwards compat)
    Route::get('bookings/{id}/messages',  [MessageController::class, 'index']);
    Route::post('bookings/{id}/messages', [MessageController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Tracking Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('bookings/{id}/tracking/start',    [TrackingController::class, 'start']);
    Route::post('bookings/{id}/tracking/update',   [TrackingController::class, 'update']);
    Route::post('bookings/{id}/tracking/location', [TrackingController::class, 'location']); // spec alias
    Route::post('bookings/{id}/tracking/stop',     [TrackingController::class, 'stop']);
    Route::get('bookings/{id}/tracking',           [TrackingController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Notification Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('notifications/register-device', [NotificationController::class, 'registerDevice']);
    Route::get('notifications',                  [NotificationController::class, 'index']);
    Route::post('notifications/read-all',        [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{id}/read',       [NotificationController::class, 'markRead']);
});

/*
|--------------------------------------------------------------------------
| Worker Routes — auth:sanctum + worker role required
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'worker'])->prefix('worker')->group(function () {
    Route::post('verification/submit', [VerificationController::class, 'submit']);
    Route::get('verification/status',  [VerificationController::class, 'status']);
    Route::post('profile',             [WorkerProfileController::class, 'update']);

    // Job posts
    Route::get('posts',           [JobPostController::class, 'index']);
    Route::post('posts',          [JobPostController::class, 'store']);
    Route::put('posts/{postId}',  [JobPostController::class, 'update']);
    Route::delete('posts/{postId}', [JobPostController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes — auth:sanctum + admin role required
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Verification management
    Route::get('verifications',                               [AdminVerificationController::class, 'index']);
    Route::get('verifications/pending',                       [AdminVerificationController::class, 'pending']); // spec §K2 alias
    Route::post('verifications/{workerProfileId}/review',     [AdminVerificationController::class, 'review']);

    // User management
    Route::get('users',                      [AdminUserController::class, 'index']);
    Route::get('users/{id}',                 [AdminUserController::class, 'show']);
    Route::patch('users/{id}/toggle-active', [AdminUserController::class, 'toggleActive']);  // legacy
    Route::post('users/{id}/toggle-status',  [AdminUserController::class, 'toggleStatus']);  // spec §K7

    // Booking oversight
    Route::get('bookings',       [AdminBookingController::class, 'index']);
    Route::get('bookings/{id}',  [AdminBookingController::class, 'show']);

    // Report management
    Route::get('reports',                [AdminReportController::class, 'index']);
    Route::get('reports/{id}',           [AdminReportController::class, 'show']);
    Route::patch('reports/{id}/resolve', [AdminReportController::class, 'resolve']);

    // Dashboard stats
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Worker trust tier
    Route::post('workers/{workerProfileId}/trust-tier', [AdminWorkerController::class, 'updateTrustTier']);

    // Ratings oversight
    Route::get('ratings',          [AdminRatingController::class, 'index']);
    Route::delete('ratings/{id}',  [AdminRatingController::class, 'destroy']);

    // Force cancel booking
    Route::post('bookings/{id}/cancel', [AdminBookingController::class, 'forceCancel']);

    // Audit logs
    Route::get('audit-logs', [AdminAuditLogController::class, 'index']);

    // Platform settings
    Route::get('settings',                                 [AdminSettingsController::class, 'index']);        // spec §K17
    Route::post('settings',                                [AdminSettingsController::class, 'store']);        // spec §K17
    Route::get('settings/categories',                      [AdminSettingsController::class, 'listCategories']);
    Route::post('settings/categories',                     [AdminSettingsController::class, 'createCategory']);
    Route::patch('settings/categories/{categoryId}',       [AdminSettingsController::class, 'updateCategory']);

    // Job post oversight
    Route::get('posts',          [AdminJobPostController::class, 'index']);
    Route::delete('posts/{id}',  [AdminJobPostController::class, 'destroy']);

    // Account deletion queue
    Route::get('deletion-requests',                  [AdminDeletionController::class, 'index']);
    Route::post('deletion-requests/{id}/process',    [AdminDeletionController::class, 'process']);
});

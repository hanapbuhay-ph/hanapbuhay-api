<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisterController;
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

    // Worker search — any authenticated user
    Route::get('workers',          [WorkerController::class, 'index']);
    Route::get('workers/{workerProfileId}', [WorkerController::class, 'show']);
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
});

Route::middleware(['auth:sanctum', 'client'])->group(function () {
    Route::post('bookings', [BookingController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'worker'])->group(function () {
    Route::post('bookings/{id}/accept',   [BookingController::class, 'accept']);
    Route::post('bookings/{id}/decline',  [BookingController::class, 'decline']);
    Route::post('bookings/{id}/start',    [BookingController::class, 'start']);
    Route::post('bookings/{id}/complete', [BookingController::class, 'complete']);
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
    Route::get('bookings/{id}/messages',  [MessageController::class, 'index']);
    Route::post('bookings/{id}/messages', [MessageController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Tracking Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('bookings/{id}/tracking/start',  [TrackingController::class, 'start']);
    Route::post('bookings/{id}/tracking/update', [TrackingController::class, 'update']);
    Route::post('bookings/{id}/tracking/stop',   [TrackingController::class, 'stop']);
    Route::get('bookings/{id}/tracking',         [TrackingController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Notification Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('notifications/register-device', [NotificationController::class, 'registerDevice']);
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
});

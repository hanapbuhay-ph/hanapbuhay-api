<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisterController;
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
    Route::post('password/forgot',     [AuthController::class, 'forgotPassword']);
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
});

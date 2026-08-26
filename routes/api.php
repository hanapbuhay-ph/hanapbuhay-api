<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes — no authentication required
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register',    [RegisterController::class, 'register']);
    Route::post('verify-otp',  [EmailVerificationController::class, 'verify']);
});

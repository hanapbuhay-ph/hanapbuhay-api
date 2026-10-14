<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class EmailVerificationController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code'  => ['required', 'string'],
        ]);

        $otp = OtpCode::validFor($request->email, 'email_verification')
                      ->latest('created_at')
                      ->first();

        if ($otp === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        // Constant-time comparison prevents timing attacks on the OTP.
        if (! hash_equals($otp->code, $request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Admin accounts cannot sign in through the mobile app.',
            ], 403);
        }

        try {
            $otp->update(['used_at' => now()]);

            $user->update(['email_verified_at' => now()]);

            $token = $user->createToken('mobile')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully.',
                'data'    => [
                    'token' => $token,
                    'user'  => [
                        'id'                => $user->id,
                        'name'              => $user->name,
                        'email'             => $user->email,
                        'role'              => $user->role,
                        'mobile_number'     => $user->mobile_number,
                        'profile_photo_url' => $user->profile_photo_path
                            ? (Str::startsWith($user->profile_photo_path, ['http://', 'https://'])
                                ? $user->profile_photo_path
                                : asset('storage/' . $user->profile_photo_path))
                            : null,
                        'barangay'          => $user->barangay?->only(['id', 'name', 'latitude', 'longitude']),
                        'email_verified_at' => $user->email_verified_at,
                        'is_google_account' => $user->is_google_account,
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Email verification failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification failed. Please try again later.',
            ], 500);
        }
    }
}

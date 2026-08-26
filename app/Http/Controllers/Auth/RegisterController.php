<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::transaction(function () use ($request, &$user, &$otp) {
                $user = User::create([
                    'name'          => $request->name,
                    'email'         => $request->email,
                    'password'      => $request->password,
                    'mobile_number' => $request->mobile_number,
                    'role'          => $request->role,
                    'barangay_id'   => $request->barangay_id,
                    // email_verified_at intentionally left null until OTP confirmed
                ]);

                // Workers get a profile row created immediately so verification
                // documents can be submitted before the worker is approved.
                if ($request->role === 'worker') {
                    WorkerProfile::create(['user_id' => $user->id]);
                }

                $otp = OtpCode::create([
                    'email'      => $user->email,
                    'code'       => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                    'type'       => 'email_verification',
                    'expires_at' => now()->addMinutes(10),
                ]);
            });

            Mail::to($user->email)->send(
                new OtpMail($otp->code, 'Verify your HanapBuhay account')
            );

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please verify your email.',
                'data'    => [
                    'user' => [
                        'id'                => $user->id,
                        'name'              => $user->name,
                        'email'             => $user->email,
                        'role'              => $user->role,
                        'barangay'          => $user->barangay_id,
                        'email_verified_at' => null,
                        'is_google_account' => false,
                    ],
                ],
            ], 201);
        } catch (Throwable $e) {
            Log::error('Registration failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again later.',
            ], 500);
        }
    }
}

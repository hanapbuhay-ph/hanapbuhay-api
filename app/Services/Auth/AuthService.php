<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\EmailNotVerifiedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Http\Requests\Auth\CompleteProfileRequest;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AuthService
{
    /**
     * @return array{token: string, user: User}
     * @throws InvalidCredentialsException
     * @throws EmailNotVerifiedException
     */
    public function login(string $email, string $password): array
    {
        $user = User::with('barangay')->where('email', $email)->first();

        // Intentionally the same message for "no account" and "wrong password"
        // to avoid leaking which emails are registered (user enumeration).
        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException();
        }

        if ($user->email_verified_at === null) {
            throw new EmailNotVerifiedException();
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return [
            'token' => $token,
            'user'  => $user,
        ];
    }

    /**
     * Completes the profile for a new Google user.
     * File upload runs before the transaction — a failed upload logs and
     * continues so it never blocks the profile update.
     *
     * @return User with barangay eager-loaded
     */
    public function completeProfile(User $user, CompleteProfileRequest $request): User
    {
        $photoPath = $user->profile_photo_path;

        if ($request->hasFile('profile_photo')) {
            try {
                $ext       = $request->file('profile_photo')->getClientOriginalExtension();
                $photoPath = "photos/{$user->id}.{$ext}";

                Storage::disk('public')->putFileAs(
                    'photos',
                    $request->file('profile_photo'),
                    "{$user->id}.{$ext}"
                );
            } catch (Throwable $e) {
                // A failed photo upload must not block profile completion.
                Log::error('Profile photo upload failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);

                $photoPath = $user->profile_photo_path;
            }
        }

        DB::transaction(function () use ($user, $request, $photoPath): void {
            $user->update([
                'name'               => $request->name,
                'mobile_number'      => $request->mobile_number,
                'role'               => $request->role,
                'barangay_id'        => $request->barangay_id,
                'profile_photo_path' => $photoPath,
            ]);

            // Create the worker profile row so verification documents
            // can be submitted immediately after profile completion.
            if ($request->role === 'worker' && ! $user->workerProfile()->exists()) {
                WorkerProfile::create(['user_id' => $user->id]);
            }
        });

        return $user->load('barangay');
    }

    /**
     * Resends an email verification OTP.
     *
     * Returns an array with keys:
     *   - 'already_verified' (bool)
     *   - 'user_not_found'   (bool)
     *   - 'otp'             (string|null) — only set when a new OTP was created
     *   - 'email'           (string|null)
     */
    public function resendOtp(string $email): array
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            return ['user_not_found' => true, 'already_verified' => false, 'otp' => null, 'email' => null];
        }

        if ($user->email_verified_at !== null) {
            return ['user_not_found' => false, 'already_verified' => true, 'otp' => null, 'email' => null];
        }

        $otp = DB::transaction(function () use ($email): string {
            OtpCode::where('email', $email)
                ->where('type', 'email_verification')
                ->whereNull('used_at')
                ->update(['used_at' => Carbon::now()]);

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            OtpCode::create([
                'email'      => $email,
                'code'       => $code,
                'type'       => 'email_verification',
                'expires_at' => Carbon::now()->addMinutes(10),
                'used_at'    => null,
            ]);

            return $code;
        });

        return ['user_not_found' => false, 'already_verified' => false, 'otp' => $otp, 'email' => $email];
    }

    /**
     * Initiates a password reset flow.
     *
     * Returns the generated OTP code and email when the user exists,
     * or null for both when the email is not registered.
     *
     * @return array{otp: string|null, email: string|null}
     */
    public function forgotPassword(string $email): array
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            return ['otp' => null, 'email' => null];
        }

        $otp = DB::transaction(function () use ($email): string {
            OtpCode::where('email', $email)
                ->where('type', 'password_reset')
                ->whereNull('used_at')
                ->update(['used_at' => Carbon::now()]);

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            OtpCode::create([
                'email'      => $email,
                'code'       => $code,
                'type'       => 'password_reset',
                'expires_at' => Carbon::now()->addMinutes(10),
                'used_at'    => null,
            ]);

            return $code;
        });

        return ['otp' => $otp, 'email' => $email];
    }

    /**
     * Verifies a password reset OTP and returns a short-lived reset_token.
     *
     * Returns the reset_token string on success, or null if the OTP is
     * invalid, expired, already used, or the email is not registered.
     */
    public function verifyResetOtp(string $email, string $code): ?string
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            return null;
        }

        $otp = OtpCode::validFor($email, 'password_reset')
            ->where('code', $code)
            ->latest()
            ->first();

        if ($otp === null) {
            return null;
        }

        $resetToken = \Illuminate\Support\Str::random(64);

        $otp->update(['reset_token' => $resetToken]);

        return $resetToken;
    }

    /**
     * Shape the user object returned in every auth response.
     * Single place to change the auth response user shape.
     */
    public function formatUser(User $user): array
    {
        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'mobile_number'     => $user->mobile_number,
            'role'              => $user->role,
            'profile_photo_url' => $user->profile_photo_path
                ? asset('storage/' . $user->profile_photo_path)
                : null,
            'barangay'          => $user->barangay?->only([
                'id', 'name', 'latitude', 'longitude',
            ]),
            'email_verified_at' => $user->email_verified_at,
            'is_google_account' => $user->is_google_account,
        ];
    }
}

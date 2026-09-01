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
use Illuminate\Support\Str;
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

        $resetToken = Str::random(64);

        $otp->update(['reset_token' => $resetToken]);

        return $resetToken;
    }

    /**
     * Resets the user's password using a verified reset_token.
     *
     * Returns false if the email or OTP record is not found / invalid.
     * On success: updates password, marks OTP used, revokes all tokens.
     */
    public function resetPassword(string $email, string $resetToken, string $newPassword): bool
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            return false;
        }

        $otp = OtpCode::validFor($email, 'password_reset')
            ->where('reset_token', $resetToken)
            ->latest()
            ->first();

        if ($otp === null) {
            return false;
        }

        DB::transaction(function () use ($user, $otp, $newPassword): void {
            $user->password = Hash::make($newPassword);
            $user->save();

            $otp->used_at = Carbon::now();
            $otp->save();
        });

        $user->tokens()->delete();

        return true;
    }

    /**
     * Update the authenticated user's basic profile fields and optional photo.
     */
    public function updateProfile(User $user, \App\Http\Requests\Auth\UpdateProfileRequest $request): User
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
                Log::error('Profile photo upload failed during updateProfile', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
                $photoPath = $user->profile_photo_path;
            }
        }

        $fields = array_filter([
            'name'               => $request->input('name'),
            'mobile_number'      => $request->input('mobile_number'),
            'barangay_id'        => $request->input('barangay_id'),
            'profile_photo_path' => $photoPath !== $user->profile_photo_path ? $photoPath : null,
        ], fn ($v) => $v !== null);

        // Re-include photo even when path is the same (upload replaced file)
        if ($request->hasFile('profile_photo')) {
            $fields['profile_photo_path'] = $photoPath;
        }

        if (! empty($fields)) {
            $user->update($fields);
        }

        return $user->load('barangay');
    }

    /**
     * Change the authenticated user's password.
     *
     * For Google-only accounts (password is null), `current_password` is not
     * required — they are setting a password for the first time.
     *
     * @return array{success: bool, message: string}
     */
    public function changePassword(User $user, ?string $currentPassword, string $newPassword): array
    {
        $isGoogleOnly = $user->is_google_account && $user->password === null;

        if (! $isGoogleOnly) {
            if ($currentPassword === null) {
                return ['success' => false, 'message' => 'Current password is required.'];
            }

            if (! Hash::check($currentPassword, $user->password)) {
                return ['success' => false, 'message' => 'Current password is incorrect.'];
            }
        }

        $user->update(['password' => Hash::make($newPassword)]);

        return ['success' => true, 'message' => 'Password changed successfully.'];
    }

    /**
     * Return all active Sanctum tokens for the user, flagging the current one.
     */
    public function getSessions(User $user, ?int $currentTokenId): array
    {
        return $user->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id'           => $token->id,
                'device'       => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'is_current'   => $token->id === $currentTokenId,
            ])
            ->values()
            ->all();
    }

    /**
     * Revoke a specific session token (cannot revoke current token — use logout).
     *
     * @return array{success: bool, message: string}
     */
    public function revokeSession(User $user, int $tokenId, ?int $currentTokenId): array
    {
        if ($currentTokenId !== null && $tokenId === $currentTokenId) {
            return ['success' => false, 'message' => 'Use the logout endpoint to end your current session.'];
        }

        $deleted = $user->tokens()->where('id', $tokenId)->delete();

        if ($deleted === 0) {
            return ['success' => false, 'message' => 'Session not found.'];
        }

        return ['success' => true, 'message' => 'Session revoked.'];
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

<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\EmailNotVerifiedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Http\Requests\Auth\CompleteProfileRequest;
use App\Models\User;
use App\Models\WorkerProfile;
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

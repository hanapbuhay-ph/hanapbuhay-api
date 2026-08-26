<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteProfileRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * Called by Flutter after it obtains a Google ID token via google_sign_in.
     * Flutter sends the raw ID token — we verify it with Google server-side
     * so the token is never trusted blindly.
     */
    public function handleGoogleToken(Request $request): JsonResponse
    {
        $request->validate([
            'google_token' => ['required', 'string'],
        ]);

        try {
            $googleUser = Socialite::driver('google')
                ->userFromToken($request->google_token);
        } catch (Throwable $e) {
            Log::warning('Google token verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Google token.',
            ], 401);
        }

        try {
            $isNewUser = false;

            $user = DB::transaction(function () use ($googleUser, &$isNewUser): User {
                // Look up by google_id first, fall back to email so a user
                // who previously registered manually can link their Google account.
                $existing = User::where('google_id', $googleUser->getId())
                                ->orWhere('email', $googleUser->getEmail())
                                ->first();

                if ($existing !== null) {
                    // Link google_id if they registered manually before.
                    if ($existing->google_id === null) {
                        $existing->update([
                            'google_id'         => $googleUser->getId(),
                            'is_google_account' => true,
                        ]);
                    }

                    return $existing->load('barangay');
                }

                $isNewUser = true;

                // New Google users get role=null intentionally — they must
                // call complete-profile before they can use the app.
                return User::create([
                    'name'               => $googleUser->getName(),
                    'email'              => $googleUser->getEmail(),
                    'google_id'          => $googleUser->getId(),
                    'profile_photo_path' => $googleUser->getAvatar(),
                    'role'               => null,
                    'is_google_account'  => true,
                    'password'           => null,
                    'email_verified_at'  => now(),
                ]);
            });

            $token = $user->createToken('mobile')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => $isNewUser ? 'Account created successfully.' : 'Login successful.',
                'data'    => [
                    'token'       => $token,
                    'is_new_user' => $isNewUser,
                    'user'        => $this->authService->formatUser($user),
                ],
            ], $isNewUser ? 201 : 200);
        } catch (Throwable $e) {
            Log::error('Google sign-in failed', [
                'email' => $googleUser->getEmail() ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Google sign-in failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Completes the profile for a new Google user (is_new_user = true).
     * Protected — requires the Sanctum token issued by handleGoogleToken.
     */
    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        // Guard: only users who signed in via Google with an incomplete
        // profile (role = null) may call this endpoint.
        if ($user->role !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Profile is already complete.',
            ], 403);
        }

        try {
            $user = $this->authService->completeProfile($user, $request);

            return response()->json([
                'success' => true,
                'message' => 'Profile completed.',
                'data'    => [
                    'user' => $this->authService->formatUser($user),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Profile completion failed', [
                'user_id' => $request->user()->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Profile completion failed. Please try again.',
            ], 500);
        }
    }
}

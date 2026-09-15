<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteProfileRequest;
use App\Mail\OtpMail;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        $tokenInfo = [];

        $request->validate([
            'google_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'admin_only' => ['sometimes', 'boolean'],
        ]);

        try {
            $tokenInfo = Http::timeout(5)
                ->get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $request->google_token,
                ])
                ->throw()
                ->json();

            $expectedClientId = (string) config('services.google.client_id');
            if (
                empty($tokenInfo['sub'])
                || empty($tokenInfo['email'])
                || ($tokenInfo['email_verified'] ?? 'false') !== 'true'
                || ($expectedClientId !== '' && ($tokenInfo['aud'] ?? null) !== $expectedClientId)
            ) {
                throw new \RuntimeException('Google token claims are invalid.');
            }
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
            $adminOnly = $request->boolean('admin_only');

            $user = DB::transaction(function () use ($tokenInfo, $adminOnly, &$isNewUser): User {
                // Look up by google_id first, fall back to email so a user
                // who previously registered manually can link their Google account.
                $existing = User::where('google_id', $tokenInfo['sub'])
                                ->orWhere('email', $tokenInfo['email'])
                                ->first();

                if ($existing !== null) {
                    if ($adminOnly && $existing->role !== 'admin') {
                        throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                            'This Google account is not configured as an admin.'
                        );
                    }

                    // Link google_id if they registered manually before.
                    if ($existing->google_id === null) {
                        $existing->update([
                            'google_id'         => $tokenInfo['sub'],
                            'is_google_account' => true,
                        ]);
                    }

                    return $existing->load('barangay');
                }

                if ($adminOnly) {
                    throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                        'This Google account is not configured as an admin.'
                    );
                }

                $isNewUser = true;

                // New Google users get role=null intentionally — they must
                // call complete-profile before they can use the app.
                return User::create([
                    'name'               => $tokenInfo['name'] ?? $tokenInfo['email'],
                    'email'              => $tokenInfo['email'],
                    'google_id'          => $tokenInfo['sub'],
                    'profile_photo_path' => $tokenInfo['picture'] ?? null,
                    'role'               => null,
                    'is_google_account'  => true,
                    'password'           => null,
                    'email_verified_at'  => now(),
                ]);
            });

            if ($user->two_factor_enabled) {
                $code = $this->authService->resendLoginOtp($user->email);
                Mail::to($user->email)->queue(
                    new OtpMail($code, 'Your Login Verification Code')
                );

                return response()->json([
                    'success' => true,
                    'message' => 'A verification code has been sent to your email.',
                    'data' => [
                        'requiresOtp' => true,
                        'is_new_user' => $isNewUser,
                        'user' => $this->authService->formatUser($user),
                    ],
                ], $isNewUser ? 201 : 200);
            }

            $token = $this->authService->issueAccessToken(
                $user,
                $request->input('device_name'),
                $request->ip(),
            );

            return response()->json([
                'success' => true,
                'message' => $isNewUser ? 'Account created successfully.' : 'Login successful.',
                'data'    => [
                    'token'       => $token,
                    'is_new_user' => $isNewUser,
                    'user'        => $this->authService->formatUser($user),
                ],
            ], $isNewUser ? 201 : 200);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (Throwable $e) {
            Log::error('Google sign-in failed', [
                'email' => $tokenInfo['email'] ?? 'unknown',
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

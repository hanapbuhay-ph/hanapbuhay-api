<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteProfileRequest;
use App\Mail\OtpMail;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly SecurityEventLogger $securityEventLogger,
    ) {}

    /**
     * Called by Flutter after it obtains a Google ID token via google_sign_in.
     * Flutter sends the raw ID token — we verify it with Google server-side
     * so the token is never trusted blindly.
     */
    public function handleAdminGoogleToken(Request $request): JsonResponse
    {
        return $this->handleGoogleToken($request, true);
    }

    public function handleGoogleToken(Request $request, bool $adminPortal = false): JsonResponse
    {
        $tokenInfo = [];

        $request->validate([
            'google_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
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
                'error_type' => $e::class,
            ]);
            $this->securityEventLogger->record(
                'google_login',
                'failure',
                null,
                null,
                ['reason' => 'invalid_token'],
                $request->ip(),
                $request->userAgent(),
            );

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Google token.',
            ], 401);
        }

        try {
            $isNewUser = false;
            $user = DB::transaction(function () use ($tokenInfo, $adminPortal, &$isNewUser): User {
                // Look up by google_id first, fall back to email so a user
                // who previously registered manually can link their Google account.
                $existing = User::where('google_id', $tokenInfo['sub'])
                    ->orWhere('email', $tokenInfo['email'])
                    ->first();

                if ($existing !== null) {
                    if ($existing->is_active === false) {
                        throw new AccessDeniedHttpException(
                            'Your account has been suspended.'
                        );
                    }

                    if ($existing->isAdmin() !== $adminPortal) {
                        throw new AccessDeniedHttpException(
                            $adminPortal
                                ? 'This Google account is not configured as an admin.'
                                : 'Admin accounts must sign in through the admin portal.'
                        );
                    }

                    // Link google_id if they registered manually before.
                    if ($existing->google_id === null) {
                        $existing->update([
                            'google_id' => $tokenInfo['sub'],
                            'is_google_account' => true,
                        ]);
                    }

                    return $existing->load('barangay');
                }

                if ($adminPortal) {
                    throw new AccessDeniedHttpException(
                        'This Google account is not configured as an admin.'
                    );
                }

                $isNewUser = true;

                // New Google users get role=null intentionally — they must
                // call complete-profile before they can use the app.
                return User::create([
                    'name' => $tokenInfo['name'] ?? $tokenInfo['email'],
                    'email' => $tokenInfo['email'],
                    'google_id' => $tokenInfo['sub'],
                    'profile_photo_path' => $tokenInfo['picture'] ?? null,
                    'role' => null,
                    'is_google_account' => true,
                    'password' => null,
                    'email_verified_at' => now(),
                ]);
            });

            if ($user->two_factor_enabled) {
                $code = $this->authService->resendLoginOtp($user->email);
                $this->securityEventLogger->record(
                    'google_login_otp_challenge',
                    'success',
                    $user,
                    $user->id,
                    ['is_new_user' => $isNewUser],
                    $request->ip(),
                    $request->userAgent(),
                );
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
            $this->securityEventLogger->record(
                'google_login',
                'success',
                $user,
                $user->id,
                ['is_new_user' => $isNewUser],
                $request->ip(),
                $request->userAgent(),
            );

            return response()->json([
                'success' => true,
                'message' => $isNewUser ? 'Account created successfully.' : 'Login successful.',
                'data' => [
                    'token' => $token,
                    'is_new_user' => $isNewUser,
                    'user' => $this->authService->formatUser($user),
                ],
            ], $isNewUser ? 201 : 200);
        } catch (AccessDeniedHttpException $e) {
            $email = $tokenInfo['email'] ?? null;
            $this->securityEventLogger->record(
                'google_login',
                'failure',
                null,
                $email === null ? null : User::where('email', $email)->value('id'),
                array_filter(['email' => $email, 'reason' => 'access_denied']),
                $request->ip(),
                $request->userAgent(),
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (Throwable $e) {
            Log::error('Google sign-in failed', [
                'email' => $tokenInfo['email'] ?? 'unknown',
                'error_type' => $e::class,
            ]);
            $email = $tokenInfo['email'] ?? null;
            $this->securityEventLogger->record(
                'google_login',
                'failure',
                null,
                $email === null ? null : User::where('email', $email)->value('id'),
                array_filter(['email' => $email, 'reason' => 'sign_in_error']),
                $request->ip(),
                $request->userAgent(),
            );

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
                'data' => [
                    'user' => $this->authService->formatUser($user),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Profile completion failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Profile completion failed. Please try again.',
            ], 500);
        }
    }
}

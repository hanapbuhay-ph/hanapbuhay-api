<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Auth\EmailNotVerifiedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyResetOtpRequest;
use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Auth\AccountDeletionService;
use App\Services\Auth\AuthService;
use App\Services\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_LOCKOUT_SECONDS = 900;

    private const PASSWORD_CHANGE_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly AuthService $authService,
        private readonly AccountDeletionService $deletionService,
        private readonly SecurityEventLogger $securityEventLogger,
    ) {}

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $success = $this->authService->resetPassword(
            $request->validated('email'),
            $request->validated('reset_token'),
            $request->validated('password')
        );
        $this->securityEventLogger->record(
            'password_reset',
            $success ? 'success' : 'failure',
            null,
            User::where('email', $request->validated('email'))->value('id'),
            ['email' => $request->validated('email')],
            $request->ip(),
            $request->userAgent(),
        );

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please log in.',
        ]);
    }

    public function verifyResetOtp(VerifyResetOtpRequest $request): JsonResponse
    {
        $resetToken = $this->authService->verifyResetOtp(
            $request->validated('email'),
            $request->validated('code')
        );
        $this->securityEventLogger->record(
            'password_reset_otp_verified',
            $resetToken === null ? 'failure' : 'success',
            null,
            User::where('email', $request->validated('email'))->value('id'),
            ['email' => $request->validated('email')],
            $request->ip(),
            $request->userAgent(),
        );

        if ($resetToken === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset code.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Code verified. You can now reset your password.',
            'data' => ['reset_token' => $resetToken],
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return $this->requestPasswordReset($request, ['client', 'worker']);
    }

    public function forgotAdminPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return $this->requestPasswordReset($request, ['admin', 'super_admin']);
    }

    private function requestPasswordReset(
        ForgotPasswordRequest $request,
        array $allowedRoles,
    ): JsonResponse {
        $email = $request->validated('email');
        $key = 'forgot-password:'.$email;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please wait before trying again.',
            ], 429);
        }

        RateLimiter::hit($key, 600);

        $result = $this->authService->forgotPassword($email, $allowedRoles);

        if ($result['otp'] !== null) {
            Mail::to($result['email'])->queue(
                new OtpMail($result['otp'], 'Your Password Reset Code')
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'If an eligible account exists, a password reset code has been sent.',
            'data' => ['otp_expires_in_seconds' => OtpCode::VALIDITY_SECONDS],
        ]);
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $key = 'resend-otp:'.$email;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please wait before requesting another code.',
            ], 429);
        }

        RateLimiter::hit($key, 600);

        $result = $this->authService->resendOtp($email);

        if ($result['user_not_found']) {
            return response()->json([
                'success' => true,
                'message' => 'Verification code resent to your email.',
                'data' => ['otp_expires_in_seconds' => OtpCode::VALIDITY_SECONDS],
            ]);
        }

        if ($result['already_verified']) {
            return response()->json([
                'success' => true,
                'message' => 'Your email is already verified. Please log in.',
                'data' => ['otp_expires_in_seconds' => 0],
            ]);
        }

        Mail::to($result['email'])->queue(
            new OtpMail($result['otp'], 'Your Email Verification Code')
        );

        return response()->json([
            'success' => true,
            'message' => 'Verification code resent to your email.',
            'data' => ['otp_expires_in_seconds' => OtpCode::VALIDITY_SECONDS],
        ]);
    }

    public function adminLogin(LoginRequest $request): JsonResponse
    {
        return $this->login($request, true);
    }

    public function login(LoginRequest $request, bool $adminPortal = false): JsonResponse
    {
        $email = $request->validated('email');
        $loginKey = $this->loginRateLimitKey(mb_strtolower($email), $request->ip());
        $attemptsKey = "{$loginKey}:attempts";
        $lockoutKey = "{$loginKey}:lockout";
        if (RateLimiter::tooManyAttempts($lockoutKey, 1)) {
            return $this->rateLimitedResponse(
                'Too many failed login attempts. Try again when the countdown ends.',
                RateLimiter::availableIn($lockoutKey),
            );
        }

        try {
            $result = $this->authService->login(
                $email,
                $request->validated('password'),
                $request->validated('device_name'),
                $request->ip(),
                $adminPortal,
            );

            if ($result['requires_otp'] ?? false) {
                $this->securityEventLogger->record(
                    'login_otp_challenge',
                    'success',
                    $result['user'],
                    $result['user']->id,
                    [],
                    $request->ip(),
                    $request->userAgent(),
                );
                Mail::to($request->validated('email'))->queue(
                    new OtpMail($result['otp'], 'Your Login Verification Code')
                );
                RateLimiter::clear($attemptsKey);
                RateLimiter::clear($lockoutKey);

                return response()->json([
                    'success' => true,
                    'message' => 'A verification code has been sent to your email.',
                    'data' => [
                        'requiresOtp' => true,
                        'otp_expires_in_seconds' => OtpCode::VALIDITY_SECONDS,
                    ],
                ]);
            }

            $this->securityEventLogger->record(
                'login',
                'success',
                $result['user'],
                $result['user']->id,
                [],
                $request->ip(),
                $request->userAgent(),
            );
            RateLimiter::clear($attemptsKey);
            RateLimiter::clear($lockoutKey);

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'token' => $result['token'],
                    'user' => $this->authService->formatUser($result['user']),
                ],
            ]);
        } catch (InvalidCredentialsException $e) {
            RateLimiter::hit($attemptsKey, self::LOGIN_LOCKOUT_SECONDS);
            $this->securityEventLogger->record(
                'login',
                'failure',
                null,
                User::where('email', $email)->value('id'),
                ['email' => $email, 'reason' => 'invalid_credentials'],
                $request->ip(),
                $request->userAgent(),
            );

            if (RateLimiter::attempts($attemptsKey) >= self::LOGIN_MAX_ATTEMPTS) {
                RateLimiter::hit($lockoutKey, self::LOGIN_LOCKOUT_SECONDS);

                return $this->rateLimitedResponse(
                    'Too many failed login attempts. Try again when the countdown ends.',
                    RateLimiter::availableIn($lockoutKey),
                );
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'failed_attempts_remaining' => self::LOGIN_MAX_ATTEMPTS - RateLimiter::attempts($attemptsKey),
                ],
            ], 401);
        } catch (EmailNotVerifiedException $e) {
            RateLimiter::clear($attemptsKey);
            RateLimiter::clear($lockoutKey);
            $this->securityEventLogger->record(
                'login',
                'failure',
                null,
                User::where('email', $email)->value('id'),
                ['email' => $email, 'reason' => 'email_not_verified'],
                $request->ip(),
                $request->userAgent(),
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function verifyAdminLoginOtp(Request $request): JsonResponse
    {
        return $this->verifyLoginOtp($request, true);
    }

    public function verifyLoginOtp(Request $request, bool $adminPortal = false): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $this->authService->verifyLoginOtp(
            $validated['email'],
            $validated['code'],
            $validated['device_name'] ?? null,
            $request->ip(),
            $adminPortal,
        );
        $this->securityEventLogger->record(
            'login_otp_verification',
            $result === null ? 'failure' : 'success',
            $result['user'] ?? null,
            User::where('email', $validated['email'])->value('id'),
            $result === null ? ['email' => $validated['email'], 'reason' => 'invalid_or_expired_code'] : [],
            $request->ip(),
            $request->userAgent(),
        );

        if ($result === null) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired verification code.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $result['token'],
                'user' => $this->authService->formatUser($result['user']),
            ],
        ]);
    }

    public function resendAdminLoginOtp(Request $request): JsonResponse
    {
        return $this->resendLoginOtp($request, true);
    }

    public function resendLoginOtp(Request $request, bool $adminPortal = false): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $code = $this->authService->resendLoginOtp($validated['email'], $adminPortal);

        if ($code !== null) {
            Mail::to($validated['email'])->queue(new OtpMail($code, 'Your Login Verification Code'));
        }

        return response()->json([
            'success' => true,
            'message' => 'A new verification code has been sent.',
            'data' => ['otp_expires_in_seconds' => OtpCode::VALIDITY_SECONDS],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Revoke only the token used for this request — not all sessions.
        // This lets the user stay logged in on other devices.
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('barangay');

        return response()->json([
            'success' => true,
            'data' => $this->authService->formatUser($user),
        ]);
    }

    /**
     * POST /api/user/profile
     * Update name, mobile number, barangay and/or profile photo.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile($request->user(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => ['user' => $this->authService->formatUser($user)],
        ]);
    }

    /**
     * POST /api/user/password
     * Change password. Google-only accounts may omit current_password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $cooldownKey = $this->passwordChangeRateLimitKey($request->user()->id);
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            return $this->rateLimitedResponse(
                'Please wait before attempting another password change.',
                RateLimiter::availableIn($cooldownKey),
            );
        }

        RateLimiter::hit($cooldownKey, self::PASSWORD_CHANGE_COOLDOWN_SECONDS);

        $result = $this->authService->changePassword(
            $request->user(),
            $request->input('current_password'),
            $request->validated('password'),
        );
        $this->securityEventLogger->record(
            'password_change',
            $result['success'] ? 'success' : 'failure',
            $request->user(),
            $request->user()->id,
            $result['success'] ? [] : ['reason' => 'current_password_invalid'],
            $request->ip(),
            $request->userAgent(),
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => [
                    'retry_after_seconds' => RateLimiter::availableIn($cooldownKey),
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'retry_after_seconds' => RateLimiter::availableIn($cooldownKey),
            ],
        ]);
    }

    public function passwordChangeCooldown(Request $request): JsonResponse
    {
        $retryAfter = RateLimiter::availableIn($this->passwordChangeRateLimitKey($request->user()->id));

        return response()->json([
            'success' => true,
            'data' => [
                'retry_after_seconds' => $retryAfter,
            ],
        ]);
    }

    private function loginRateLimitKey(string $email, ?string $ipAddress): string
    {
        return 'login:'.hash('sha256', $email.'|'.($ipAddress ?? 'unknown'));
    }

    private function passwordChangeRateLimitKey(int $userId): string
    {
        return "password-change:{$userId}";
    }

    private function rateLimitedResponse(string $message, int $retryAfter): JsonResponse
    {
        $retryAfter = max(1, $retryAfter);

        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [
                'retry_after_seconds' => $retryAfter,
            ],
        ], 429)->header('Retry-After', (string) $retryAfter);
    }

    /**
     * GET /api/user/sessions
     * List all active login sessions (Sanctum tokens).
     */
    public function sessions(Request $request): JsonResponse
    {
        $currentToken = $request->user()->currentAccessToken();
        $currentId = $currentToken?->id;

        $sessions = $this->authService->getSessions(
            $request->user(),
            $currentId,
        );

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * DELETE /api/user/sessions/{tokenId}
     * Revoke a specific session. Cannot revoke the currently active session.
     */
    public function revokeSession(Request $request, int $tokenId): JsonResponse
    {
        $currentToken = $request->user()->currentAccessToken();
        $currentId = $currentToken?->id;

        $result = $this->authService->revokeSession(
            $request->user(),
            $tokenId,
            $currentId,
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['message'] === 'Session not found.' ? 404 : 403);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ]);
    }

    /**
     * POST /api/user/sessions/revoke-all
     * Revoke every active session, including the current one.
     */
    public function revokeAllSessions(Request $request): JsonResponse
    {
        $this->authService->revokeAllSessions($request->user());

        return response()->json([
            'success' => true,
            'message' => 'All sessions revoked.',
        ]);
    }

    /**
     * POST /api/user/delete-account
     * Submit an account deletion request. Idempotent.
     */
    public function requestDeletion(Request $request): JsonResponse
    {
        $this->deletionService->requestDeletion($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Account deletion requested. An admin will process your request within 30 days.',
            'data' => [
                'deletion_requested_at' => $request->user()->fresh()->deletion_requested_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/user/delete-account
     * Return the current user's deletion request status.
     */
    public function deletionRequestStatus(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'deletion_requested_at' => $request->user()->deletion_requested_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * DELETE /api/user/delete-account
     * Cancel a pending deletion request.
     */
    public function cancelDeletionRequest(Request $request): JsonResponse
    {
        if ($request->user()->deletion_requested_at === null) {
            return response()->json([
                'success' => false,
                'message' => 'No pending deletion request found.',
            ], 422);
        }

        $this->deletionService->cancelDeletionRequest($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Deletion request cancelled.',
            'data' => [
                'deletion_requested_at' => null,
            ],
        ]);
    }

    public function notificationPreferences(Request $request): JsonResponse
    {
        $preferences = $this->authService->getNotificationPreferences($request->user());

        return response()->json([
            'success' => true,
            'data' => $preferences,
        ]);
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_updates' => 'sometimes|boolean',
            'messages' => 'sometimes|boolean',
            'promotions' => 'sometimes|boolean',
            'push' => 'sometimes|boolean',
            'email' => 'sometimes|boolean',
        ]);

        $user = $this->authService->updateNotificationPreferences($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated.',
            'data' => $this->authService->getNotificationPreferences($user),
        ]);
    }

    public function twoFactorPreference(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['enabled' => (bool) $request->user()->two_factor_enabled],
        ]);
    }

    public function updateTwoFactorPreference(Request $request): JsonResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $request->user()->update(['two_factor_enabled' => $validated['enabled']]);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication preference updated.',
            'data' => ['enabled' => (bool) $request->user()->fresh()->two_factor_enabled],
        ]);
    }
}

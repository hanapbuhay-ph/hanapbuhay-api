<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Auth\EmailNotVerifiedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetOtpRequest;
use App\Mail\OtpMail;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $success = $this->authService->resetPassword(
            $request->validated('email'),
            $request->validated('reset_token'),
            $request->validated('password')
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

        if ($resetToken === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset code.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Code verified. You can now reset your password.',
            'data'    => ['reset_token' => $resetToken],
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $key   = 'forgot-password:' . $email;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please wait before trying again.',
            ], 429);
        }

        RateLimiter::hit($key, 600);

        $result = $this->authService->forgotPassword($email);

        if ($result['otp'] !== null) {
            Mail::to($result['email'])->send(
                new OtpMail($result['otp'], 'Your Password Reset Code')
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'If that email is registered, a password reset code has been sent.',
        ]);
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $key   = 'resend-otp:' . $email;

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
            ]);
        }

        if ($result['already_verified']) {
            return response()->json([
                'success' => true,
                'message' => 'Your email is already verified. Please log in.',
            ]);
        }

        Mail::to($result['email'])->send(
            new OtpMail($result['otp'], 'Your Email Verification Code')
        );

        return response()->json([
            'success' => true,
            'message' => 'Verification code resent to your email.',
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->validated('email'),
                $request->validated('password')
            );

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data'    => [
                    'token' => $result['token'],
                    'user'  => $this->authService->formatUser($result['user']),
                ],
            ]);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        } catch (EmailNotVerifiedException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
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
            'data'    => $this->authService->formatUser($user),
        ]);
    }
}

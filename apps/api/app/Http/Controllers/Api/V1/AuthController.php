<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9]\d{1,14}$/'], // E.164 format
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'full_name' => ['required', 'string', 'max:255'],
            'locale' => ['sometimes', 'string', 'in:fr-FR,en-US'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $user = User::create([
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
            'full_name' => $request->full_name,
            'locale' => $request->locale ?? 'fr-FR',
        ]);

        // Audit log: user registration (Issue #12)
        AuditLog::log(
            action: 'user.registered',
            entityType: 'User',
            entityId: $user->id,
            metadata: ['email' => $user->email, 'locale' => $user->locale],
            userId: $user->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $accessToken = $user->createToken('access-token', ['*'], now()->addMinutes(15))->plainTextToken;
        $refreshToken = $user->createToken('refresh-token', ['refresh'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'full_name' => $user->full_name,
                    'locale' => $user->locale,
                ],
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
            ],
        ], 201);
    }

    /**
     * Login user with email or phone.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required_without:phone', 'email'],
            'phone' => ['required_without:email', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        // Find user by email or phone
        $user = null;
        if ($request->has('email')) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->has('phone')) {
            $user = User::where('phone', $request->phone)->first();
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // Audit log: failed login attempt (Issue #12)
            AuditLog::log(
                action: 'user.login_failed',
                entityType: 'User',
                entityId: $user?->id ?? '00000000-0000-0000-0000-000000000000',
                metadata: ['attempted_email_or_phone' => hash('sha256', $request->email ?? $request->phone)],
                userId: $user?->id,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return response()->json([
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'Email/phone or password is incorrect',
                ],
            ], 401);
        }

        // Revoke old tokens
        $user->tokens()->delete();

        $accessToken = $user->createToken('access-token', ['*'], now()->addMinutes(15))->plainTextToken;
        $refreshToken = $user->createToken('refresh-token', ['refresh'], now()->addDays(30))->plainTextToken;

        // Audit log: successful login (Issue #12)
        AuditLog::log(
            action: 'user.logged_in',
            entityType: 'User',
            entityId: $user->id,
            metadata: ['login_method' => $request->has('email') ? 'email' : 'phone'],
            userId: $user->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'full_name' => $user->full_name,
                    'locale' => $user->locale,
                ],
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
            ],
        ]);
    }

    /**
     * Refresh access token using refresh token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->bearerToken();

        if (! $refreshToken) {
            return response()->json([
                'error' => [
                    'code' => 'MISSING_TOKEN',
                    'message' => 'Refresh token is required',
                ],
            ], 401);
        }

        // Validate refresh token
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'Invalid or expired refresh token',
                ],
            ], 401);
        }

        // Create new access token
        $accessToken = $user->createToken('access-token', ['*'], now()->addMinutes(15))->plainTextToken;

        return response()->json([
            'data' => [
                'access_token' => $accessToken,
            ],
        ]);
    }

    /**
     * Logout user (revoke all tokens).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Audit log: user logout (Issue #12)
        AuditLog::log(
            action: 'user.logged_out',
            entityType: 'User',
            entityId: $user->id,
            userId: $user->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'data' => [
                'message' => 'Successfully logged out',
            ],
        ]);
    }

    /**
     * Request password reset token (Issue #10).
     */
    public function resetRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_or_phone' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        // Find user by email or phone
        $emailOrPhone = $request->email_or_phone;
        $user = User::where('email', $emailOrPhone)
            ->orWhere('phone', $emailOrPhone)
            ->first();

        // Always return 200 to prevent user enumeration
        if (! $user) {
            return response()->json([
                'data' => [
                    'message' => 'If the email or phone exists in our system, you will receive password reset instructions.',
                ],
            ]);
        }

        // Generate secure reset token
        $token = Str::random(64);
        $hashedToken = Hash::make($token);

        // Delete any existing tokens for this email
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // Store token (expires in 1 hour)
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $hashedToken,
            'created_at' => now(),
        ]);

        // Audit log: password reset requested (Issue #12)
        AuditLog::log(
            action: 'user.password_reset_requested',
            entityType: 'User',
            entityId: $user->id,
            metadata: ['method' => filter_var($emailOrPhone, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone'],
            userId: $user->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        // TODO: Send email with reset link (deferred to email infrastructure issue #6)
        // For now, log the token for testing (LOCAL ENVIRONMENT ONLY - security sensitive)
        if (app()->environment('local')) {
            \Log::info('Password reset token generated', [
                'email' => $user->email,
                'token' => $token,
                'reset_url' => config('app.frontend_url').'/reset-password?token='.$token,
            ]);
        }

        return response()->json([
            'data' => [
                'message' => 'If the email or phone exists in our system, you will receive password reset instructions.',
            ],
        ]);
    }

    /**
     * Reset password using token (Issue #10).
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        // Find token record (not expired)
        $resetRecords = DB::table('password_reset_tokens')
            ->where('created_at', '>', now()->subHour())
            ->get();

        $resetRecord = null;
        foreach ($resetRecords as $record) {
            if (Hash::check($request->token, $record->token)) {
                $resetRecord = $record;
                break;
            }
        }

        if (! $resetRecord) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'Reset token is invalid or expired',
                ],
            ], 400);
        }

        // Find user by email
        $user = User::where('email', $resetRecord->email)->first();

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'User not found',
                ],
            ], 404);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke all existing tokens (force re-login for security)
        $user->tokens()->delete();

        // Delete used token
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // Audit log: password reset successful (Issue #12)
        AuditLog::log(
            action: 'user.password_changed',
            entityType: 'User',
            entityId: $user->id,
            metadata: ['method' => 'password_reset'],
            userId: $user->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        // TODO: Send confirmation email (deferred to email infrastructure)
        \Log::info('Password reset successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'data' => [
                'message' => 'Password reset successful. Please log in with your new password.',
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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

        if (!$user || !Hash::check($request->password, $user->password)) {
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

        if (!$refreshToken) {
            return response()->json([
                'error' => [
                    'code' => 'MISSING_TOKEN',
                    'message' => 'Refresh token is required',
                ],
            ], 401);
        }

        // Validate refresh token
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
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

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'data' => [
                'message' => 'Successfully logged out',
            ],
        ]);
    }
}

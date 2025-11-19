# ADR-0009: Authentication and Session Management

**Status**: Accepted
**Date**: 2025-11-19
**Deciders**: Architect Agent
**Tags**: [architecture, backend, security, authentication]

## Context and Problem Statement

Aria requires secure authentication for organizers (dashboard access), staff (check-in), and attendees (ticket history). We need:
- **Web sessions**: Persistent login for organizer dashboard (Inertia.js)
- **Mobile tokens**: Long-lived tokens for mobile app
- **API tokens**: Short-lived access tokens for API security
- **2FA support**: SMS OTP for high-value accounts

**Referenced sections**: DESIGN.md Section 12 (Security & Risk - Auth)

## Decision Outcome

**Chosen Approach**: Laravel Sanctum (Token-based Auth) + Session-based Auth for Inertia

### Token Strategy
- **Access Token**: Short-lived (15 minutes), bearer token for API requests
- **Refresh Token**: Long-lived (30 days), HTTP-only cookie for token renewal
- **Session Cookie**: HTTP-only, same-site, secure for Inertia dashboard

### Implementation

**Login Endpoint**:
```php
<?php
public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($credentials)) {
        return $this->errorResponse('INVALID_CREDENTIALS', 'Email or password incorrect', [], 401);
    }

    $user = Auth::user();
    $accessToken = $user->createToken('access', ['*'], now()->addMinutes(15))->plainTextToken;
    $refreshToken = $user->createToken('refresh', ['refresh'], now()->addDays(30))->plainTextToken;

    return response()->json([
        'data' => [
            'user' => $user,
            'access_token' => $accessToken,
        ],
    ])->cookie('refresh_token', $refreshToken, 43200, '/', null, true, true, false, 'strict');
}
```

**2FA with SMS OTP**:
```php
<?php
public function send2FA(Request $request)
{
    $user = Auth::user();
    $otp = rand(100000, 999999);

    Cache::put("2fa:{$user->id}", $otp, now()->addMinutes(5));

    // Send SMS via local aggregator
    SMS::send($user->phone, "Your Aria verification code: {$otp}");

    return $this->successResponse(['message' => 'OTP sent']);
}

public function verify2FA(Request $request)
{
    $user = Auth::user();
    $otp = $request->input('otp');

    if (Cache::get("2fa:{$user->id}") !== (int)$otp) {
        return $this->errorResponse('INVALID_OTP', 'OTP incorrect or expired', [], 401);
    }

    Cache::forget("2fa:{$user->id}");
    $user->update(['2fa_verified_at' => now()]);

    return $this->successResponse(['message' => '2FA verified']);
}
```

## References
- DESIGN.md Section 12: Security & Risk (Auth)
- External: [Laravel Sanctum](https://laravel.com/docs/11.x/sanctum)

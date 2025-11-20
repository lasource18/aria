<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting for authentication endpoints (Issue #11).
     */
    protected function configureRateLimiting(): void
    {
        // Login rate limiting: 5 attempts per IP per 15 minutes
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinutes(15, 5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 900;

                    return response()->json([
                        'error' => [
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Too many login attempts. Please try again in '.ceil($retryAfter / 60).' minutes.',
                            'retry_after_seconds' => $retryAfter,
                        ],
                    ], 429, $headers);
                });
        });

        // Registration rate limiting: 10 attempts per IP per hour
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(10)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 3600;

                    return response()->json([
                        'error' => [
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Too many registration attempts. Please try again later.',
                            'retry_after_seconds' => $retryAfter,
                        ],
                    ], 429, $headers);
                });
        });

        // Password reset rate limiting: 3 attempts per email/phone per hour
        RateLimiter::for('password-reset', function (Request $request) {
            // Use email/phone from request, fallback to IP if not provided (handles malformed requests)
            $key = $request->input('email_or_phone', $request->ip());

            return Limit::perHour(3)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 3600;

                    return response()->json([
                        'error' => [
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Too many password reset attempts. Please try again later.',
                            'retry_after_seconds' => $retryAfter,
                        ],
                    ], 429, $headers);
                });
        });

        // Token refresh rate limiting: 20 attempts per user per hour
        RateLimiter::for('token-refresh', function (Request $request) {
            $userId = $request->user()?->id ?? $request->ip();

            return Limit::perHour(20)
                ->by($userId)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'error' => [
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Too many token refresh attempts.',
                        ],
                    ], 429, $headers);
                });
        });

        // Global API rate limiting: 100 requests per IP per minute
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'error' => [
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Too many API requests.',
                        ],
                    ], 429, $headers);
                });
        });
    }
}

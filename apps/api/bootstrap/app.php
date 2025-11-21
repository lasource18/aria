<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        // Don't redirect API routes to login - let exception handler return JSON
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                throw new AuthenticationException('Unauthenticated.');
            }

            return '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return JSON responses for authentication errors on API routes
        $exceptions->render(function (AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'Unauthenticated.',
                    ],
                ], 401);
            }
        });
    })->create();

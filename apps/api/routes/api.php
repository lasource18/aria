<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrgController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// V1 API Routes with global rate limiting
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    // Public Auth Routes with specific rate limits
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/password/reset-request', [AuthController::class, 'resetRequest'])->middleware('throttle:password-reset');
    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');

    // Protected Auth Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:token-refresh');
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Organization Routes
        Route::get('/orgs', [OrgController::class, 'index']);
        Route::post('/orgs', [OrgController::class, 'store']);
        Route::get('/orgs/{org}', [OrgController::class, 'show']);
        Route::patch('/orgs/{org}', [OrgController::class, 'update']);

        // Organization Member Management
        Route::post('/orgs/{org}/members', [OrgController::class, 'addMember']);
        Route::delete('/orgs/{org}/members/{userId}', [OrgController::class, 'removeMember']);
        Route::patch('/orgs/{org}/members/{userId}', [OrgController::class, 'updateMemberRole']);
    });
});

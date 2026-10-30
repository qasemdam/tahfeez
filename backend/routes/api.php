<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\StudentController;
use Illuminate\Support\Facades\Route;

/*
 * Stateless API (BR-SEC-03): every request carries a bearer token validated by
 * the `auth.api` middleware. No session, no CSRF.
 *
 * Versioned under /api/v1.
 */
Route::prefix('v1')->group(function (): void {
    // Public
    Route::post('auth/login', [AuthController::class, 'login']);

    // Authenticated
    Route::middleware('auth.api')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Only the actions implemented in this slice are exposed.
        Route::apiResource('students', StudentController::class)
            ->only(['index', 'store', 'show']);
    });
});

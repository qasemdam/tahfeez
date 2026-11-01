<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GroupAttendanceController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\MemorizationController;
use App\Http\Controllers\Api\V1\QuranController;
use App\Http\Controllers\Api\V1\RecitationController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\WeeklyEvaluationController;
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

        Route::get('teachers', [TeacherController::class, 'index']);

        Route::apiResource('groups', GroupController::class);

        Route::post('groups/{group}/students', [GroupController::class, 'addStudent']);
        Route::delete('groups/{group}/students/{student}', [GroupController::class, 'removeStudent']);

        Route::apiResource('attendance', AttendanceController::class)
            ->only(['index', 'store', 'show', 'update']);

        Route::get('groups/{group}/attendance', [GroupAttendanceController::class, 'sheet']);
        Route::post('groups/{group}/attendance', [GroupAttendanceController::class, 'store']);

        Route::get('quran/surahs', [QuranController::class, 'surahs']);

        Route::apiResource('memorization', MemorizationController::class);

        Route::apiResource('reviews', ReviewController::class);

        Route::apiResource('recitations', RecitationController::class);

        Route::get('weekly-evaluations', [WeeklyEvaluationController::class, 'index']);
        Route::post('weekly-evaluations', [WeeklyEvaluationController::class, 'store']);
        Route::get('weekly-evaluations/{weeklyEvaluation}', [WeeklyEvaluationController::class, 'show']);
        Route::patch('weekly-evaluations/{weeklyEvaluation}', [WeeklyEvaluationController::class, 'update']);
    });
});

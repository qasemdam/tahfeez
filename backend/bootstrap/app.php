<?php

use App\Domain\Reports\Export\ExportTooLarge;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Bearer-token auth for the stateless API (BR-SEC-03).
        $middleware->alias([
            'auth.api' => AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A report that does not fit a synchronous export is a capacity limit,
        // not a server fault: it gets a stable code and a 422, never a 500.
        $exceptions->render(static function (ExportTooLarge $exception): JsonResponse {
            return response()->json([
                'message' => $exception->userMessage(),
                'error' => 'export_too_large',
                'limits' => $exception->context(),
            ], 422);
        });
    })->create();

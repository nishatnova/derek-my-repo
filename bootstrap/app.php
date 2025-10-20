<?php

use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);


    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ✅ Force JSON response for authentication errors
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'status' => 401,
                    'message' => 'Unauthorized. Please provide a valid token.',
                ], 401);
            }
        });

        // ✅ Catch all other exceptions and ensure JSON response
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'status' => 500,
                    'message' => $e->getMessage(),
                ], 500);
            }
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        // Schedule cleanup of unpaid purchases
        $schedule->command('auth:cleanup-codes')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cleanup-expired-codes.log'));

        // 2. Cleanup unpaid purchases
        // Run every hour to check for purchases older than 24 hours
        $schedule->command('purchases:cleanup-unpaid')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cleanup-unpaid-purchases.log'))
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Unpaid purchases cleanup completed successfully');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Unpaid purchases cleanup failed');
            });
    })
    ->create();

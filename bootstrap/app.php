<?php

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ReturnQuantityExceededException;
use App\Http\Middleware\EnsureRole;
use Illuminate\Console\Scheduling\Schedule;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'failures' => $e->failures,
            ], 422);
        });

        $exceptions->renderable(function (ReturnQuantityExceededException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'failures' => $e->failures,
            ], 422);
        });

        $exceptions->renderable(function (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('frostparts:dashboard-warm')->everyFiveMinutes();
        $schedule->command('frostparts:overdue-installments')->dailyAt('00:00');
        $schedule->command('frostparts:settlement-reminder')->weeklyOn(5, '18:00');
        $schedule->command('frostparts:low-stock-digest')->dailyAt('08:00');
    })
    ->create();

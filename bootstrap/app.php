<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Твой существующий middleware
        $middleware->append(\App\Http\Middleware\LogRequestMiddleware::class);

        // Алиасы
        $middleware->alias([
            'roles' => \App\Http\Middleware\AddUserRolesToView::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Логируем ошибку 419 (TokenMismatchException)
        $exceptions->reportable(function (\Illuminate\Session\TokenMismatchException $e) {
            \Illuminate\Support\Facades\Log::warning('CSRF Token Mismatch (419 Error)', [
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'user_id' => auth()->id() ?? 'guest',
                'referer' => request()->header('referer'),
            ]);
        });

        // Подключаем Sentry для автоматического отлова и отправки ошибок
        // Integration::handles($exceptions);
    })
    ->create();

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/auth.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Google Cross-Account Protection (CAP/RISC) posts security events
        // server-to-server and cannot provide a browser CSRF token.
        $middleware->validateCsrfTokens(except: [
            'auth/google/cap/events',
        ]);

        $middleware->alias([
            'onboarded' => \App\Http\Middleware\EnsureOnboarded::class,
            'feature' => \App\Http\Middleware\EnsureFeatureAccess::class,
            'website.api' => \App\Http\Middleware\WebsiteApiAuth::class,
            'website.features' => \App\Http\Middleware\InjectFeatureFlags::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

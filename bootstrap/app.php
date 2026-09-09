<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\WebApplicationFirewall;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
            WebApplicationFirewall::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'waf' => WebApplicationFirewall::class,
            'security.headers' => SecurityHeaders::class,
        ]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

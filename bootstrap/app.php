<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Coolify (and any TLS-terminating proxy) forwards the public host/scheme;
        // without this, signed /files URLs are generated for https://APP_URL but
        // validated against http://container, which returns 403.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Payment webhooks have no CSRF token; they are authenticated by HMAC signature instead.
        $middleware->validateCsrfTokens(except: ['payments/webhook', 'payments/webhook/monnify']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

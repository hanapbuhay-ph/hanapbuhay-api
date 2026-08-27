<?php

use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsureWorker;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Return null instead of redirecting to the "login" named route.
        // Without this, unauthenticated API requests throw a
        // RouteNotFoundException before the JSON exception handler runs.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'worker' => EnsureWorker::class,
            'client' => EnsureClient::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

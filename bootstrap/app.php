<?php

use App\Http\Middleware\AttachClientId;
use App\Http\Middleware\SetResponseHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve the browser identifier after Laravel decrypts cookies and
        // before route-level throttling chooses its rate-limit key.
        $middleware->web(append: [
            AttachClientId::class,
            SetResponseHeaders::class,
        ]);
        $middleware->prependToPriorityList(
            before: ThrottleRequests::class,
            prepend: AttachClientId::class,
        );

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Use Laravel's default exception rendering.
    })
    ->create();

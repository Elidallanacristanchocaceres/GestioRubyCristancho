<?php

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
        $middleware->statefulApi();
        // La app es API-only: no existe la ruta web "login". Redirigir a
        // route('login') lanzaría RouteNotFoundException (500) en lugar de
        // devolver 401 para las peticiones no autenticadas.
        $middleware->redirectGuestsTo(null);
        $middleware->throttleApi('api');
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->append(\App\Http\Middleware\SanitizeInput::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'permiso' => \App\Http\Middleware\EnsurePermission::class,
            'audita.acceso' => \App\Http\Middleware\LogRecordAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

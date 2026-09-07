<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\DeviceRouting;
use Illuminate\Auth\AuthenticationException;
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
        $middleware->web(append: [
            DeviceRouting::class,
        ]);
        $middleware->alias([
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API-only app: unauthenticated requests must return JSON 401,
        // never redirect to a (non-existent) named 'login' route.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Sesi Anda telah berakhir, silakan login kembali'],
                ], 401);
            }

            return redirect()->guest(route('login'))->with('error', 'Silakan login terlebih dahulu');
        });
    })->create();

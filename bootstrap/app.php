<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Binds the ISP tenant for every web request.
        $middleware->web(append: [
            \App\Http\Middleware\SetCurrentIsp::class,
        ]);

        $middleware->alias([
            'isp' => \App\Http\Middleware\SetCurrentIsp::class,
        ]);

        // Route unauthenticated admins and customers to their own login pages.
        $middleware->redirectGuestsTo(function (Request $request) {
            return $request->is('admin', 'admin/*') || $request->is('loginisp')
                ? route('isp.login')
                : route('customer.login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            return $request->is('admin', 'admin/*') ? route('admin.dashboard') : route('customer.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Never leak stack traces to end users in production.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (config('app.debug')) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'An unexpected error occurred. Please try again.',
                ], 500);
            }

            return null;
        });
    })->create();

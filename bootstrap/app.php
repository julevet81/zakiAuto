<?php

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
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum: required so SPA/mobile clients using cookie-based auth
        // also work; harmless for pure Bearer-token API consumers too.
        $middleware->statefulApi();

        // Spatie Permission middleware aliases, used in routes like:
        //   ->middleware('role:admin')
        //   ->middleware('permission:cars.view')
        //   ->middleware('role_or_permission:admin|cars.view')
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'staff_only' => \App\Http\Middleware\RejectCustomerUsers::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Force consistent JSON error responses for every /api/* request,
        // instead of Laravel's default HTML error pages.
        $exceptions->shouldRenderJsonWhen(function ($request, $throwable) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Spatie Permission throws this when a role/permission check fails
        // via middleware (role:, permission:, role_or_permission:). Map it
        // to a clean 403 JSON response instead of leaking exception internals.
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'لا تملك الصلاحية اللازمة للقيام بهذا الإجراء',
                ], 403);
            }
        });
    })->create();

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            App\Http\Middleware\CorsMiddleware::class,
        ]);
        $middleware->alias([
            'api.token' => App\Http\Middleware\ApiTokenMiddleware::class,
            'role' => App\Http\Middleware\RoleMiddleware::class,
            'permission' => App\Http\Middleware\PermissionMiddleware::class,
            'request.user' => App\Http\Middleware\RequestUserMatchesAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (ValidationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Validation failed.', 400, $exception->errors());
            }
        });

        $exceptions->renderable(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Unauthenticated.', 401);
            }
        });

        $exceptions->renderable(function (AuthorizationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Forbidden.', 403);
            }
        });
    })
    ->create();
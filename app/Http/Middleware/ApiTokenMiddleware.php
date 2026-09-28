<?php

namespace App\Http\Middleware;

use App\Services\ApiTokenService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenMiddleware
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $user = $token ? $this->tokens->resolve($token) : null;
        if (!$user) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $request->setUserResolver(static fn () => $user);
        return $next($request);
    }
}
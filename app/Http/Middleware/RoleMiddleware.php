<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\ApiResponse;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (!$user) return ApiResponse::error('Unauthenticated.', 401);
        if (!in_array($user->role, $roles, true)) return ApiResponse::error('Insufficient role.', 403);
        return $next($request);
    }
}
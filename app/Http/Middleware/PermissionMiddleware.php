<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\ApiResponse;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        $permissions = config('permissions.roles.' . ($user?->role ?? ''), []);
        $allowed = in_array($permission, $permissions, true)
            || in_array(strtok($permission, '.') . '.*', $permissions, true);

        if (!$user) return ApiResponse::error('Unauthenticated.', 401);
        if (!$allowed) return ApiResponse::error('Insufficient permission.', 403);
        return $next($request);
    }
}
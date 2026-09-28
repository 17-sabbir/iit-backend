<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\ApiResponse;

class RequestUserMatchesAuth
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return ApiResponse::error('Unauthenticated.', 401);

        if (in_array($user->role, ['Librarian', 'Director'], true)) {
            return $next($request);
        }

        $requestedEmail = $request->input('user_email')
            ?? $request->input('email')
            ?? $request->query('user_email')
            ?? $request->query('email');

        if ($requestedEmail !== null) {
            if (!hash_equals(strtolower($user->email), strtolower((string) $requestedEmail))) {
                return ApiResponse::error('You may only access your own account data.', 403);
            }
        }

        return $next($request);
    }
}
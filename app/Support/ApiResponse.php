<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(array $data = [], int $status = 200, ?string $message = null): JsonResponse
    {
        $payload = ['success' => true];
        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json(array_merge($payload, $data), $status);
    }

    public static function error(string $message, int $status, mixed $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
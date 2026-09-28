<?php

namespace Tests\Unit;

use App\Support\ApiResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_response_keeps_frontend_contract(): void
    {
        $response = ApiResponse::success(['token' => 'test-token'], 201, 'Created');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'message' => 'Created',
            'token' => 'test-token',
        ], $response->getData(true));
    }

    public function test_error_response_has_stable_validation_shape(): void
    {
        $response = ApiResponse::error('Validation failed.', 400, ['email' => ['Email is required.']]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => ['email' => ['Email is required.']],
        ], $response->getData(true));
    }
}

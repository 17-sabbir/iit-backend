<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LegacyApiCompatibilityTest extends TestCase
{
    public function test_legacy_login_url_is_registered_as_a_post_route(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/auth/login.php');

        $this->assertNotNull($route);
        $this->assertTrue($route->methods() === ['POST', 'HEAD'] || in_array('POST', $route->methods(), true));
    }

    public function test_registered_routes_do_not_dispatch_to_legacy_php(): void
    {
        $legacyRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->getActionName(), 'LegacyApiController'));

        $this->assertCount(0, $legacyRoutes);
    }
}

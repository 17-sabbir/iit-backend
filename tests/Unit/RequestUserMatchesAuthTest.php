<?php

namespace Tests\Unit;

use App\Http\Middleware\RequestUserMatchesAuth;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class RequestUserMatchesAuthTest extends TestCase
{
    public function test_user_can_access_own_request_data(): void
    {
        $request = Request::create('/api/profile', 'GET', ['email' => 'student@iit.edu']);
        $request->setUserResolver(fn () => $this->user('student@iit.edu', 'Student'));

        $response = app(RequestUserMatchesAuth::class)->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_user_cannot_access_another_users_request_data(): void
    {
        $request = Request::create('/api/profile', 'GET', ['email' => 'other@iit.edu']);
        $request->setUserResolver(fn () => $this->user('student@iit.edu', 'Student'));

        $response = app(RequestUserMatchesAuth::class)->handle($request, fn () => response('ok'));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(false, json_decode($response->getContent(), true)['success']);
    }

    public function test_librarian_can_manage_other_users_requests(): void
    {
        $request = Request::create('/api/admin', 'POST', ['user_email' => 'student@iit.edu']);
        $request->setUserResolver(fn () => $this->user('librarian@iit.edu', 'Librarian'));

        $response = app(RequestUserMatchesAuth::class)->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    private function user(string $email, string $role): User
    {
        $user = new User();
        $user->forceFill(['email' => $email, 'role' => $role]);
        return $user;
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    public function login(string $email, string $password): array
    {
        $user = User::where('email', strtolower(trim($email)))->first();
        if (!$user || !Hash::check($password, $user->password_hash)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        $user->forceFill(['last_login' => now()])->save();
        return ['user' => $user, 'token' => $this->tokens->issue($user)];
    }

    public function register(array $data): User
    {
        $user = new User();
        $user->forceFill([
            'email' => strtolower(trim($data['email'])),
            'name' => $data['name'],
            'password_hash' => Hash::make($data['password']),
            'role' => 'Student',
            'contact' => $data['contact'] ?? null,
        ]);
        $user->save();
        return $user;
    }

    public function logout(?User $user, ?string $token): void
    {
        if ($token) $this->tokens->revoke($token);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password_hash)) {
            throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
        }

        $user->forceFill(['password_hash' => Hash::make($newPassword)])->save();
        $this->tokens->revokeAll($user);
    }

    public function sendResetLink(string $email): string
    {
        return Password::sendResetLink(['email' => strtolower(trim($email))]);
    }

    public function resetPassword(array $credentials): string
    {
        return Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill(['password_hash' => Hash::make($password)])->save();
            $this->tokens->revokeAll($user);
        });
    }
}
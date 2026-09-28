<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ApiTokenService
{
    private const TOKEN_PREFIX = 'api-token:';
    private const USER_PREFIX = 'api-user-tokens:';

    public function issue(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $cache = Cache::store();
        $ttl = now()->addDays(30);
        $cache->put(self::TOKEN_PREFIX . $tokenHash, $user->email, $ttl);

        $userKey = self::USER_PREFIX . hash('sha256', strtolower($user->email));
        $tokens = $cache->get($userKey, []);
        $tokens[] = $tokenHash;
        $cache->put($userKey, array_values(array_unique($tokens)), $ttl);

        return $token;
    }

    public function resolve(string $token): ?User
    {
        $email = Cache::store()->get(self::TOKEN_PREFIX . hash('sha256', $token));
        return is_string($email) ? User::query()->where('email', $email)->first() : null;
    }

    public function revoke(string $token): void
    {
        $hash = hash('sha256', $token);
        $cache = Cache::store();
        $key = self::TOKEN_PREFIX . $hash;
        $email = $cache->get($key);
        $cache->forget($key);
        if (!is_string($email)) return;

        $userKey = self::USER_PREFIX . hash('sha256', strtolower($email));
        $tokens = array_values(array_filter(
            $cache->get($userKey, []),
            static fn ($storedHash) => $storedHash !== $hash,
        ));
        if ($tokens === []) {
            $cache->forget($userKey);
        } else {
            $cache->put($userKey, $tokens, now()->addDays(30));
        }
    }

    public function revokeAll(User $user): void
    {
        $cache = Cache::store();
        $userKey = self::USER_PREFIX . hash('sha256', strtolower($user->email));
        foreach ($cache->get($userKey, []) as $hash) {
            $cache->forget(self::TOKEN_PREFIX . $hash);
        }
        $cache->forget($userKey);
    }
}
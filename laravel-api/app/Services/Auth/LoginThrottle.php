<?php

namespace App\Services\Auth;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate-limit đăng nhập: đếm số lần THẤT BẠI theo (IP + email) trong cửa sổ thời gian.
 * Vượt ngưỡng → 429. Đăng nhập thành công sẽ clear bộ đếm.
 */
class LoginThrottle
{
    public function ensureNotThrottled(string $email, ?string $ip): void
    {
        $key = $this->key($email, $ip);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts())) {
            $seconds = RateLimiter::availableIn($key);
            throw new ThrottleRequestsException(
                "Too many login attempts. Please try again in {$seconds} seconds."
            );
        }
    }

    public function recordFailure(string $email, ?string $ip): void
    {
        RateLimiter::hit($this->key($email, $ip), (int) config('auth_security.login_decay_seconds'));
    }

    public function clear(string $email, ?string $ip): void
    {
        RateLimiter::clear($this->key($email, $ip));
    }

    private function maxAttempts(): int
    {
        return (int) config('auth_security.login_max_attempts');
    }

    private function key(string $email, ?string $ip): string
    {
        return 'login:'.sha1(mb_strtolower($email).'|'.((string) $ip));
    }
}

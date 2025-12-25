<?php

namespace App\Http\RateLimiters;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Str;

class CustomRateLimiter
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Rate limiter للـ API العامة
     */
    public function forApi(string $key, int $maxAttempts = 60, int $decaySeconds = 60): bool
    {
        return $this->limiter->tooManyAttempts(
            'api:' . $key,
            $maxAttempts,
            $decaySeconds
        );
    }

    /**
     * Rate limiter للمصادقة
     */
    public function forAuth(string $key, int $maxAttempts = 5, int $decaySeconds = 300): bool
    {
        return $this->limiter->tooManyAttempts(
            'auth:' . $key,
            $maxAttempts,
            $decaySeconds
        );
    }

    /**
     * Rate limiter للبحث
     */
    public function forSearch(string $key, int $maxAttempts = 30, int $decaySeconds = 60): bool
    {
        return $this->limiter->tooManyAttempts(
            'search:' . $key,
            $maxAttempts,
            $decaySeconds
        );
    }

    /**
     * زيادة عدد المحاولات
     */
    public function hit(string $key, string $type = 'api', int $decaySeconds = 60): int
    {
        return $this->limiter->hit(
            $type . ':' . $key,
            $decaySeconds
        );
    }

    /**
     * الحصول على عدد المحاولات المتبقية
     */
    public function retriesLeft(string $key, string $type = 'api', int $maxAttempts = 60): int
    {
        return $this->limiter->retriesLeft(
            $type . ':' . $key,
            $maxAttempts
        );
    }

    /**
     * مسح الـ Rate Limiter
     */
    public function clear(string $key, string $type = 'api'): void
    {
        $this->limiter->clear($type . ':' . $key);
    }
}

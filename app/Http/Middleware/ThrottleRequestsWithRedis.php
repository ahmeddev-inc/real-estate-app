<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ThrottleRequestsWithRedis
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $maxAttempts = 60, $decaySeconds = 60, $prefix = ''): Response
    {
        $key = $this->resolveRequestSignature($request, $prefix);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            Log::warning('Rate limit exceeded', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'endpoint' => $request->path(),
                'key' => $key
            ]);

            return $this->buildResponse($key, $maxAttempts);
        }

        $this->limiter->hit($key, $decaySeconds);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * إنشاء مفتاح فريد للطلب
     */
    protected function resolveRequestSignature(Request $request, string $prefix = ''): string
    {
        // استخدام IP + User Agent + Endpoint
        $signature = sha1(
            $request->ip() . '|' .
            $request->userAgent() . '|' .
            $request->path()
        );

        // إضافة البادئة إذا كانت موجودة
        if ($prefix) {
            return $prefix . ':' . $signature;
        }

        // تحديد البادئة حسب نوع الـ endpoint
        if (Str::startsWith($request->path(), 'api/auth')) {
            return 'auth:' . $signature;
        }

        if (Str::contains($request->path(), 'search')) {
            return 'search:' . $signature;
        }

        return 'api:' . $signature;
    }

    /**
     * بناء رد عند تجاوز الحد
     */
    protected function buildResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'success' => false,
            'message' => 'لقد تجاوزت الحد المسموح به من الطلبات. الرجاء المحاولة مرة أخرى بعد ' . $retryAfter . ' ثانية.',
            'retry_after' => $retryAfter,
            'remaining' => 0,
            'limit' => $maxAttempts
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => time() + $retryAfter,
        ]);
    }

    /**
     * إضافة headers للـ rate limit
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ]);

        return $response;
    }

    /**
     * حساب المحاولات المتبقية
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->limiter->attempts($key));
    }
}

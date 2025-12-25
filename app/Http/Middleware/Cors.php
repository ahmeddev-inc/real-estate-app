<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $environment = app()->environment();
        $corsConfig = config('cors');
        $envConfig = $corsConfig['environments'][$environment] ?? [];
        
        // دمج الإعدادات العامة مع إعدادات البيئة
        $allowedOrigins = $envConfig['allowed_origins'] ?? $corsConfig['allowed_origins'];
        $supportsCredentials = $envConfig['supports_credentials'] ?? $corsConfig['supports_credentials'];
        $maxAge = $envConfig['max_age'] ?? $corsConfig['max_age'];

        $response = $next($request);

        // التحقق من Origin
        $origin = $request->header('Origin');
        
        if ($origin && in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        } elseif (in_array('*', $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        // إضافة الـ CORS headers
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $corsConfig['allowed_methods']));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', $corsConfig['allowed_headers']));
        $response->headers->set('Access-Control-Expose-Headers', implode(', ', $corsConfig['exposed_headers']));
        $response->headers->set('Access-Control-Max-Age', $maxAge);

        if ($supportsCredentials) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        // إضافة headers أمنية
        foreach ($corsConfig['security_headers'] as $header => $value) {
            if ($value) {
                $response->headers->set($header, $value);
            }
        }

        // معالجة طلبات OPTIONS (preflight)
        if ($request->isMethod('OPTIONS')) {
            $response->setStatusCode(200);
            $response->setContent('');
        }

        return $response;
    }
}

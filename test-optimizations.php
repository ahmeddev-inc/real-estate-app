<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

echo "🧪 اختبار تحسينات الأداء\n";
echo "========================\n\n";

// اختبار Redis connection
try {
    Redis::ping();
    echo "✅ Redis connection: OK\n";
} catch (Exception $e) {
    echo "❌ Redis connection: FAILED - " . $e->getMessage() . "\n";
}

// اختبار Rate Limiting
try {
    $key = 'test:' . uniqid();
    $limiter = app(\Illuminate\Cache\RateLimiter::class);
    
    for ($i = 1; $i <= 3; $i++) {
        $limiter->hit($key, 60);
        $attempts = $limiter->attempts($key);
        echo "🔢 Rate Limiting attempt {$i}: {$attempts} attempts\n";
    }
    
    echo "✅ Rate Limiting: WORKING\n";
} catch (Exception $e) {
    echo "❌ Rate Limiting: FAILED - " . $e->getMessage() . "\n";
}

// اختبار Query Cache
try {
    $cacheKey = 'test_query_cache';
    $testData = ['test' => 'data'];
    
    Cache::store('query_cache')->put($cacheKey, $testData, 60);
    $retrieved = Cache::store('query_cache')->get($cacheKey);
    
    if ($retrieved === $testData) {
        echo "✅ Query Cache: WORKING\n";
    } else {
        echo "❌ Query Cache: FAILED - Data mismatch\n";
    }
} catch (Exception $e) {
    echo "❌ Query Cache: FAILED - " . $e->getMessage() . "\n";
}

// اختبار CORS headers
echo "\n🌐 CORS Headers Configuration:\n";
$corsConfig = include __DIR__ . '/config/cors.php';
echo "   Allowed Origins: " . implode(', ', $corsConfig['allowed_origins']) . "\n";
echo "   Allowed Methods: " . implode(', ', $corsConfig['allowed_methods']) . "\n";
echo "   Max Age: " . $corsConfig['max_age'] . " seconds\n";

echo "\n🎉 اختبار التحسينات مكتمل!\n";

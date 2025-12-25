<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\Query\Builder;

class QueryCacheService
{
    protected $enabled;
    protected $ttl;
    protected $store;
    protected $excludeTables;

    public function __construct()
    {
        $this->enabled = config('cache.query_cache.enabled', true);
        $this->ttl = config('cache.query_cache.ttl', 300);
        $this->store = config('cache.query_cache.store', 'query_cache');
        $this->excludeTables = config('cache.query_cache.exclude_tables', []);
    }

    /**
     * تخزين استعلام في الكاش
     */
    public function cacheQuery(string $query, array $bindings, $results, ?int $ttl = null): void
    {
        if (!$this->enabled || $this->shouldExcludeQuery($query)) {
            return;
        }

        $key = $this->generateCacheKey($query, $bindings);
        $ttl = $ttl ?? $this->ttl;

        try {
            Cache::store($this->store)->put($key, $results, $ttl);
            
            Log::debug('Query cached', [
                'key' => $key,
                'ttl' => $ttl,
                'query' => Str::limit($query, 100),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cache query', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);
        }
    }

    /**
     * استرجاع استعلام من الكاش
     */
    public function getCachedQuery(string $query, array $bindings)
    {
        if (!$this->enabled || $this->shouldExcludeQuery($query)) {
            return null;
        }

        $key = $this->generateCacheKey($query, $bindings);

        try {
            $cached = Cache::store($this->store)->get($key);
            
            if ($cached !== null) {
                Log::debug('Query retrieved from cache', [
                    'key' => $key,
                    'query' => Str::limit($query, 100),
                ]);
                
                return $cached;
            }
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cached query', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);
        }

        return null;
    }

    /**
     * تنفيذ استعلام مع الكاش
     */
    public function executeWithCache(string $query, array $bindings = [], ?int $ttl = null)
    {
        // محاولة الاسترجاع من الكاش
        $cached = $this->getCachedQuery($query, $bindings);
        
        if ($cached !== null) {
            return $cached;
        }

        // تنفيذ الاستعلام
        $results = DB::select($query, $bindings);

        // تخزين في الكاش
        $this->cacheQuery($query, $bindings, $results, $ttl);

        return $results;
    }

    /**
     * مسح كاش جدول معين
     */
    public function clearTableCache(string $table): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $pattern = $this->store . ':*' . strtolower($table) . '*';
            
            // في Redis نستخدم SCAN لحذف المفاتيح المتطابقة
            $this->clearRedisKeysByPattern($pattern);
            
            Log::info('Table cache cleared', ['table' => $table]);
        } catch (\Exception $e) {
            Log::error('Failed to clear table cache', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * مسح كاش جميع الجداول
     */
    public function clearAllCache(): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            Cache::store($this->store)->clear();
            Log::info('All query cache cleared');
        } catch (\Exception $e) {
            Log::error('Failed to clear all cache', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * إنشاء مفتاح فريد للاستعلام
     */
    protected function generateCacheKey(string $query, array $bindings): string
    {
        $normalizedQuery = $this->normalizeQuery($query);
        $key = md5($normalizedQuery . serialize($bindings));
        
        return 'query:' . $key;
    }

    /**
     * تطبيع الاستعلام (إزالة المسافات الزائدة والتعليقات)
     */
    protected function normalizeQuery(string $query): string
    {
        // إزالة التعليقات
        $query = preg_replace('/\/\*.*?\*\//s', '', $query);
        $query = preg_replace('/--.*$/m', '', $query);
        
        // إزالة المسافات الزائدة
        $query = preg_replace('/\s+/', ' ', $query);
        
        return trim($query);
    }

    /**
     * التحقق إذا كان الاستعلام مستثنى من الكاش
     */
    protected function shouldExcludeQuery(string $query): bool
    {
        $query = strtolower($query);
        
        // استبعاد استعلامات الكتابة
        if (preg_match('/^\s*(insert|update|delete|truncate|drop|create|alter)/i', $query)) {
            return true;
        }

        // استبعاد الجداول المحددة
        foreach ($this->excludeTables as $table) {
            if (str_contains($query, strtolower($table))) {
                return true;
            }
        }

        return false;
    }

    /**
     * مسح مفاتيح Redis بناءً على النمط
     */
    private function clearRedisKeysByPattern(string $pattern): void
    {
        // هذا يعتمد على استخدام Redis
        try {
            $redis = Cache::store($this->store)->getRedis();
            
            // لـ Laravel مع predis/redis
            $keys = $redis->keys($pattern);
            
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear Redis keys by pattern', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * إحصائيات استخدام الكاش
     */
    public function getCacheStats(): array
    {
        if (!$this->enabled) {
            return ['enabled' => false];
        }

        try {
            $redis = Cache::store($this->store)->getRedis();
            
            return [
                'enabled' => true,
                'store' => $this->store,
                'ttl' => $this->ttl,
                'memory_usage' => $redis->info('memory')['used_memory'] ?? null,
                'keys_count' => count($redis->keys('query:*')),
            ];
        } catch (\Exception $e) {
            return [
                'enabled' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cache Builder Macro لـ Eloquent
     */
    public static function registerMacros(): void
    {
        Builder::macro('cache', function (?int $ttl = null) {
            $service = app(QueryCacheService::class);
            $query = $this->toSql();
            $bindings = $this->getBindings();
            
            return $service->executeWithCache($query, $bindings, $ttl);
        });

        Builder::macro('remember', function (int $ttl = 300) {
            return $this->cache($ttl);
        });
    }
}

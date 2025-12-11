<?php

namespace App\Core\Cache;

use App\Core\Cache\Cache;

/**
 * Auto-Caching Helper
 * 
 * Automatically caches:
 * - DB queries
 * - Route compilation
 * - View rendering
 */
class AutoCache
{
    /**
     * Cache database query result
     */
    public static function cacheQuery(string $sql, array $tables, callable $callback, int $ttl = 3600)
    {
        $cacheKey = 'db_query:' . md5($sql);
        
        return Cache::rememberStatic($cacheKey, function() use ($callback, $tables, $cacheKey, $ttl) {
            $result = $callback();
            
            // Tag cache with table names for invalidation
            foreach ($tables as $table) {
                Cache::getInstance()->tag($table)->set($cacheKey, $result, $ttl);
            }
            
            return $result;
        }, $ttl);
    }

    /**
     * Cache route compilation
     */
    public static function cacheRoute(string $route, callable $callback, int $ttl = 86400)
    {
        $cacheKey = 'route:' . md5($route);
        return Cache::rememberStatic($cacheKey, $callback, $ttl);
    }

    /**
     * Cache view rendering
     */
    public static function cacheView(string $view, array $data, callable $callback, int $ttl = 3600)
    {
        $cacheKey = 'view:' . md5($view . serialize($data));
        return Cache::rememberStatic($cacheKey, $callback, $ttl);
    }

    /**
     * Invalidate cache for table
     */
    public static function invalidateTable(string $table): void
    {
        Cache::getInstance()->forgetTag($table);
    }

    /**
     * Invalidate all route cache
     */
    public static function invalidateRoutes(): void
    {
        // Clear all route cache
        $cache = Cache::getInstance();
        // This would need to track route cache keys, simplified here
        Cache::deleteStatic('php_palm_routes');
    }

    /**
     * Invalidate view cache
     */
    public static function invalidateViews(): void
    {
        Cache::getInstance()->forgetTag('views');
    }
}


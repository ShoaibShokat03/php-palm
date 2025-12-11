<?php

namespace App\Core;

use App\Core\Cache\Cache;

/**
 * Route Cache
 * Caches compiled routes for faster dispatch
 */
class RouteCache
{
    protected static string $cacheKey = 'php_palm_routes';
    protected static int $cacheTtl = 86400; // 24 hours

    /**
     * Cache routes
     */
    public static function cache(array $routes): void
    {
        Cache::setStatic(self::$cacheKey, $routes, self::$cacheTtl);
    }

    /**
     * Get cached routes
     */
    public static function getCached(): ?array
    {
        return Cache::getStatic(self::$cacheKey);
    }

    /**
     * Clear route cache
     */
    public static function clear(): void
    {
        Cache::deleteStatic(self::$cacheKey);
    }

    /**
     * Check if routes are cached
     */
    public static function isCached(): bool
    {
        return Cache::hasStatic(self::$cacheKey);
    }
}


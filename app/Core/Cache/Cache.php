<?php

namespace App\Core\Cache;

/**
 * Unified Cache Interface
 * 
 * Supports multiple cache stores:
 * - File cache (default)
 * - APCu (when available)
 * - In-memory cache
 * 
 * Features:
 * - Tag-based cache invalidation
 * - Configurable TTL
 * - Auto cache DB queries
 */
class Cache
{
    protected static Cache $instance;
    protected CacheStoreInterface $store;
    protected array $tags = [];

    /**
     * Get cache instance
     */
    public static function getInstance(): Cache
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - auto-selects best cache store
     * Priority: Redis > Memcached > APCu > File
     */
    public function __construct()
    {
        // Try Redis first (if configured and available)
        if (class_exists('\Redis') && !empty($_ENV['REDIS_HOST'] ?? '')) {
            $redisStore = new RedisStore();
            if ($redisStore->has('test')) {
                $this->store = $redisStore;
                return;
            }
        }
        
        // Try Memcached (if configured and available)
        if (class_exists('\Memcached') && !empty($_ENV['MEMCACHED_SERVERS'] ?? '')) {
            $memcachedStore = new MemcachedStore();
            if ($memcachedStore->has('test')) {
                $this->store = $memcachedStore;
                return;
            }
        }
        
        // Try APCu (fastest in-memory)
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $this->store = new ApcuStore();
            return;
        }
        
        // Fallback to file cache
        $cacheDir = __DIR__ . '/../../../storage/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $this->store = new FileStore($cacheDir);
    }

    /**
     * Get value from cache
     */
    public function get(string $key, $default = null)
    {
        return $this->store->get($key, $default);
    }

    /**
     * Set value in cache
     */
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        return $this->store->set($key, $value, $ttl);
    }

    /**
     * Check if key exists
     */
    public function has(string $key): bool
    {
        return $this->store->has($key);
    }

    /**
     * Delete key from cache
     */
    public function delete(string $key): bool
    {
        return $this->store->delete($key);
    }

    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        return $this->store->clear();
    }

    /**
     * Remember - get or compute and cache
     */
    public function remember(string $key, callable $callback, int $ttl = 3600)
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * Tag-based cache operations
     */
    public function tag(string $tag): self
    {
        $this->tags[] = $tag;
        return $this;
    }

    /**
     * Invalidate cache by tag
     */
    public function forgetTag(string $tag): bool
    {
        return $this->store->forgetTag($tag);
    }

    /**
     * Static helper methods
     */
    public static function getStatic(string $key, $default = null)
    {
        return self::getInstance()->get($key, $default);
    }

    public static function setStatic(string $key, $value, int $ttl = 3600): bool
    {
        return self::getInstance()->set($key, $value, $ttl);
    }

    public static function hasStatic(string $key): bool
    {
        return self::getInstance()->has($key);
    }

    public static function deleteStatic(string $key): bool
    {
        return self::getInstance()->delete($key);
    }

    public static function rememberStatic(string $key, callable $callback, int $ttl = 3600)
    {
        return self::getInstance()->remember($key, $callback, $ttl);
    }
}


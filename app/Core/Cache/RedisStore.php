<?php

namespace App\Core\Cache;

/**
 * Redis Cache Store
 * 
 * Note: Requires PHP redis extension
 * Falls back to FileStore if not available
 */
class RedisStore implements CacheStoreInterface
{
    protected ?\Redis $redis = null;
    protected string $prefix = 'php_palm_cache:';

    public function __construct()
    {
        if (class_exists('\Redis')) {
            $this->redis = new \Redis();
            
            $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            $password = $_ENV['REDIS_PASSWORD'] ?? null;
            
            try {
                $this->redis->connect($host, $port);
                
                if ($password !== null) {
                    $this->redis->auth($password);
                }
            } catch (\Exception $e) {
                $this->redis = null;
            }
        }
    }

    public function get(string $key, $default = null)
    {
        if ($this->redis === null) {
            return $default;
        }

        $fullKey = $this->prefix . $key;
        $value = $this->redis->get($fullKey);
        
        if ($value === false) {
            return $default;
        }

        return unserialize($value);
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        if ($this->redis === null) {
            return false;
        }

        $fullKey = $this->prefix . $key;
        $serialized = serialize($value);
        
        if ($ttl > 0) {
            return $this->redis->setex($fullKey, $ttl, $serialized);
        }
        
        return $this->redis->set($fullKey, $serialized);
    }

    public function has(string $key): bool
    {
        if ($this->redis === null) {
            return false;
        }

        $fullKey = $this->prefix . $key;
        return $this->redis->exists($fullKey) > 0;
    }

    public function delete(string $key): bool
    {
        if ($this->redis === null) {
            return false;
        }

        $fullKey = $this->prefix . $key;
        return $this->redis->del($fullKey) > 0;
    }

    public function clear(): bool
    {
        if ($this->redis === null) {
            return false;
        }

        $keys = $this->redis->keys($this->prefix . '*');
        if (empty($keys)) {
            return true;
        }

        return $this->redis->del($keys) > 0;
    }

    public function forgetTag(string $tag): bool
    {
        if ($this->redis === null) {
            return false;
        }

        // Use Redis sets for tag tracking
        $tagKey = $this->prefix . 'tag:' . $tag;
        $keys = $this->redis->smembers($tagKey);
        
        if (!empty($keys)) {
            $this->redis->del($keys);
            $this->redis->del($tagKey);
        }

        return true;
    }
}


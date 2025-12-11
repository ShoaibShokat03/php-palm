<?php

namespace App\Core\Cache;

/**
 * Memcached Cache Store
 * 
 * Note: Requires PHP memcached extension
 * Falls back to FileStore if not available
 */
class MemcachedStore implements CacheStoreInterface
{
    protected ?\Memcached $memcached = null;
    protected string $prefix = 'php_palm_cache:';

    public function __construct()
    {
        if (class_exists('\Memcached')) {
            $this->memcached = new \Memcached();
            // Configure servers from environment
            $servers = $this->getServers();
            if (!empty($servers)) {
                $this->memcached->addServers($servers);
            } else {
                // Default localhost
                $this->memcached->addServer('localhost', 11211);
            }
        }
    }

    protected function getServers(): array
    {
        $servers = [];
        $config = $_ENV['MEMCACHED_SERVERS'] ?? '';
        
        if (empty($config)) {
            return [];
        }

        $lines = explode(',', $config);
        foreach ($lines as $line) {
            $parts = explode(':', trim($line));
            $host = $parts[0] ?? 'localhost';
            $port = (int)($parts[1] ?? 11211);
            $servers[] = [$host, $port];
        }

        return $servers;
    }

    public function get(string $key, $default = null)
    {
        if ($this->memcached === null) {
            return $default;
        }

        $fullKey = $this->prefix . $key;
        $value = $this->memcached->get($fullKey);
        
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return $default;
        }

        return $value;
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        if ($this->memcached === null) {
            return false;
        }

        $fullKey = $this->prefix . $key;
        return $this->memcached->set($fullKey, $value, $ttl);
    }

    public function has(string $key): bool
    {
        if ($this->memcached === null) {
            return false;
        }

        $fullKey = $this->prefix . $key;
        $this->memcached->get($fullKey);
        return $this->memcached->getResultCode() === \Memcached::RES_SUCCESS;
    }

    public function delete(string $key): bool
    {
        if ($this->memcached === null) {
            return false;
        }

        $fullKey = $this->prefix . $key;
        return $this->memcached->delete($fullKey);
    }

    public function clear(): bool
    {
        if ($this->memcached === null) {
            return false;
        }

        return $this->memcached->flush();
    }

    public function forgetTag(string $tag): bool
    {
        // Memcached doesn't support tags natively
        // We'd need to maintain a tag index, which is complex
        // For now, return true (tags would need custom implementation)
        return true;
    }
}


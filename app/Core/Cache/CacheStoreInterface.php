<?php

namespace App\Core\Cache;

/**
 * Cache Store Interface
 */
interface CacheStoreInterface
{
    public function get(string $key, $default = null);
    public function set(string $key, $value, int $ttl = 3600): bool;
    public function has(string $key): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function forgetTag(string $tag): bool;
}


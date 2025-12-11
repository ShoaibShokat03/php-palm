<?php

namespace App\Core\Cache;

/**
 * APCu Cache Store
 * Fastest cache store using PHP's APCu extension
 */
class ApcuStore implements CacheStoreInterface
{
    protected string $prefix = 'php_palm_cache:';

    public function get(string $key, $default = null)
    {
        $fullKey = $this->prefix . $key;
        $success = false;
        $value = apcu_fetch($fullKey, $success);
        return $success ? $value : $default;
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $fullKey = $this->prefix . $key;
        return apcu_store($fullKey, $value, $ttl);
    }

    public function has(string $key): bool
    {
        $fullKey = $this->prefix . $key;
        return apcu_exists($fullKey);
    }

    public function delete(string $key): bool
    {
        $fullKey = $this->prefix . $key;
        return apcu_delete($fullKey);
    }

    public function clear(): bool
    {
        return apcu_clear_cache();
    }

    public function forgetTag(string $tag): bool
    {
        // APCu doesn't support tags natively, so we use a pattern
        $iterator = new \APCUIterator('/^' . preg_quote($this->prefix . 'tag:' . $tag . ':', '/') . '/');
        foreach ($iterator as $item) {
            apcu_delete($item['key']);
        }
        return true;
    }
}


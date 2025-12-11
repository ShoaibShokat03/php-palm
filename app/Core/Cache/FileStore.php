<?php

namespace App\Core\Cache;

/**
 * File-based Cache Store
 * Fallback cache store using filesystem
 */
class FileStore implements CacheStoreInterface
{
    protected string $cacheDir;
    protected string $tagDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/') . '/';
        $this->tagDir = $this->cacheDir . 'tags/';
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        if (!is_dir($this->tagDir)) {
            mkdir($this->tagDir, 0755, true);
        }
    }

    protected function getFilePath(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . substr($hash, 0, 2) . '/' . $hash . '.cache';
    }

    protected function getTagFilePath(string $tag): string
    {
        return $this->tagDir . md5($tag) . '.tag';
    }

    public function get(string $key, $default = null)
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return $default;
        }

        $data = unserialize(file_get_contents($file));
        
        // Check expiration
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);
        $dir = dirname($file);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'created' => time()
        ];

        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    public function has(string $key): bool
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return false;
        }

        $data = unserialize(file_get_contents($file));
        
        // Check expiration
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function clear(): bool
    {
        $this->deleteDirectory($this->cacheDir);
        mkdir($this->cacheDir, 0755, true);
        return true;
    }

    public function forgetTag(string $tag): bool
    {
        $tagFile = $this->getTagFilePath($tag);
        
        if (!file_exists($tagFile)) {
            return true;
        }

        $keys = unserialize(file_get_contents($tagFile));
        
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return unlink($tagFile);
    }

    protected function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}


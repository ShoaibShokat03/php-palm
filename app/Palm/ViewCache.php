<?php

namespace Frontend\Palm;

/**
 * View Cache System
 * 
 * Caches compiled views for faster rendering
 * 
 * Usage:
 * - Automatically enabled in production
 * - Use `palm view:clear` to clear cache
 */
class ViewCache
{
    protected static string $cacheDir = '';
    protected static bool $enabled = true;
    protected static array $cache = [];

    /**
     * Initialize view cache
     */
    public static function init(string $baseDir): void
    {
        // Set cache directory
        $projectRoot = defined('PALM_ROOT') ? PALM_ROOT : dirname($baseDir);
        $cacheDir = $projectRoot . '/storage/cache/views';
        
        // Create directory if it doesn't exist
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        
        self::$cacheDir = $cacheDir;
        
        // Check if cache is enabled
        self::$enabled = !self::isDevelopment();
    }

    /**
     * Check if we're in development mode
     */
    protected static function isDevelopment(): bool
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
        return strtolower($env) === 'development' || strtolower($env) === 'dev';
    }

    /**
     * Get cache file path for a view
     */
    protected static function getCacheFilePath(string $viewPath): string
    {
        $hash = md5($viewPath);
        return self::$cacheDir . '/' . $hash . '.php';
    }

    /**
     * Check if view is cached and valid
     */
    public static function exists(string $viewPath): bool
    {
        if (!self::$enabled) {
            return false;
        }

        $cacheFile = self::getCacheFilePath($viewPath);
        if (!file_exists($cacheFile)) {
            return false;
        }

        // Check if view file is newer than cache
        if (file_exists($viewPath) && filemtime($viewPath) > filemtime($cacheFile)) {
            return false; // View file changed, cache invalid
        }

        return true;
    }

    /**
     * Get cached view path
     */
    public static function get(string $viewPath): ?string
    {
        if (!self::exists($viewPath)) {
            return null;
        }

        return self::getCacheFilePath($viewPath);
    }

    /**
     * Cache a view (compile and save)
     */
    public static function put(string $viewPath, string $compiledContent): bool
    {
        if (!self::$enabled) {
            return false;
        }

        $cacheFile = self::getCacheFilePath($viewPath);
        
        // Write to file atomically
        $tempFile = $cacheFile . '.tmp';
        if (file_put_contents($tempFile, $compiledContent, LOCK_EX) !== false) {
            return rename($tempFile, $cacheFile);
        }

        return false;
    }

    /**
     * Clear view cache
     */
    public static function clear(?string $viewPath = null): bool
    {
        if ($viewPath !== null) {
            // Clear specific view
            $cacheFile = self::getCacheFilePath($viewPath);
            if (file_exists($cacheFile)) {
                return unlink($cacheFile);
            }
            return true;
        }

        // Clear all views
        $files = glob(self::$cacheDir . '/*.php');
        $success = true;
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Get cache directory
     */
    public static function getCacheDir(): string
    {
        return self::$cacheDir;
    }

    /**
     * Check if caching is enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
}


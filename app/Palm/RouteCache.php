<?php

namespace Frontend\Palm;

/**
 * Route Cache System
 * 
 * Compiles and caches routes for O(1) lookup performance
 * 
 * Usage:
 * - Automatically enabled in production
 * - Use `palm route:clear` to clear cache
 */
class RouteCache
{
    protected static string $cacheDir = '';
    protected static string $cacheFile = 'routes.php';
    protected static bool $enabled = true;
    protected static ?array $cachedRoutes = null;

    /**
     * Initialize route cache
     */
    public static function init(string $baseDir): void
    {
        // Set cache directory - use project root
        $projectRoot = defined('PALM_ROOT') ? PALM_ROOT : dirname($baseDir);
        $cacheDir = $projectRoot . '/storage/cache/routes';
        
        // Create directory if it doesn't exist
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        
        // Also ensure storage/cache exists
        $storageCache = $projectRoot . '/storage/cache';
        if (!is_dir($storageCache)) {
            @mkdir($storageCache, 0755, true);
        }
        
        self::$cacheDir = $cacheDir;
        
        // Check if cache is enabled (disable in development if needed)
        self::$enabled = !self::isDevelopment();
    }

    /**
     * Check if we're in development mode
     */
    protected static function isDevelopment(): bool
    {
        // Check environment variable or config
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
        return strtolower($env) === 'development' || strtolower($env) === 'dev';
    }

    /**
     * Get cache file path
     */
    protected static function getCacheFilePath(): string
    {
        return self::$cacheDir . '/' . self::$cacheFile;
    }

    /**
     * Check if cache exists and is valid
     */
    public static function exists(): bool
    {
        if (!self::$enabled) {
            return false;
        }
        
        $cacheFile = self::getCacheFilePath();
        if (!file_exists($cacheFile)) {
            return false;
        }

        // Check if routes file is newer than cache
        $routesFile = defined('PALM_ROOT') ? PALM_ROOT . '/src/routes/main.php' : __DIR__ . '/../../src/routes/main.php';
        if (file_exists($routesFile) && filemtime($routesFile) > filemtime($cacheFile)) {
            return false; // Routes file changed, cache invalid
        }

        return true;
    }

    /**
     * Load cached routes
     */
    public static function load(): ?array
    {
        if (!self::$enabled || !self::exists()) {
            return null;
        }

        if (self::$cachedRoutes !== null) {
            return self::$cachedRoutes;
        }

        $cacheFile = self::getCacheFilePath();
        if (file_exists($cacheFile)) {
            try {
                self::$cachedRoutes = require $cacheFile;
                // Validate loaded routes structure
                if (is_array(self::$cachedRoutes) && 
                    (isset(self::$cachedRoutes['GET']) || isset(self::$cachedRoutes['POST']))) {
                    return self::$cachedRoutes;
                }
            } catch (\Throwable $e) {
                // Cache file is corrupted, delete it
                error_log("Palm: Invalid route cache file, deleting: " . $e->getMessage());
                @unlink($cacheFile);
                self::$cachedRoutes = null;
            }
        }

        return null;
    }

    /**
     * Save routes to cache
     * Note: Closures cannot be cached, so routes with closures are excluded
     */
    public static function save(array $routes): bool
    {
        if (!self::$enabled) {
            return false;
        }

        $cacheFile = self::getCacheFilePath();
        
        // Filter out routes with closures (cannot be serialized)
        $cacheableRoutes = self::filterCacheableRoutes($routes);
        
        // If no cacheable routes, don't create cache file
        if (empty($cacheableRoutes['GET']) && empty($cacheableRoutes['POST'])) {
            // Remove cache file if exists
            if (file_exists($cacheFile)) {
                @unlink($cacheFile);
            }
            return false;
        }
        
        // Prepare cache data
        $cacheData = "<?php\n\n";
        $cacheData .= "// Auto-generated route cache\n";
        $cacheData .= "// Generated: " . date('Y-m-d H:i:s') . "\n";
        $cacheData .= "// DO NOT EDIT MANUALLY\n";
        $cacheData .= "// Note: Routes with closures are not cached\n\n";
        $cacheData .= "return " . var_export($cacheableRoutes, true) . ";\n";

        // Write to file atomically
        $tempFile = $cacheFile . '.tmp';
        if (file_put_contents($tempFile, $cacheData, LOCK_EX) !== false) {
            return rename($tempFile, $cacheFile);
        }

        return false;
    }

    /**
     * Filter out routes with closures (non-cacheable)
     */
    protected static function filterCacheableRoutes(array $routes): array
    {
        $cacheable = [
            'GET' => [],
            'POST' => [],
        ];

        foreach (['GET', 'POST'] as $method) {
            if (!isset($routes[$method])) {
                continue;
            }

            foreach ($routes[$method] as $path => $route) {
                // Check if route is cacheable
                if (self::isRouteCacheable($route)) {
                    $cacheable[$method][$path] = $route;
                }
            }
        }

        return $cacheable;
    }

    /**
     * Check if a route can be cached
     */
    protected static function isRouteCacheable($route): bool
    {
        // Direct callable (closure) - not cacheable
        if (is_callable($route) && $route instanceof \Closure) {
            return false;
        }

        // Array with handler - check if handler is closure
        if (is_array($route) && isset($route['handler'])) {
            $handler = $route['handler'];
            if (is_callable($handler) && $handler instanceof \Closure) {
                return false;
            }
            // ViewHandler instances are cacheable
            return true;
        }

        // ViewHandler instances are cacheable
        if (is_object($route) && method_exists($route, 'getSlug')) {
            return true;
        }

        // String or non-closure callable is cacheable
        if (!is_callable($route) || !($route instanceof \Closure)) {
            return true;
        }

        return false;
    }

    /**
     * Clear route cache
     */
    public static function clear(): bool
    {
        $cacheFile = self::getCacheFilePath();
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        return true;
    }

    /**
     * Get cache file path (for CLI commands)
     */
    public static function getCachePath(): string
    {
        return self::getCacheFilePath();
    }

    /**
     * Check if caching is enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
}


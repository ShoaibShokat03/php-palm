<?php

namespace Frontend\Palm;

/**
 * Route Cache System
 *
 * Compiles and caches routes for O(1) lookup performance
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
        $projectRoot = defined('PALM_ROOT')
            ? PALM_ROOT
            : dirname($baseDir);

        // 🔥 STEP-BY-STEP directory creation (IMPORTANT)
        $storageDir      = $projectRoot . '/storage';
        $cacheBaseDir    = $storageDir . '/cache';
        $routesCacheDir  = $cacheBaseDir . '/routes';

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        if (!is_dir($cacheBaseDir)) {
            mkdir($cacheBaseDir, 0755, true);
        }

        if (!is_dir($routesCacheDir)) {
            mkdir($routesCacheDir, 0755, true);
        }

        self::$cacheDir = $routesCacheDir;

        // Enable cache only in production
        self::$enabled = !self::isDevelopment();
    }

    /**
     * Detect development environment
     */
    protected static function isDevelopment(): bool
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
        return in_array(strtolower($env), ['dev', 'development'], true);
    }

    /**
     * Get full cache file path
     */
    protected static function getCacheFilePath(): string
    {
        return self::$cacheDir . '/' . self::$cacheFile;
    }

    /**
     * Check if valid cache exists
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

        $routesFile = defined('PALM_ROOT')
            ? PALM_ROOT . '/src/routes/web.php'
            : __DIR__ . '/../../src/routes/web.php';

        if (file_exists($routesFile) && filemtime($routesFile) > filemtime($cacheFile)) {
            return false;
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

        try {
            $routes = require $cacheFile;

            if (is_array($routes)) {
                self::$cachedRoutes = $routes;
                return $routes;
            }
        } catch (\Throwable $e) {
            error_log('Palm RouteCache load error: ' . $e->getMessage());
            @unlink($cacheFile);
        }

        return null;
    }

    /**
     * Save routes to cache
     */
    public static function save(array $routes): bool
    {
        if (!self::$enabled) {
            return false;
        }

        // 😤 ENSURE DIRECTORY EXISTS (NO DRAMA)
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }

        $cacheFile = self::getCacheFilePath();
        $cacheableRoutes = self::filterCacheableRoutes($routes);

        if (
            empty($cacheableRoutes['GET']) &&
            empty($cacheableRoutes['POST'])
        ) {
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
            return false;
        }

        $cacheData  = "<?php\n\n";
        $cacheData .= "// Auto-generated Palm route cache\n";
        $cacheData .= "// Generated at: " . date('Y-m-d H:i:s') . "\n";
        $cacheData .= "// DO NOT EDIT MANUALLY\n\n";
        $cacheData .= "return " . var_export($cacheableRoutes, true) . ";\n";

        $tempFile = $cacheFile . '.tmp';

        if (file_put_contents($tempFile, $cacheData, LOCK_EX) === false) {
            return false;
        }

        return rename($tempFile, $cacheFile);
    }

    /**
     * Filter cacheable routes only
     */
    protected static function filterCacheableRoutes(array $routes): array
    {
        $result = [
            'GET'  => [],
            'POST' => [],
        ];

        foreach (['GET', 'POST'] as $method) {
            if (!isset($routes[$method])) {
                continue;
            }

            foreach ($routes[$method] as $path => $route) {
                if (self::isRouteCacheable($route)) {
                    $result[$method][$path] = $route;
                }
            }
        }

        return $result;
    }

    /**
     * Check if a route can be cached
     */
    protected static function isRouteCacheable($route): bool
    {
        if ($route instanceof \Closure) {
            return false;
        }

        if (is_array($route) && isset($route['handler'])) {
            return !($route['handler'] instanceof \Closure);
        }

        return true;
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
     * For CLI usage
     */
    public static function getCachePath(): string
    {
        return self::getCacheFilePath();
    }

    /**
     * Is caching enabled?
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
}

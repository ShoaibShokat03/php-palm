<?php

namespace App\Core;

use PhpPalm\Core\Route;
use Dotenv\Dotenv;
use App\Core\ModuleLoader;
use App\Core\MiddlewareLoader;
use App\Core\RouteConflictChecker;

/**
 * Application Bootstrap Cache
 * 
 * Caches the application state (routes, modules, middlewares) to avoid
 * reloading on every request. Only rebuilds when cache is invalidated.
 */
class ApplicationBootstrap
{
    private static ?array $cachedState = null;
    private static string $cacheFile;
    private static string $cacheDir;
    private static bool $cacheEnabled = true;
    
    /**
     * Initialize bootstrap cache
     */
    public static function init(string $cacheDir = null): void
    {
        if ($cacheDir === null) {
            $cacheDir = dirname(__DIR__, 2) . '/storage/cache';
        }
        
        self::$cacheDir = $cacheDir;
        self::$cacheFile = $cacheDir . '/bootstrap.cache.php';
        
        // Create cache directory if it doesn't exist
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        
        // Check if cache is enabled via environment
        $cacheEnabled = $_ENV['APP_CACHE_ENABLED'] ?? 'true';
        self::$cacheEnabled = filter_var($cacheEnabled, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Load or build application state
     */
    public static function load(): array
    {
        if (self::$cachedState !== null) {
            return self::$cachedState;
        }
        
        // Always build fresh state (routes can't be cached due to callables)
        // But we can use cache to skip conflict checking if cache is valid
        $useCachedConflicts = false;
        $cachedConflictData = null;
        
        if (self::$cacheEnabled && self::isCacheValid()) {
            $cachedConflictData = self::loadFromCache();
            if ($cachedConflictData !== null && !($cachedConflictData['has_conflicts'] ?? false)) {
                $useCachedConflicts = true;
            }
        }
        
        // Build fresh state (always load routes)
        self::$cachedState = self::buildState($useCachedConflicts, $cachedConflictData);
        
        // Save conflict data to cache (routes can't be serialized)
        if (self::$cacheEnabled) {
            self::saveToCache(self::$cachedState);
        }
        
        return self::$cachedState;
    }
    
    /**
     * Build application state (routes, modules, middlewares)
     */
    private static function buildState(bool $useCachedConflicts = false, ?array $cachedData = null): array
    {
        // Initialize router
        Route::init();
        
        // Load environment variables (only if not already loaded)
        if (!isset($_ENV['APP_ENV'])) {
            $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2) . '/config/');
            $dotenv->load();
        }
        
        // Load middlewares
        $middlewareLoader = new MiddlewareLoader();
        try {
            $middlewareLoader->loadMiddlewares();
        } catch (\Throwable $e) {
            error_log('Middleware loading error: ' . $e->getMessage());
        }
        
        // Load routes from api.php
        $routesFile = dirname(__DIR__, 2) . '/routes/api.php';
        if (file_exists($routesFile)) {
            Route::setSource('api.php');
            require $routesFile;
        }
        
        // Load modules
        $moduleLoader = new ModuleLoader();
        try {
            $moduleLoader->loadModules();
        } catch (\Throwable $e) {
            error_log('Module loading error: ' . $e->getMessage());
        }
        
        // Check for route conflicts (skip if using cached result)
        $router = Route::getRouter();
        $hasConflicts = false;
        $conflicts = [];
        
        if ($useCachedConflicts && $cachedData !== null) {
            // Use cached conflict data
            $hasConflicts = $cachedData['has_conflicts'] ?? false;
            $conflicts = $cachedData['conflicts'] ?? [];
        } elseif ($router !== null) {
            // Perform conflict check
            try {
                $conflictChecker = new RouteConflictChecker($router);
                $conflicts = $conflictChecker->checkConflicts();
                $hasConflicts = $conflictChecker->hasConflicts();
                
                if ($hasConflicts) {
                    error_log("ROUTE CONFLICTS DETECTED:\n" . $conflictChecker->getConflictReport());
                }
            } catch (\Throwable $e) {
                error_log('Route conflict check error: ' . $e->getMessage());
            }
        }
        
        // Get all routes (for reference, but not cached)
        $routes = $router !== null ? $router->getRoutes() : [];
        
        return [
            'routes' => $routes,
            'has_conflicts' => $hasConflicts,
            'conflicts' => $conflicts,
            'built_at' => time(),
            'router' => $router
        ];
    }
    
    /**
     * Check if cache is valid
     */
    private static function isCacheValid(): bool
    {
        if (!file_exists(self::$cacheFile)) {
            return false;
        }
        
        // Check cache age (default: 1 hour, configurable)
        $cacheLifetime = (int)($_ENV['APP_CACHE_LIFETIME'] ?? 3600);
        $cacheAge = time() - filemtime(self::$cacheFile);
        
        if ($cacheAge > $cacheLifetime) {
            return false;
        }
        
        // Check if source files have changed
        return self::checkSourceFilesUnchanged();
    }
    
    /**
     * Check if source files have changed since cache was created
     */
    private static function checkSourceFilesUnchanged(): bool
    {
        $cacheTime = filemtime(self::$cacheFile);
        $baseDir = dirname(__DIR__, 2);
        
        // Check key files that would invalidate cache
        $filesToCheck = [
            $baseDir . '/routes/api.php',
            $baseDir . '/modules',
            $baseDir . '/app/Core/ModuleLoader.php',
            $baseDir . '/app/Core/MiddlewareLoader.php',
        ];
        
        foreach ($filesToCheck as $file) {
            if (is_file($file) && filemtime($file) > $cacheTime) {
                return false;
            }
            if (is_dir($file)) {
                // Check directory modification time
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($file, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $item) {
                    if ($item->isFile() && $item->getMTime() > $cacheTime) {
                        return false;
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * Load state from cache
     */
    private static function loadFromCache(): ?array
    {
        if (!file_exists(self::$cacheFile)) {
            return null;
        }
        
        try {
            $data = include self::$cacheFile;
            
            // Note: Routes are not restored from cache because handlers (callables/arrays)
            // cannot be serialized. Instead, we'll rebuild routes but use cached conflict check.
            // The actual route loading happens in buildState() but we skip it if cache is valid.
            // For now, we return null to force rebuild, but keep conflict data.
            
            // Actually, we need to rebuild routes because callables can't be cached
            // But we can cache the fact that there are no conflicts
            return $data;
        } catch (\Throwable $e) {
            error_log('Cache load error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Save state to cache
     */
    private static function saveToCache(array $state): void
    {
        try {
            // Only cache conflict data and metadata (routes can't be serialized due to callables)
            $cacheData = [
                'has_conflicts' => $state['has_conflicts'],
                'conflicts' => $state['conflicts'],
                'built_at' => $state['built_at'],
                'route_count' => count($state['routes'] ?? [])
            ];
            
            $content = "<?php\nreturn " . var_export($cacheData, true) . ";\n";
            file_put_contents(self::$cacheFile, $content, LOCK_EX);
        } catch (\Throwable $e) {
            error_log('Cache save error: ' . $e->getMessage());
        }
    }
    
    /**
     * Clear cache
     */
    public static function clearCache(): void
    {
        if (file_exists(self::$cacheFile)) {
            @unlink(self::$cacheFile);
        }
        self::$cachedState = null;
    }
    
    /**
     * Get cached state
     */
    public static function getState(): ?array
    {
        return self::$cachedState;
    }
    
    /**
     * Check if cache is enabled
     */
    public static function isCacheEnabled(): bool
    {
        return self::$cacheEnabled;
    }
}


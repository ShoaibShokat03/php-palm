<?php

namespace App\Core;

use App\Core\Cache\Cache;

/**
 * Configuration Management
 * 
 * Features:
 * - Environment-based configuration
 * - Config caching
 * - Type-safe config access
 */
class Config
{
    protected static array $config = [];
    protected static bool $loaded = false;
    protected static string $configPath;

    /**
     * Load configuration
     */
    public static function load(string $configPath = null): void
    {
        if (self::$loaded) {
            return;
        }

        self::$configPath = $configPath ?? __DIR__ . '/../../config';
        
        // Try to load from cache first
        $cacheKey = 'php_palm_config';
        $cached = Cache::getStatic($cacheKey);
        
        if ($cached !== null && !self::isDevelopment()) {
            self::$config = $cached;
            self::$loaded = true;
            return;
        }

        // Load config files
        self::$config = self::loadConfigFiles();
        
        // Cache config (except in development)
        if (!self::isDevelopment()) {
            Cache::setStatic($cacheKey, self::$config, 3600);
        }

        self::$loaded = true;
    }

    /**
     * Load all config files
     */
    protected static function loadConfigFiles(): array
    {
        $config = [];
        
        if (!is_dir(self::$configPath)) {
            return $config;
        }

        $files = glob(self::$configPath . '/*.php');
        
        foreach ($files as $file) {
            $key = basename($file, '.php');
            // Skip .env and other non-config files
            if ($key === '.env' || $key === 'cors') {
                continue;
            }
            $config[$key] = require $file;
        }

        // Apply environment overrides
        $env = $_ENV['APP_ENV'] ?? 'production';
        $envConfigFile = self::$configPath . '/' . $env . '.php';
        
        if (file_exists($envConfigFile)) {
            $envConfig = require $envConfigFile;
            $config = array_merge_recursive($config, $envConfig);
        }

        return $config;
    }

    /**
     * Get config value
     */
    public static function get(string $key, $default = null)
    {
        self::load();
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }

    /**
     * Set config value (runtime)
     */
    public static function set(string $key, $value): void
    {
        self::load();
        
        $keys = explode('.', $key);
        $config = &self::$config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }

    /**
     * Check if config key exists
     */
    public static function has(string $key): bool
    {
        self::load();
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }
        
        return true;
    }

    /**
     * Get all config
     */
    public static function all(): array
    {
        self::load();
        return self::$config;
    }

    /**
     * Clear config cache
     */
    public static function clearCache(): void
    {
        Cache::deleteStatic('php_palm_config');
        self::$loaded = false;
        self::$config = [];
    }

    /**
     * Check if in development mode
     */
    protected static function isDevelopment(): bool
    {
        $env = $_ENV['APP_ENV'] ?? 'production';
        return strtolower($env) === 'development' || strtolower($env) === 'dev';
    }
}


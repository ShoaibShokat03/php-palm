<?php

namespace App\Core\Security;

use PhpPalm\Core\Request;

/**
 * Enhanced Rate Limiter
 * 
 * Provides rate limiting with different limits for different endpoints
 * 
 * Features:
 * - Per-endpoint rate limiting
 * - Per-IP rate limiting
 * - Per-user rate limiting
 * - Login attempt rate limiting
 */
class RateLimiter
{
    protected static string $storageDir = '';
    protected static array $limits = [
        'default' => ['limit' => 100, 'window' => 60], // 100 requests per minute
        'login' => ['limit' => 5, 'window' => 300], // 5 attempts per 5 minutes
        'api' => ['limit' => 1000, 'window' => 3600], // 1000 requests per hour
    ];

    /**
     * Get limit for a type
     */
    public static function getLimit(string $type = 'default'): int
    {
        $config = self::$limits[$type] ?? self::$limits['default'];
        return $config['limit'];
    }

    /**
     * Initialize rate limiter
     */
    public static function init(string $storageDir = null): void
    {
        self::$storageDir = $storageDir ?? (__DIR__ . '/../../../storage/ratelimit');
        
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0755, true);
        }
    }

    /**
     * Check rate limit
     * 
     * @param string $key Unique identifier (IP, user ID, endpoint, etc.)
     * @param string $type Rate limit type (default, login, api)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public static function check(string $key, string $type = 'default'): array
    {
        self::init();
        
        $config = self::$limits[$type] ?? self::$limits['default'];
        $limit = $config['limit'];
        $window = $config['window'];
        
        $safeKey = preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $key);
        $file = self::$storageDir . '/' . $type . '_' . $safeKey . '.json';
        $now = time();
        
        if (!file_exists($file)) {
            $data = ['count' => 1, 'start' => $now, 'reset' => $now + $window];
            file_put_contents($file, json_encode($data), LOCK_EX);
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'reset' => $now + $window
            ];
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        // Check if window expired
        if ($now >= ($data['reset'] ?? $data['start'] + $window)) {
            $data = ['count' => 1, 'start' => $now, 'reset' => $now + $window];
            file_put_contents($file, json_encode($data), LOCK_EX);
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'reset' => $now + $window
            ];
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $limit) {
            SecurityLogger::logRateLimitViolation($key, $limit, $data['count']);
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $data['reset'] ?? $data['start'] + $window
            ];
        }
        
        // Increment count
        $data['count']++;
        file_put_contents($file, json_encode($data), LOCK_EX);
        
        return [
            'allowed' => true,
            'remaining' => $limit - $data['count'],
            'reset' => $data['reset'] ?? $data['start'] + $window
        ];
    }

    /**
     * Check rate limit for IP address
     */
    public static function checkIp(string $type = 'default'): array
    {
        $ip = Request::ip() ?? 'unknown';
        return self::check($ip, $type);
    }

    /**
     * Check rate limit for login attempts
     */
    public static function checkLogin(string $identifier): array
    {
        return self::check('login_' . $identifier, 'login');
    }

    /**
     * Reset rate limit for a key
     */
    public static function reset(string $key, string $type = 'default'): void
    {
        self::init();
        
        $safeKey = preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $key);
        $file = self::$storageDir . '/' . $type . '_' . $safeKey . '.json';
        
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Set custom rate limit
     */
    public static function setLimit(string $type, int $limit, int $window): void
    {
        self::$limits[$type] = ['limit' => $limit, 'window' => $window];
    }
}


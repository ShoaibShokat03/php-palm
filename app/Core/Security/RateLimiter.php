<?php

namespace App\Core\Security;

use PhpPalm\Core\Request;

/**
 * Enhanced Rate Limiter with Sliding Window
 * 
 * Features:
 * - Sliding window algorithm (more accurate than fixed windows)
 * - Per-endpoint rate limiting
 * - Per-IP rate limiting
 * - Per-user rate limiting
 * - Graduated penalties (exponential backoff for repeat offenders)
 * - API quota management (daily/monthly limits)
 * - Login attempt rate limiting with lockout
 * 
 * @package PhpPalm\Security
 */
class RateLimiter
{
    protected static string $storageDir = '';

    /**
     * Rate limit configurations
     */
    protected static array $limits = [
        'default' => ['limit' => 100, 'window' => 60],      // 100 requests per minute
        'login' => ['limit' => 5, 'window' => 300],         // 5 attempts per 5 minutes
        'api' => ['limit' => 1000, 'window' => 3600],       // 1000 requests per hour
        'strict' => ['limit' => 10, 'window' => 60],        // 10 requests per minute
        'upload' => ['limit' => 20, 'window' => 3600],      // 20 uploads per hour
    ];

    /**
     * Penalty multipliers for repeat offenders
     */
    protected static array $penalties = [];

    /**
     * Maximum penalty multiplier
     */
    protected static int $maxPenaltyMultiplier = 16;

    /**
     * Get limit for a type
     */
    public static function getLimit(string $type = 'default'): int
    {
        $config = self::$limits[$type] ?? self::$limits['default'];
        return $config['limit'];
    }

    /**
     * Get window for a type
     */
    public static function getWindow(string $type = 'default'): int
    {
        $config = self::$limits[$type] ?? self::$limits['default'];
        return $config['window'];
    }

    /**
     * Initialize rate limiter
     */
    public static function init(string $storageDir = null): void
    {
        self::$storageDir = $storageDir ?? (__DIR__ . '/../../../storage/ratelimit');

        if (!is_dir(self::$storageDir)) {
            @mkdir(self::$storageDir, 0755, true);
        }
    }

    /**
     * Check rate limit using sliding window algorithm
     * 
     * Sliding window provides more accurate rate limiting than fixed windows
     * by considering requests from the previous window proportionally.
     * 
     * @param string $key Unique identifier (IP, user ID, endpoint, etc.)
     * @param string $type Rate limit type (default, login, api)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int, 'retry_after' => int]
     */
    public static function check(string $key, string $type = 'default'): array
    {
        self::init();

        $config = self::$limits[$type] ?? self::$limits['default'];
        $baseLimit = $config['limit'];
        $window = $config['window'];

        // Apply penalty multiplier for repeat offenders
        $penaltyKey = $type . '_' . $key;
        $penalty = self::$penalties[$penaltyKey] ?? 0;
        $limit = max(1, (int)($baseLimit / max(1, $penalty)));

        $safeKey = self::sanitizeKey($key);
        $file = self::$storageDir . '/' . $type . '_' . $safeKey . '.json';
        $now = time();
        $nowMs = microtime(true);

        // Initialize or read existing data
        $data = self::readData($file);

        if ($data === null) {
            $data = [
                'requests' => [['time' => $nowMs]],
                'window_start' => $now,
                'violations' => 0,
            ];
            self::writeData($file, $data);
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'reset' => $now + $window,
                'retry_after' => 0,
            ];
        }

        // Sliding window calculation
        $windowStart = $now - $window;
        $prevWindowStart = $windowStart - $window;

        // Filter requests in current and previous windows
        $currentWindowRequests = [];
        $prevWindowRequests = [];

        foreach ($data['requests'] ?? [] as $request) {
            $requestTime = $request['time'];
            if ($requestTime >= $windowStart) {
                $currentWindowRequests[] = $request;
            } elseif ($requestTime >= $prevWindowStart) {
                $prevWindowRequests[] = $request;
            }
        }

        // Calculate weighted count using sliding window
        $currentCount = count($currentWindowRequests);
        $prevCount = count($prevWindowRequests);

        // Weight of previous window (percentage of current window that overlaps)
        $windowPosition = ($now - $windowStart) / $window;
        $prevWeight = 1 - $windowPosition;

        $weightedCount = $currentCount + ($prevCount * $prevWeight);

        // Check if limit exceeded
        if ($weightedCount >= $limit) {
            // Increment violation count for graduated penalties
            $data['violations'] = ($data['violations'] ?? 0) + 1;
            self::writeData($file, $data);

            // Apply graduated penalty
            if ($data['violations'] >= 3) {
                self::applyPenalty($penaltyKey);
            }

            // Log violation
            if (class_exists('App\Core\Security\SecurityLogger')) {
                SecurityLogger::logRateLimitViolation($key, $limit, (int)$weightedCount);
            }

            $retryAfter = (int)(($limit - $currentCount) > 0 ? 1 : $window - ($now - $windowStart));

            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $now + $window,
                'retry_after' => max(1, $retryAfter),
            ];
        }

        // Add current request
        $currentWindowRequests[] = ['time' => $nowMs];

        // Reset violations on successful request
        $data['violations'] = 0;
        $data['requests'] = array_merge($prevWindowRequests, $currentWindowRequests);
        self::writeData($file, $data);

        return [
            'allowed' => true,
            'remaining' => max(0, $limit - (int)$weightedCount - 1),
            'reset' => $now + $window,
            'retry_after' => 0,
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
     * Check rate limit for user ID
     */
    public static function checkUser(int|string $userId, string $type = 'default'): array
    {
        return self::check('user_' . $userId, $type);
    }

    /**
     * Check rate limit for login attempts with lockout
     */
    public static function checkLogin(string $identifier): array
    {
        $result = self::check('login_' . $identifier, 'login');

        // Add lockout information
        if (!$result['allowed']) {
            $result['locked_until'] = $result['reset'];
            $result['message'] = 'Too many login attempts. Please try again later.';
        }

        return $result;
    }

    /**
     * Check combined IP + User rate limit (stricter)
     */
    public static function checkIpAndUser(int|string $userId, string $type = 'default'): array
    {
        $ipResult = self::checkIp($type);
        $userResult = self::checkUser($userId, $type);

        // Return the most restrictive result
        if (!$ipResult['allowed'] || !$userResult['allowed']) {
            return $ipResult['allowed'] ? $userResult : $ipResult;
        }

        return [
            'allowed' => true,
            'remaining' => min($ipResult['remaining'], $userResult['remaining']),
            'reset' => max($ipResult['reset'], $userResult['reset']),
            'retry_after' => 0,
        ];
    }

    /**
     * Check API quota (daily/monthly limits)
     */
    public static function checkQuota(string $key, int $limit, string $period = 'daily'): array
    {
        self::init();

        $periodSeconds = match ($period) {
            'hourly' => 3600,
            'daily' => 86400,
            'weekly' => 604800,
            'monthly' => 2592000, // 30 days
            default => 86400,
        };

        $safeKey = self::sanitizeKey($key);
        $file = self::$storageDir . '/quota_' . $period . '_' . $safeKey . '.json';
        $now = time();

        $data = self::readData($file);

        // Check if period expired
        if ($data === null || $now >= ($data['reset'] ?? 0)) {
            $data = [
                'count' => 1,
                'start' => $now,
                'reset' => $now + $periodSeconds,
            ];
            self::writeData($file, $data);
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'reset' => $data['reset'],
                'used' => 1,
                'limit' => $limit,
            ];
        }

        // Check if quota exceeded
        if ($data['count'] >= $limit) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $data['reset'],
                'used' => $data['count'],
                'limit' => $limit,
            ];
        }

        // Increment count
        $data['count']++;
        self::writeData($file, $data);

        return [
            'allowed' => true,
            'remaining' => $limit - $data['count'],
            'reset' => $data['reset'],
            'used' => $data['count'],
            'limit' => $limit,
        ];
    }

    /**
     * Apply graduated penalty for repeat offenders
     */
    protected static function applyPenalty(string $key): void
    {
        $current = self::$penalties[$key] ?? 1;
        self::$penalties[$key] = min($current * 2, self::$maxPenaltyMultiplier);
    }

    /**
     * Reset penalty for a key
     */
    public static function resetPenalty(string $key): void
    {
        unset(self::$penalties[$key]);
    }

    /**
     * Reset rate limit for a key
     */
    public static function reset(string $key, string $type = 'default'): void
    {
        self::init();

        $safeKey = self::sanitizeKey($key);
        $file = self::$storageDir . '/' . $type . '_' . $safeKey . '.json';

        if (file_exists($file)) {
            @unlink($file);
        }

        self::resetPenalty($type . '_' . $key);
    }

    /**
     * Set custom rate limit
     */
    public static function setLimit(string $type, int $limit, int $window): void
    {
        self::$limits[$type] = ['limit' => $limit, 'window' => $window];
    }

    /**
     * Read data from file
     */
    protected static function readData(string $file): ?array
    {
        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Write data to file atomically
     */
    protected static function writeData(string $file, array $data): bool
    {
        $tempFile = $file . '.tmp.' . getmypid();
        $result = @file_put_contents($tempFile, json_encode($data), LOCK_EX);

        if ($result === false) {
            return false;
        }

        return @rename($tempFile, $file);
    }

    /**
     * Sanitize key for use in filename
     */
    protected static function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $key);
    }

    /**
     * Clean up old rate limit files
     */
    public static function cleanup(int $maxAge = 86400): int
    {
        self::init();

        $deleted = 0;
        $files = glob(self::$storageDir . '/*.json');
        $now = time();

        foreach ($files as $file) {
            if (($now - filemtime($file)) > $maxAge) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}

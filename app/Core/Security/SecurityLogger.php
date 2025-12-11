<?php

namespace App\Core\Security;

use PhpPalm\Core\Request;

/**
 * Security Logging & Monitoring
 * 
 * Logs security-related events for monitoring and alerting
 * 
 * Events logged:
 * - Failed login attempts
 * - CSRF token failures
 * - Suspicious IPs
 * - Rate limit violations
 * - Authentication failures
 * - Authorization failures
 */
class SecurityLogger
{
    protected static string $logDir = '';
    protected static array $alertThresholds = [
        'failed_logins' => 5, // Alert after 5 failed logins
        'csrf_failures' => 10, // Alert after 10 CSRF failures
        'rate_limit_violations' => 20 // Alert after 20 violations
    ];

    /**
     * Initialize logger
     */
    public static function init(string $logDir = null): void
    {
        self::$logDir = $logDir ?? (__DIR__ . '/../../../storage/logs/security');
        
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }

    /**
     * Log failed login attempt
     */
    public static function logFailedLogin(string $username, string $reason = 'Invalid credentials'): void
    {
        self::init();
        
        $data = [
            'event' => 'failed_login',
            'username' => $username,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'reason' => $reason,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::writeLog('failed_logins', $data);
        self::checkAlert('failed_logins', $username);
    }

    /**
     * Log CSRF token failure
     */
    public static function logCsrfFailure(string $route = null): void
    {
        self::init();
        
        $data = [
            'event' => 'csrf_failure',
            'route' => $route ?? Request::path(),
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'method' => Request::getMethod(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::writeLog('csrf_failures', $data);
        self::checkAlert('csrf_failures', Request::ip());
    }

    /**
     * Log rate limit violation
     */
    public static function logRateLimitViolation(string $endpoint, int $limit, int $count): void
    {
        self::init();
        
        $data = [
            'event' => 'rate_limit_violation',
            'endpoint' => $endpoint,
            'ip' => Request::ip(),
            'limit' => $limit,
            'count' => $count,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::writeLog('rate_limit_violations', $data);
        self::checkAlert('rate_limit_violations', Request::ip());
    }

    /**
     * Log authentication failure
     */
    public static function logAuthFailure(string $reason, array $context = []): void
    {
        self::init();
        
        $data = array_merge([
            'event' => 'auth_failure',
            'reason' => $reason,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'timestamp' => date('Y-m-d H:i:s')
        ], $context);
        
        self::writeLog('auth_failures', $data);
    }

    /**
     * Log authorization failure
     */
    public static function logAuthorizationFailure(string $user, string $resource, string $action): void
    {
        self::init();
        
        $data = [
            'event' => 'authorization_failure',
            'user' => $user,
            'resource' => $resource,
            'action' => $action,
            'ip' => Request::ip(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::writeLog('authorization_failures', $data);
    }

    /**
     * Log suspicious activity
     */
    public static function logSuspiciousActivity(string $activity, array $context = []): void
    {
        self::init();
        
        $data = array_merge([
            'event' => 'suspicious_activity',
            'activity' => $activity,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'timestamp' => date('Y-m-d H:i:s')
        ], $context);
        
        self::writeLog('suspicious_activity', $data);
    }

    /**
     * Write log entry
     */
    protected static function writeLog(string $type, array $data): void
    {
        $logFile = self::$logDir . '/' . $type . '_' . date('Y-m-d') . '.log';
        $logEntry = json_encode($data) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Check if alert threshold is reached
     */
    protected static function checkAlert(string $type, string $identifier): void
    {
        if (!isset(self::$alertThresholds[$type])) {
            return;
        }
        
        $threshold = self::$alertThresholds[$type];
        $count = self::getEventCount($type, $identifier);
        
        if ($count >= $threshold) {
            self::triggerAlert($type, $identifier, $count);
        }
    }

    /**
     * Get event count for identifier in last hour
     */
    protected static function getEventCount(string $type, string $identifier): int
    {
        $logFile = self::$logDir . '/' . $type . '_' . date('Y-m-d') . '.log';
        
        if (!file_exists($logFile)) {
            return 0;
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $count = 0;
        $oneHourAgo = time() - 3600;
        
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (!$data) continue;
            
            // Check if entry matches identifier and is within last hour
            $timestamp = strtotime($data['timestamp'] ?? '');
            if ($timestamp >= $oneHourAgo) {
                // Check if identifier matches (username, IP, etc.)
                $match = false;
                foreach ($data as $key => $value) {
                    if (is_string($value) && $value === $identifier) {
                        $match = true;
                        break;
                    }
                }
                
                if ($match) {
                    $count++;
                }
            }
        }
        
        return $count;
    }

    /**
     * Trigger security alert
     */
    protected static function triggerAlert(string $type, string $identifier, int $count): void
    {
        $alertFile = self::$logDir . '/alerts_' . date('Y-m-d') . '.log';
        $alert = [
            'type' => $type,
            'identifier' => $identifier,
            'count' => $count,
            'threshold' => self::$alertThresholds[$type],
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => Request::ip()
        ];
        
        file_put_contents($alertFile, json_encode($alert) . "\n", FILE_APPEND | LOCK_EX);
        
        // In production, you might want to send email/SMS notification here
        // or integrate with SIEM systems
    }

    /**
     * Get security logs
     */
    public static function getLogs(string $type, int $limit = 100): array
    {
        self::init();
        
        $logFile = self::$logDir . '/' . $type . '_' . date('Y-m-d') . '.log';
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];
        
        foreach (array_reverse($lines) as $line) {
            $data = json_decode($line, true);
            if ($data) {
                $logs[] = $data;
                if (count($logs) >= $limit) {
                    break;
                }
            }
        }
        
        return $logs;
    }
}


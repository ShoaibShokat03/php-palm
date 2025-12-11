<?php

namespace App\Core\Security;

/**
 * Secure Session Management
 * 
 * Provides secure session configuration and management
 * 
 * Features:
 * - HttpOnly cookies
 * - Secure cookies (HTTPS only)
 * - SameSite attribute
 * - Session ID regeneration
 * - Session expiration
 * - Session fingerprinting
 */
class Session
{
    protected static bool $initialized = false;
    protected static int $lifetime = 3600; // 1 hour
    protected static int $idleTimeout = 1800; // 30 minutes

    /**
     * Initialize secure session
     */
    public static function start(): void
    {
        if (self::$initialized) {
            return;
        }

        // Configure session settings before starting
        self::configure();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Validate and regenerate if needed
        self::validate();
        
        self::$initialized = true;
    }

    /**
     * Configure secure session settings
     */
    protected static function configure(): void
    {
        // Cookie settings
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', self::isHttps() ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        
        // Security settings
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        
        // Session lifetime
        ini_set('session.gc_maxlifetime', (string)self::$lifetime);
        
        // Set cookie parameters
        $cookieParams = [
            'lifetime' => self::$lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        
        session_set_cookie_params($cookieParams);
    }

    /**
     * Check if request is over HTTPS
     */
    protected static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    }

    /**
     * Validate session (check expiration, idle timeout, fingerprint)
     */
    protected static function validate(): void
    {
        if (!isset($_SESSION)) {
            return;
        }

        // Check session expiration
        if (isset($_SESSION['_created_at']) && (time() - $_SESSION['_created_at']) > self::$lifetime) {
            self::destroy();
            return;
        }

        // Check idle timeout
        if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity']) > self::$idleTimeout) {
            self::destroy();
            return;
        }

        // Session fingerprinting (prevent session hijacking)
        $currentFingerprint = self::getFingerprint();
        if (isset($_SESSION['_fingerprint']) && $_SESSION['_fingerprint'] !== $currentFingerprint) {
            self::destroy();
            return;
        }

        // Update last activity
        $_SESSION['_last_activity'] = time();
        
        // Set creation time if not set
        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = time();
        }
        
        // Update fingerprint
        $_SESSION['_fingerprint'] = $currentFingerprint;
    }

    /**
     * Get session fingerprint (IP + User Agent)
     */
    protected static function getFingerprint(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $ip . $userAgent);
    }

    /**
     * Regenerate session ID (call after login or privilege escalation)
     */
    public static function regenerateId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Get session value
     */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value
     */
    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Check if session key exists
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session value
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Destroy session
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            
            if (isset($_COOKIE[session_name()])) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            
            session_destroy();
        }
        
        self::$initialized = false;
    }

    /**
     * Set session lifetime
     */
    public static function setLifetime(int $seconds): void
    {
        self::$lifetime = $seconds;
    }

    /**
     * Set idle timeout
     */
    public static function setIdleTimeout(int $seconds): void
    {
        self::$idleTimeout = $seconds;
    }
}


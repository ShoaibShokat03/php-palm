<?php

namespace App\Core\Security;

use PhpPalm\Core\Request;

/**
 * CSRF Protection
 * 
 * Provides CSRF token generation, validation, and management
 * 
 * Usage:
 * - CSRF::token() - Get current CSRF token
 * - CSRF::validate() - Validate CSRF token from request
 * - CSRF::regenerate() - Regenerate token after successful action
 */
class CSRF
{
    protected static string $tokenName = 'csrf_token';
    protected static ?string $currentToken = null;

    /**
     * Initialize session if not already started
     */
    protected static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure secure session settings
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', self::isHttps() ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            
            session_start();
        }
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
     * Get or generate CSRF token
     */
    public static function token(): string
    {
        self::initSession();
        
        if (self::$currentToken !== null) {
            return self::$currentToken;
        }

        // Check if token exists in session
        if (isset($_SESSION[self::$tokenName])) {
            self::$currentToken = $_SESSION[self::$tokenName];
            return self::$currentToken;
        }

        // Generate new token
        return self::regenerate();
    }

    /**
     * Regenerate CSRF token
     * Should be called after successful POST/PUT/DELETE operations
     */
    public static function regenerate(): string
    {
        self::initSession();
        
        // Generate cryptographically secure random token
        self::$currentToken = bin2hex(random_bytes(32));
        $_SESSION[self::$tokenName] = self::$currentToken;
        
        return self::$currentToken;
    }

    /**
     * Validate CSRF token from request
     * 
     * @param string|null $token Token to validate (if null, reads from request)
     * @return bool True if valid, false otherwise
     */
    public static function validate(?string $token = null): bool
    {
        self::initSession();

        // Get token from parameter or request
        if ($token === null) {
            // Try header first (for API requests)
            $token = Request::csrfToken();
            
            // Fallback to POST data (for form submissions)
            if ($token === null) {
                $token = Request::post(self::$tokenName);
            }
        }

        if (empty($token)) {
            return false;
        }

        // Get stored token from session
        $storedToken = $_SESSION[self::$tokenName] ?? null;
        
        if (empty($storedToken)) {
            return false;
        }

        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($storedToken, $token);
    }

    /**
     * Require valid CSRF token or throw error
     * 
     * @return array Empty array if valid, error array if invalid
     */
    public static function requireValid(): array
    {
        if (!self::validate()) {
            http_response_code(403);
            return [
                'status' => 'error',
                'message' => 'CSRF token validation failed',
                'code' => 'CSRF_INVALID'
            ];
        }
        return [];
    }

    /**
     * Get token name (for form fields)
     */
    public static function tokenName(): string
    {
        return self::$tokenName;
    }

    /**
     * Generate hidden input field HTML
     */
    public static function field(): string
    {
        $token = self::token();
        $name = self::tokenName();
        return '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Get token for JSON/API requests (returns header value)
     */
    public static function header(): string
    {
        return 'X-CSRF-Token: ' . self::token();
    }
}


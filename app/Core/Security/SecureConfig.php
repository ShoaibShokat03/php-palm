<?php

namespace App\Core\Security;

/**
 * Secure Default Configuration Checker
 * 
 * Validates that the framework is configured securely
 */
class SecureConfig
{
    /**
     * Check secure configuration
     * 
     * @return array ['secure' => bool, 'issues' => array]
     */
    public static function check(): array
    {
        $issues = [];
        
        // Check debug mode
        $debugMode = $_ENV['DEBUG_MODE'] ?? 'false';
        if (strtolower($debugMode) === 'true' || strtolower($debugMode) === '1') {
            $issues[] = 'DEBUG_MODE is enabled. Disable in production.';
        }
        
        // Check error display
        if (ini_get('display_errors') == '1') {
            $issues[] = 'display_errors is enabled. Disable in production.';
        }
        
        // Check dangerous PHP functions
        $dangerousFunctions = ['eval', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open'];
        $disabledFunctions = explode(',', ini_get('disable_functions'));
        $disabledFunctions = array_map('trim', $disabledFunctions);
        
        foreach ($dangerousFunctions as $func) {
            if (!in_array($func, $disabledFunctions) && function_exists($func)) {
                $issues[] = "Dangerous function '{$func}' is not disabled.";
            }
        }
        
        // Check session security
        if (ini_get('session.cookie_httponly') != '1') {
            $issues[] = 'session.cookie_httponly should be enabled.';
        }
        
        // Check encryption key
        if (empty($_ENV['ENCRYPTION_KEY']) || $_ENV['ENCRYPTION_KEY'] === 'default_key_change_in_production') {
            $issues[] = 'ENCRYPTION_KEY is not set or using default value. Set a secure key in .env';
        }
        
        // Check HTTPS
        if (!self::isHttps() && ($_ENV['APP_ENV'] ?? 'production') === 'production') {
            $issues[] = 'HTTPS is not enabled in production.';
        }
        
        return [
            'secure' => empty($issues),
            'issues' => $issues
        ];
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
     * Get security recommendations
     */
    public static function getRecommendations(): array
    {
        return [
            'Set DEBUG_MODE=false in production',
            'Disable display_errors in production',
            'Disable dangerous PHP functions (eval, exec, etc.)',
            'Enable session.cookie_httponly',
            'Set a secure ENCRYPTION_KEY in .env',
            'Use HTTPS in production',
            'Enable HSTS headers',
            'Configure proper CORS settings',
            'Set secure file permissions (644 for files, 755 for directories)',
            'Regularly update dependencies',
            'Enable error logging to files (not displaying to users)',
            'Use prepared statements for all database queries',
            'Implement proper authentication and authorization',
            'Enable rate limiting',
            'Monitor security logs regularly'
        ];
    }
}


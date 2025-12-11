<?php

namespace App\Core\Security;

/**
 * Comprehensive Security Scanner
 * 
 * Scans requests for various security threats:
 * - SQL Injection attempts
 * - XSS attacks
 * - Path traversal
 * - Command injection
 * - File inclusion attacks
 * - Suspicious patterns
 */
class SecurityScanner
{
    private static array $sqlInjectionPatterns = [
        '/(\bUNION\b.*\bSELECT\b)/i',
        '/(\bSELECT\b.*\bFROM\b)/i',
        '/(\bINSERT\b.*\bINTO\b)/i',
        '/(\bUPDATE\b.*\bSET\b)/i',
        '/(\bDELETE\b.*\bFROM\b)/i',
        '/(\bDROP\b.*\bTABLE\b)/i',
        '/(\bEXEC\b|\bEXECUTE\b)/i',
        '/(\bSCRIPT\b)/i',
        '/(\bOR\b.*=.*)/i',
        '/(\bAND\b.*=.*)/i',
        '/(\'\s*OR\s*\'\s*=\s*\')/i',
        '/(\'\s*AND\s*\'\s*=\s*\')/i',
        '/(\b1\s*=\s*1\b)/i',
        '/(\b1\s*=\s*0\b)/i',
        '/(\bCONCAT\b)/i',
        '/(\bCHAR\b)/i',
        '/(\bASCII\b)/i',
        '/(\bSUBSTRING\b)/i',
        '/(\bBENCHMARK\b)/i',
        '/(\bSLEEP\b)/i',
        '/(\bWAITFOR\b)/i',
        '/(\bDELAY\b)/i',
        '/(;\s*(DROP|DELETE|INSERT|UPDATE|SELECT))/i',
    ];
    
    private static array $xssPatterns = [
        '/<script[^>]*>.*?<\/script>/is',
        '/<iframe[^>]*>.*?<\/iframe>/is',
        '/javascript:/i',
        '/on\w+\s*=/i', // onclick=, onload=, etc.
        '/<img[^>]*src[^>]*=.*javascript:/i',
        '/<svg[^>]*onload/i',
        '/<body[^>]*onload/i',
        '/<input[^>]*onfocus/i',
        '/<marquee/i',
        '/expression\s*\(/i',
        '/vbscript:/i',
        '/data:text\/html/i',
    ];
    
    private static array $pathTraversalPatterns = [
        '/\.\.\//',
        '/\.\.\\\\/',
        '/\.\.%2f/i',
        '/\.\.%5c/i',
        '/\.\.%252f/i',
        '/\.\.%255c/i',
        '/\.\.%c0%af/i',
        '/\.\.%c1%9c/i',
        '/\.\.%c0%2f/i',
        '/\.\.%c1%af/i',
    ];
    
    private static array $commandInjectionPatterns = [
        '/;\s*(rm|del|delete|format|mkfs)/i',
        '/\|\s*(rm|del|delete|format|mkfs)/i',
        '/`[^`]*`/',
        '/\$\([^)]*\)/',
        '/\$\{[^}]*\}/',
        '/&&\s*(rm|del|delete|format|mkfs)/i',
        '/\|\|\s*(rm|del|delete|format|mkfs)/i',
        '/\b(cat|ls|pwd|whoami|id|uname|ps|kill|chmod|chown)\b/i',
        '/\b(nc|netcat|wget|curl|fetch)\b/i',
        '/\b(bash|sh|zsh|csh|ksh|cmd|powershell)\b/i',
    ];
    
    private static array $fileInclusionPatterns = [
        '/\.\.\/\.\.\/\.\.\//',
        '/\.\.\\\\\.\.\\\\\.\.\\\\/',
        '/\/etc\/passwd/i',
        '/\/etc\/shadow/i',
        '/\/proc\/self\/environ/i',
        '/\/proc\/version/i',
        '/\/boot\.ini/i',
        '/\/windows\/system32/i',
        '/php:\/\/filter/i',
        '/php:\/\/input/i',
        '/data:\/\/text\/plain/i',
        '/expect:\/\//i',
    ];
    
    /**
     * Scan input for security threats
     */
    public static function scanInput($input, string $type = 'general'): array
    {
        $threats = [];
        
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $result = self::scanInput($value, $type);
                if (!empty($result['threats'])) {
                    $threats = array_merge($threats, $result['threats']);
                }
            }
        } elseif (is_string($input)) {
            $threats = array_merge(
                $threats,
                self::detectSqlInjection($input),
                self::detectXss($input),
                self::detectPathTraversal($input),
                self::detectCommandInjection($input),
                self::detectFileInclusion($input)
            );
        }
        
        return [
            'safe' => empty($threats),
            'threats' => $threats
        ];
    }
    
    /**
     * Detect SQL injection attempts
     */
    private static function detectSqlInjection(string $input): array
    {
        $threats = [];
        foreach (self::$sqlInjectionPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $threats[] = [
                    'type' => 'sql_injection',
                    'pattern' => $pattern,
                    'severity' => 'high'
                ];
            }
        }
        return $threats;
    }
    
    /**
     * Detect XSS attempts
     */
    private static function detectXss(string $input): array
    {
        $threats = [];
        foreach (self::$xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $threats[] = [
                    'type' => 'xss',
                    'pattern' => $pattern,
                    'severity' => 'high'
                ];
            }
        }
        return $threats;
    }
    
    /**
     * Detect path traversal attempts
     */
    private static function detectPathTraversal(string $input): array
    {
        $threats = [];
        foreach (self::$pathTraversalPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $threats[] = [
                    'type' => 'path_traversal',
                    'pattern' => $pattern,
                    'severity' => 'high'
                ];
            }
        }
        return $threats;
    }
    
    /**
     * Detect command injection attempts
     */
    private static function detectCommandInjection(string $input): array
    {
        $threats = [];
        foreach (self::$commandInjectionPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $threats[] = [
                    'type' => 'command_injection',
                    'pattern' => $pattern,
                    'severity' => 'critical'
                ];
            }
        }
        return $threats;
    }
    
    /**
     * Detect file inclusion attempts
     */
    private static function detectFileInclusion(string $input): array
    {
        $threats = [];
        foreach (self::$fileInclusionPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $threats[] = [
                    'type' => 'file_inclusion',
                    'pattern' => $pattern,
                    'severity' => 'high'
                ];
            }
        }
        return $threats;
    }
    
    /**
     * Scan request headers
     */
    public static function scanHeaders(): array
    {
        $threats = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0 && is_string($value)) {
                $result = self::scanInput($value, 'header');
                if (!empty($result['threats'])) {
                    $threats = array_merge($threats, $result['threats']);
                }
            }
        }
        
        return [
            'safe' => empty($threats),
            'threats' => $threats
        ];
    }
    
    /**
     * Scan all request data
     */
    public static function scanRequest(): array
    {
        $allThreats = [];
        
        // Scan GET parameters
        $getScan = self::scanInput($_GET, 'get');
        $allThreats = array_merge($allThreats, $getScan['threats']);
        
        // Scan POST parameters
        $postScan = self::scanInput($_POST, 'post');
        $allThreats = array_merge($allThreats, $postScan['threats']);
        
        // Scan headers
        $headerScan = self::scanHeaders();
        $allThreats = array_merge($allThreats, $headerScan['threats']);
        
        // Scan URI
        $uriScan = self::scanInput($_SERVER['REQUEST_URI'] ?? '', 'uri');
        $allThreats = array_merge($allThreats, $uriScan['threats']);
        
        return [
            'safe' => empty($allThreats),
            'threats' => $allThreats,
            'count' => count($allThreats)
        ];
    }
}


<?php

namespace App\Core\Security;

/**
 * Fast Security Scanner - Optimized with early exits and pattern caching
 * 
 * Scans requests for security threats with minimal overhead
 */
class FastSecurityScanner
{
    private static ?array $compiledPatterns = null;
    
    /**
     * Fast scan with early exit on first threat
     */
    public static function quickScan($input): bool
    {
        if (is_array($input)) {
            foreach ($input as $value) {
                if (!self::quickScan($value)) {
                    return false;
                }
            }
            return true;
        }
        
        if (!is_string($input) || empty($input)) {
            return true;
        }
        
        // Quick checks first (most common threats)
        if (strpos($input, '<script') !== false) return false;
        if (strpos($input, '../') !== false) return false;
        if (strpos($input, 'UNION') !== false || strpos($input, 'SELECT') !== false) return false;
        if (strpos($input, 'javascript:') !== false) return false;
        
        return true;
    }
    
    /**
     * Comprehensive scan (only if quick scan passes)
     */
    public static function scanInput($input, string $type = 'general'): array
    {
        // Quick scan first
        if (!self::quickScan($input)) {
            return ['safe' => false, 'threats' => [['type' => 'suspicious', 'severity' => 'medium']]];
        }
        
        // If quick scan passes, do detailed scan only if needed
        return SecurityScanner::scanInput($input, $type);
    }
    
    /**
     * Fast request scan - optimized for performance
     */
    public static function scanRequestFast(): array
    {
        // Quick scan URI first (most common attack vector)
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (!self::quickScan($uri)) {
            return ['safe' => false, 'threats' => [['type' => 'uri_threat', 'severity' => 'high']], 'count' => 1];
        }
        
        // Quick scan GET params
        if (!empty($_GET) && !self::quickScan($_GET)) {
            return ['safe' => false, 'threats' => [['type' => 'get_threat', 'severity' => 'medium']], 'count' => 1];
        }
        
        // Quick scan POST params (only if POST request)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && !self::quickScan($_POST)) {
            return ['safe' => false, 'threats' => [['type' => 'post_threat', 'severity' => 'medium']], 'count' => 1];
        }
        
        // All quick scans passed
        return ['safe' => true, 'threats' => [], 'count' => 0];
    }
}


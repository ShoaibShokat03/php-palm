<?php

namespace Frontend\Palm;

/**
 * Content Security Policy Generator
 * 
 * Generates CSP policies for different use cases
 */
class CspGenerator
{
    /**
     * Generate default CSP policy
     */
    public static function default(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            "img-src 'self' data: https:",
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "connect-src 'self'",
            "frame-src 'self' https:",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * Generate strict CSP policy
     */
    public static function strict(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }

    /**
     * Generate CSP policy for development
     */
    public static function development(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:* ws://localhost:*",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' http://localhost:* ws://localhost:*",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * Generate CSP policy with CDN support
     */
    public static function withCdn(array $cdnDomains = []): string
    {
        $cdnList = implode(' ', array_map(fn($domain) => "https://{$domain}", $cdnDomains));
        
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' {$cdnList}",
            "style-src 'self' {$cdnList}",
            "img-src 'self' data: https: {$cdnList}",
            "font-src 'self' {$cdnList}",
            "connect-src 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * Generate CSP policy for API-driven apps
     */
    public static function forApi(array $apiDomains = []): string
    {
        $apiList = implode(' ', array_map(fn($domain) => "https://{$domain}", $apiDomains));
        
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self'",
            "connect-src 'self' {$apiList}",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * Build custom CSP policy
     */
    public static function build(array $directives): string
    {
        $parts = [];
        foreach ($directives as $directive => $value) {
            if (is_array($value)) {
                $parts[] = $directive . ' ' . implode(' ', $value);
            } else {
                $parts[] = $directive . ' ' . $value;
            }
        }
        return implode('; ', $parts);
    }

    /**
     * Generate CSP meta tag
     */
    public static function metaTag(string $policy): string
    {
        return '<meta http-equiv="Content-Security-Policy" content="' . htmlspecialchars($policy, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Generate CSP report-only policy
     */
    public static function reportOnly(string $policy, string $reportUri): string
    {
        return $policy . "; report-uri {$reportUri}";
    }
}


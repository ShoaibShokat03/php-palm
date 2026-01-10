<?php

namespace Frontend\Palm;

/**
 * Security Headers Helper
 * 
 * Sets security headers for HTTP responses
 */
class SecurityHeaders
{
    protected static array $headers = [];
    protected static bool $sent = false;

    /**
     * Set all security headers
     */
    public static function set(array $options = []): void
    {
        if (self::$sent || headers_sent()) {
            return;
        }

        // Content Security Policy
        if (isset($options['csp'])) {
            self::setCSP($options['csp']);
        }

        // X-Content-Type-Options
        self::setHeader('X-Content-Type-Options', $options['content_type_options'] ?? 'nosniff');

        // X-Frame-Options
        self::setHeader('X-Frame-Options', $options['frame_options'] ?? 'DENY');

        // X-XSS-Protection (legacy but still useful)
        self::setHeader('X-XSS-Protection', $options['xss_protection'] ?? '1; mode=block');

        // Referrer-Policy
        self::setHeader('Referrer-Policy', $options['referrer_policy'] ?? 'strict-origin-when-cross-origin');

        // Permissions-Policy (formerly Feature-Policy)
        if (isset($options['permissions_policy'])) {
            self::setHeader('Permissions-Policy', $options['permissions_policy']);
        }

        // Strict-Transport-Security (HSTS)
        if (isset($options['hsts'])) {
            self::setHeader('Strict-Transport-Security', $options['hsts']);
        }

        // Content-Security-Policy-Report-Only
        if (isset($options['csp_report_only'])) {
            self::setHeader('Content-Security-Policy-Report-Only', $options['csp_report_only']);
        }

        self::$sent = true;
    }

    /**
     * Set Content Security Policy
     */
    public static function setCSP(string|array $policy): void
    {
        if (is_array($policy)) {
            $directives = [];
            foreach ($policy as $directive => $value) {
                if (is_numeric($directive)) {
                    // If key is numeric, treat value as a complete directive string
                    $directives[] = $value;
                } elseif (is_array($value)) {
                    $directives[] = $directive . ' ' . implode(' ', $value);
                } else {
                    $directives[] = $directive . ' ' . $value;
                }
            }
            $policy = implode('; ', $directives);
        }

        self::setHeader('Content-Security-Policy', $policy);
    }

    /**
     * Set individual header
     */
    public static function setHeader(string $name, string $value): void
    {
        if (!headers_sent()) {
            header("{$name}: {$value}");
            self::$headers[$name] = $value;
        }
    }

    /**
     * Get all set headers
     */
    public static function getHeaders(): array
    {
        return self::$headers;
    }

    /**
     * Set default security headers (recommended for production)
     */
    public static function setDefaults(): void
    {
        // Build CSP as a single string
        $cspPolicy = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            "img-src 'self' data: https:",
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "connect-src 'self'",
            "frame-src 'self' https:",
            "frame-ancestors 'none'",
        ]);

        self::set([
            'content_type_options' => 'nosniff',
            'frame_options' => 'DENY',
            'xss_protection' => '1; mode=block',
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
            'csp' => $cspPolicy, // Pass as string, not array
        ]);
    }

    /**
     * Set strict security headers (maximum security)
     */
    public static function setStrict(): void
    {
        // Build CSP as a single string
        $cspPolicy = implode('; ', [
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

        self::set([
            'content_type_options' => 'nosniff',
            'frame_options' => 'DENY',
            'xss_protection' => '1; mode=block',
            'referrer_policy' => 'no-referrer',
            'permissions_policy' => 'geolocation=(), microphone=(), camera=(), payment=()',
            'hsts' => 'max-age=31536000; includeSubDomains; preload',
            'csp' => $cspPolicy, // Pass as string, not array
        ]);
    }
}


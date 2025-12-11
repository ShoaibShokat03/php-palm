<?php

namespace Frontend\Palm;

/**
 * Auto CSRF Injection System
 * 
 * Automatically injects CSRF tokens into forms using output buffering
 */
class CsrfInjector
{
    protected static bool $enabled = true;
    protected static bool $initialized = false;

    /**
     * Initialize CSRF injector
     */
    public static function init(): void
    {
        if (self::$initialized || !self::$enabled) {
            return;
        }

        // Start output buffering to intercept HTML
        ob_start(function ($buffer) {
            return self::injectCsrfTokens($buffer);
        });

        self::$initialized = true;
    }

    /**
     * Inject CSRF tokens into forms
     */
    protected static function injectCsrfTokens(string $buffer): string
    {
        // Only process HTML content
        if (stripos($buffer, '<html') === false && stripos($buffer, '<!doctype') === false) {
            return $buffer;
        }

        // Pattern to match <form> tags that don't already have CSRF
        $pattern = '/<form\s+([^>]*method\s*=\s*["\'](?:POST|PUT|DELETE|PATCH)["\'][^>]*)>/i';
        
        $buffer = preg_replace_callback($pattern, function ($matches) {
            $formTag = $matches[0];
            
            // Check if CSRF token already exists
            if (strpos($formTag, 'csrf_token') !== false || 
                strpos($formTag, 'name="csrf_token"') !== false ||
                strpos($formTag, 'name=\'csrf_token\'') !== false) {
                return $formTag; // Already has CSRF
            }

            // Check if method is GET (no CSRF needed)
            if (preg_match('/method\s*=\s*["\']GET["\']/i', $formTag)) {
                return $formTag; // GET requests don't need CSRF
            }

            // Inject CSRF token after opening form tag
            $csrfField = "\n    " . csrf_field();
            return str_replace('>', '>' . $csrfField, $formTag);
        }, $buffer);

        return $buffer;
    }

    /**
     * Disable auto injection
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Enable auto injection
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Check if enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
}


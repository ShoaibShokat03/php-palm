<?php

namespace Frontend\Palm;

/**
 * FastRender - SEO-Friendly Automatic Fast Loading
 * 
 * Sends the <head> section to the browser immediately while PHP continues
 * processing. This allows browsers to start loading CSS/fonts while data
 * is being fetched from the database.
 * 
 * Features:
 * - Zero configuration required - works automatically
 * - SEO-safe - full HTML is server-rendered
 * - Flushes head section early for faster First Paint
 * - Works with all existing layouts
 * 
 * Usage (automatic in layout):
 * <?php FastRender::startHead() ?>
 * <head>...</head>
 * <?php FastRender::endHead() ?>
 * <body>...</body>
 * <?php FastRender::end() ?>
 */
class FastRender
{
    private static bool $initialized = false;
    private static bool $headFlushed = false;
    private static bool $enabled = true;
    private static float $startTime = 0;

    /**
     * Initialize fast rendering for this request
     * Called automatically - no manual setup needed
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;
        self::$startTime = microtime(true);

        // Disable in development mode if debugging output buffers
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
        if (strtolower($env) === 'development' && isset($_GET['debug'])) {
            self::$enabled = false;
        }

        // Ensure output buffering doesn't block our flushes
        if (self::$enabled && !headers_sent()) {
            // Set headers for chunked transfer (streaming)
            // This allows browsers to start rendering before full response
            if (!headers_sent()) {
                header('X-Accel-Buffering: no'); // Disable nginx buffering
            }
        }
    }

    /**
     * Start capturing the <head> section
     * Call this at the very beginning of your layout
     */
    public static function startHead(): void
    {
        self::init();

        if (!self::$enabled) {
            return;
        }

        // Capture output from this point
        ob_start();
    }

    /**
     * End and flush the <head> section immediately to browser
     * The browser can now start loading CSS, fonts, and scripts
     * while PHP continues processing the body
     */
    public static function endHead(): void
    {
        if (!self::$enabled || self::$headFlushed) {
            return;
        }

        self::$headFlushed = true;

        // Get the captured head content
        $headContent = ob_get_clean();

        // Output the head immediately
        echo $headContent;

        // Flush to browser - this is the key optimization
        // Browser receives head and starts loading CSS/fonts immediately
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        if (function_exists('flush')) {
            @flush();
        }

        // Start capturing for body
        ob_start();
    }

    /**
     * Complete the response
     * Call this at the end of your layout
     */
    public static function end(): void
    {
        if (!self::$enabled) {
            return;
        }

        // Get any remaining buffered content
        if (ob_get_level() > 0) {
            $bodyContent = ob_get_clean();
            echo $bodyContent;
        }

        // Final flush
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        if (function_exists('flush')) {
            @flush();
        }
    }

    /**
     * Check if fast rendering is active
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Disable fast rendering for this request
     * Use when you need full control over output buffering
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Get time since request started (for debugging)
     */
    public static function getElapsedTime(): float
    {
        return (microtime(true) - self::$startTime) * 1000; // ms
    }

    /**
     * Add a comment showing render timing (development only)
     */
    public static function renderTimingComment(): string
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
        if (strtolower($env) !== 'development') {
            return '';
        }

        $elapsed = self::getElapsedTime();
        return sprintf(
            '<!-- FastRender: Head flushed at %.2fms, Total: %.2fms -->',
            self::$headFlushed ? $elapsed : 0,
            $elapsed
        );
    }

    /**
     * Reset state (for testing)
     */
    public static function reset(): void
    {
        self::$initialized = false;
        self::$headFlushed = false;
        self::$enabled = true;
        self::$startTime = 0;
    }
}

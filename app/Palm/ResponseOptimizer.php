<?php

namespace Frontend\Palm;

/**
 * Response Optimizer
 * 
 * Handles output compression and HTTP caching
 */
class ResponseOptimizer
{
    protected static bool $compressionEnabled = true;
    protected static bool $headersSent = false;

    /**
     * Initialize response optimization
     */
    public static function init(): void
    {
        // Enable compression if not already enabled
        if (self::$compressionEnabled && !ob_get_level()) {
            self::startCompression();
        }
    }

    /**
     * Start output compression
     */
    protected static function startCompression(): void
    {
        // Check if compression is already enabled
        if (ob_get_level() > 0) {
            return;
        }

        // Check if client supports compression
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        // Try Brotli first (better compression)
        if (function_exists('brotli_compress') && strpos($acceptEncoding, 'br') !== false) {
            if (ob_start('brotli_compress')) {
                header('Content-Encoding: br');
                return;
            }
        }
        
        // Fallback to Gzip
        if (function_exists('gzencode') && strpos($acceptEncoding, 'gzip') !== false) {
            if (ob_start('ob_gzhandler')) {
                return;
            }
        }

        // No compression available, start normal output buffer
        ob_start();
    }

    /**
     * Set cache headers
     */
    public static function cache(int $seconds = 3600, bool $public = true): void
    {
        if (self::$headersSent) {
            return;
        }

        $cacheControl = ($public ? 'public' : 'private') . ', max-age=' . $seconds;
        header('Cache-Control: ' . $cacheControl);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
    }

    /**
     * Set ETag header
     */
    public static function etag(string $etag): void
    {
        if (self::$headersSent) {
            return;
        }

        header('ETag: "' . $etag . '"');
        
        // Check if client has matching ETag
        $clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($clientEtag === '"' . $etag . '"' || $clientEtag === $etag) {
            http_response_code(304);
            exit;
        }
    }

    /**
     * Set Last-Modified header
     */
    public static function lastModified(int $timestamp): void
    {
        if (self::$headersSent) {
            return;
        }

        $lastModified = gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
        header('Last-Modified: ' . $lastModified);
        
        // Check if client has cached version
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        if ($ifModifiedSince === $lastModified) {
            http_response_code(304);
            exit;
        }
    }

    /**
     * Disable caching
     */
    public static function noCache(): void
    {
        if (self::$headersSent) {
            return;
        }

        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * Mark headers as sent
     */
    public static function markHeadersSent(): void
    {
        self::$headersSent = true;
    }

    /**
     * Check if compression is enabled
     */
    public static function isCompressionEnabled(): bool
    {
        return self::$compressionEnabled;
    }
}


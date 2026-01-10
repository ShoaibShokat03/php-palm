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
     * Start output compression with optimized handlers
     * Supports Gzip and Brotli (if available)
     */
    protected static function startCompression(): void
    {
        // Check if compression is already enabled
        if (ob_get_level() > 0) {
            return;
        }

        // Check if client supports compression
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

        // Try Brotli first (better compression ratio, ~20% better than gzip)
        if (strpos($acceptEncoding, 'br') !== false) {
            // Use ob_start with callback for Brotli (brotli_compress is not a PHP function)
            if (ob_start(function ($buffer) {
                // Check if output is too small to compress
                if (strlen($buffer) < 1024) {
                    return $buffer;
                }

                // Try to use Brotli if extension is available
                if (extension_loaded('brotli')) {
                    $compressed = brotli_compress($buffer, 4); // Level 4 = good balance
                    if ($compressed !== false && strlen($compressed) < strlen($buffer)) {
                        header('Content-Encoding: br', true);
                        header('Vary: Accept-Encoding', false);
                        return $compressed;
                    }
                }

                // Fallback to gzip if Brotli failed
                return self::compressGzip($buffer);
            })) {
                return;
            }
        }

        // Fallback to Gzip (most compatible)
        if (strpos($acceptEncoding, 'gzip') !== false) {
            if (ob_start(function ($buffer) {
                return self::compressGzip($buffer);
            })) {
                return;
            }
        }

        // No compression available, start normal output buffer
        ob_start();
    }

    /**
     * Compress content with Gzip
     */
    protected static function compressGzip(string $buffer): string
    {
        // Don't compress if too small
        if (strlen($buffer) < 1024) {
            return $buffer;
        }

        // Use ob_gzhandler if available (built-in, optimized)
        if (function_exists('ob_gzhandler')) {
            $compressed = ob_gzhandler($buffer, 4); // 4 = output buffer level
            if ($compressed !== false) {
                header('Content-Encoding: gzip', true);
                header('Vary: Accept-Encoding', false);
                return $compressed;
            }
        }

        // Fallback to gzencode
        if (function_exists('gzencode')) {
            $compressed = gzencode($buffer, 6); // Level 6 = good balance
            if ($compressed !== false && strlen($compressed) < strlen($buffer)) {
                header('Content-Encoding: gzip', true);
                header('Vary: Accept-Encoding', false);
                return $compressed;
            }
        }

        return $buffer;
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
     * Set ETag header with automatic 304 handling
     */
    public static function etag(string $etag): void
    {
        if (self::$headersSent) {
            return;
        }

        // Normalize ETag (add quotes if not present)
        if (!str_starts_with($etag, '"')) {
            $etag = '"' . $etag . '"';
        }

        header('ETag: ' . $etag);

        // Check if client has matching ETag (304 Not Modified)
        $clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($clientEtag !== '' && ($clientEtag === $etag || trim($clientEtag, '"') === trim($etag, '"'))) {
            http_response_code(304);
            header('Content-Length: 0');
            exit;
        }
    }

    /**
     * Generate ETag from content hash
     */
    public static function etagFromContent(string $content): string
    {
        return md5($content);
    }

    /**
     * Generate ETag from file
     */
    public static function etagFromFile(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        // Use file mtime + size for fast ETag generation
        return md5($filePath . filemtime($filePath) . filesize($filePath));
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

    /**
     * Minify HTML output (production only)
     * Removes unnecessary whitespace while preserving structure
     */
    public static function minifyHtml(string $html): string
    {
        // Only minify in production
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
        if (strtolower($env) === 'development' || strtolower($env) === 'dev') {
            return $html;
        }

        // Preserve content in <pre>, <textarea>, <script>, <style>
        $preserve = [];
        $html = preg_replace_callback(
            '/<(pre|textarea|script|style)[^>]*>.*?<\/\1>/is',
            function ($matches) use (&$preserve) {
                $unique = '___PRESERVE_' . count($preserve) . '_' . bin2hex(random_bytes(4)) . '___';
                $preserve[$unique] = $matches[0];
                return $unique;
            },
            $html
        );

        // Remove HTML comments (except conditional comments and some structural markers)
        $html = preg_replace('/<!--(?!\[if|<!\[endif|[\w\. \-]*?-->)[^>]*-->/s', '', $html);

        // Remove whitespace between tags
        $html = preg_replace('/>\s+</', '><', $html);

        // Remove leading/trailing whitespace from lines
        $html = preg_replace('/^\s+|\s+$/m', '', $html);

        // Remove multiple spaces/newlines (but keep single space)
        $html = preg_replace('/\s{2,}/', ' ', $html);

        // Restore preserved content
        foreach ($preserve as $key => $content) {
            $html = str_replace($key, $content, $html);
        }

        return trim($html);
    }

    /**
     * Enable HTML minification in output buffer
     * Adds minification as the outermost buffer (runs last)
     * Works with existing output buffers (CSRF injector, compression, etc.)
     */
    public static function enableHtmlMinification(): void
    {
        // Add minification as the outermost buffer
        // This will run after all other buffers (CSRF, compression, etc.)
        ob_start(function ($buffer) {
            // Only minify if it looks like HTML
            if (stripos($buffer, '<html') !== false || stripos($buffer, '<!doctype') !== false) {
                $minified = self::minifyHtml($buffer);

                // Inject progressive loading optimizations
                require_once __DIR__ . '/ProgressiveResourceLoader.php';
                $optimized = ProgressiveResourceLoader::injectOptimizations($minified);

                return $optimized;
            }
            return $buffer;
        });
    }
}

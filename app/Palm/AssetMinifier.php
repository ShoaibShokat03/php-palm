<?php

namespace Frontend\Palm;

/**
 * Asset Minifier
 * 
 * Provides CSS and JS minification (basic implementation)
 */
class AssetMinifier
{
    protected static string $publicPath = '';
    protected static bool $enabled = true;

    /**
     * Initialize asset minifier
     */
    public static function init(string $baseDir): void
    {
        self::$publicPath = $baseDir . '/public';
    }

    /**
     * Minify CSS
     */
    public static function minifyCss(string $css): string
    {
        if (!self::$enabled) {
            return $css;
        }

        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
        $css = preg_replace('/;}/', '}', $css);
        
        // Remove trailing semicolons
        $css = preg_replace('/;([^a-z])/i', '$1', $css);
        
        return trim($css);
    }

    /**
     * Minify JavaScript
     */
    public static function minifyJs(string $js): string
    {
        if (!self::$enabled) {
            return $js;
        }

        // Remove single-line comments
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*[^*]*\*+([^/][^*]*\*+)*\//', '', $js);
        
        // Remove whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        $js = preg_replace('/\s*([{}();,\[\]])\s*/', '$1', $js);
        
        return trim($js);
    }

    /**
     * Minify file
     */
    public static function minifyFile(string $filePath, string $type = 'auto'): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        
        // Auto-detect type
        if ($type === 'auto') {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $type = $extension === 'css' ? 'css' : ($extension === 'js' ? 'js' : '');
        }

        if ($type === 'css') {
            $minified = self::minifyCss($content);
        } elseif ($type === 'js') {
            $minified = self::minifyJs($content);
        } else {
            return false;
        }

        // Generate minified file path
        $pathInfo = pathinfo($filePath);
        $minPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.min.' . $pathInfo['extension'];
        
        return file_put_contents($minPath, $minified) !== false;
    }

    /**
     * Enable/disable minification
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Check if minification is enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
}


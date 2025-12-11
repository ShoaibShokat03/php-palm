<?php

namespace Frontend\Palm;

/**
 * XSS Protection Helper
 * 
 * Provides automatic XSS protection for output in views
 */
class XssProtection
{
    protected static bool $enabled = true;
    protected static array $allowedTags = [];
    protected static bool $stripTags = false;

    /**
     * Initialize XSS protection
     */
    public static function init(bool $enabled = true, bool $stripTags = false, array $allowedTags = []): void
    {
        self::$enabled = $enabled;
        self::$stripTags = $stripTags;
        self::$allowedTags = $allowedTags;
    }

    /**
     * Escape output to prevent XSS
     * 
     * @param mixed $value Value to escape
     * @param bool $doubleEncode Whether to double-encode existing entities
     * @return string Escaped string
     */
    public static function escape(mixed $value, bool $doubleEncode = true): string
    {
        if (!self::$enabled) {
            return (string)$value;
        }

        if ($value === null || $value === '') {
            return '';
        }

        // Convert to string if not already
        if (!is_string($value)) {
            $value = (string)$value;
        }

        // HTML escape
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', $doubleEncode);

        // Strip tags if configured
        if (self::$stripTags) {
            $allowed = !empty(self::$allowedTags) ? implode('', self::$allowedTags) : '';
            $escaped = strip_tags($escaped, $allowed);
        }

        return $escaped;
    }

    /**
     * Escape attributes (for HTML attributes)
     */
    public static function escapeAttr(mixed $value): string
    {
        return self::escape($value);
    }

    /**
     * Escape JavaScript string
     */
    public static function escapeJs(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = (string)$value;
        
        // Escape for JavaScript string
        $escaped = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        
        // Remove surrounding quotes (json_encode adds them)
        return substr($escaped, 1, -1);
    }

    /**
     * Escape URL
     */
    public static function escapeUrl(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return urlencode((string)$value);
    }

    /**
     * Clean HTML (strip dangerous tags and attributes)
     */
    public static function clean(string $html, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            $allowedTags = ['p', 'br', 'strong', 'em', 'u', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        }

        // Strip dangerous tags
        $html = preg_replace('/<(script|iframe|object|embed|form|input|button)[^>]*>.*?<\/\1>/is', '', $html);
        
        // Strip dangerous attributes
        $html = preg_replace('/\s*(on\w+|javascript:|data:text\/html)/i', '', $html);
        
        // Strip tags not in allowed list
        $allowed = implode('|', array_map('preg_quote', $allowedTags));
        $html = preg_replace('/<(?!\/?(?:' . $allowed . ')(?:\s|>))/i', '&lt;', $html);
        $html = preg_replace('/<\/(?!' . $allowed . '>)/i', '', $html);

        return $html;
    }

    /**
     * Auto-escape array recursively
     */
    public static function escapeArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $escapedKey = self::escape($key);
            if (is_array($value)) {
                $result[$escapedKey] = self::escapeArray($value);
            } elseif (is_string($value) || is_numeric($value)) {
                $result[$escapedKey] = self::escape($value);
            } else {
                $result[$escapedKey] = $value;
            }
        }
        return $result;
    }
}


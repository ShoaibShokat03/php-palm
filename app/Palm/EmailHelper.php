<?php

namespace Frontend\Palm;

/**
 * Email Helper
 * 
 * Provides email-related helper functions
 */
class EmailHelper
{
    /**
     * Generate mailto link
     */
    public static function mailto(string $email, ?string $text = null, array $options = []): string
    {
        $text = $text ?? $email;
        $subject = $options['subject'] ?? '';
        $body = $options['body'] ?? '';
        $cc = $options['cc'] ?? '';
        $bcc = $options['bcc'] ?? '';
        $class = $options['class'] ?? '';

        $params = [];
        if ($subject) {
            $params['subject'] = $subject;
        }
        if ($body) {
            $params['body'] = $body;
        }
        if ($cc) {
            $params['cc'] = $cc;
        }
        if ($bcc) {
            $params['bcc'] = $bcc;
        }

        $href = 'mailto:' . urlencode($email);
        if (!empty($params)) {
            $href .= '?' . http_build_query($params);
        }

        $attrString = 'href="' . htmlspecialchars($href) . '"';
        if ($class) {
            $attrString .= ' class="' . htmlspecialchars($class) . '"';
        }

        return '<a ' . $attrString . '>' . htmlspecialchars($text) . '</a>';
    }

    /**
     * Obfuscate email to prevent spam
     */
    public static function obfuscate(string $email): string
    {
        $obfuscated = '';
        $length = strlen($email);
        
        for ($i = 0; $i < $length; $i++) {
            $char = $email[$i];
            $obfuscated .= '&#x' . dechex(ord($char)) . ';';
        }
        
        return $obfuscated;
    }

    /**
     * Validate email format
     */
    public static function isValid(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Extract domain from email
     */
    public static function getDomain(string $email): ?string
    {
        if (!self::isValid($email)) {
            return null;
        }

        $parts = explode('@', $email);
        return $parts[1] ?? null;
    }
}


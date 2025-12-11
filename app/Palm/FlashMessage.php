<?php

namespace Frontend\Palm;

/**
 * Flash Message Helper
 * 
 * Provides flash message storage and retrieval
 */
class FlashMessage
{
    /**
     * Set flash message
     */
    public static function set(string $key, string $message, string $type = 'info'): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['_flash'][$key] = [
            'message' => $message,
            'type' => $type,
        ];
    }

    /**
     * Get flash message
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['_flash'][$key])) {
            $flash = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $flash['message'] ?? $default;
        }

        return $default;
    }

    /**
     * Get flash message with type
     */
    public static function getWithType(string $key): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['_flash'][$key])) {
            $flash = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $flash;
        }

        return null;
    }

    /**
     * Check if flash message exists
     */
    public static function has(string $key): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Render flash message as HTML
     */
    public static function render(string $key, array $options = []): string
    {
        $flash = self::getWithType($key);
        
        if (!$flash) {
            return '';
        }

        $message = $flash['message'];
        $type = $flash['type'] ?? 'info';
        $dismissible = $options['dismissible'] ?? true;
        $class = $options['class'] ?? '';

        $alertClass = 'alert alert-' . $type;
        if ($class) {
            $alertClass .= ' ' . $class;
        }

        $html = '<div class="' . htmlspecialchars($alertClass) . '" role="alert">';
        $html .= htmlspecialchars($message);
        
        if ($dismissible) {
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        }
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Quick helper methods
     */
    public static function success(string $message): void
    {
        self::set('success', $message, 'success');
    }

    public static function error(string $message): void
    {
        self::set('error', $message, 'danger');
    }

    public static function warning(string $message): void
    {
        self::set('warning', $message, 'warning');
    }

    public static function info(string $message): void
    {
        self::set('info', $message, 'info');
    }
}


<?php

namespace Frontend\Palm;

/**
 * Dark Mode Support
 * 
 * Provides dark mode detection and switching
 */
class DarkMode
{
    protected static string $storageKey = 'palm_theme';
    protected static string $defaultTheme = 'auto'; // auto, light, dark

    /**
     * Get current theme preference
     */
    public static function getTheme(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION[self::$storageKey] ?? self::$defaultTheme;
    }

    /**
     * Set theme preference
     */
    public static function setTheme(string $theme): void
    {
        if (!in_array($theme, ['auto', 'light', 'dark'], true)) {
            $theme = self::$defaultTheme;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION[self::$storageKey] = $theme;
    }

    /**
     * Check if dark mode is active
     */
    public static function isDark(): bool
    {
        $theme = self::getTheme();
        
        if ($theme === 'dark') {
            return true;
        }
        
        if ($theme === 'light') {
            return false;
        }
        
        // Auto mode - check system preference (client-side)
        // Server-side, we default to false, client JS will handle it
        return false;
    }

    /**
     * Generate theme toggle script
     */
    public static function getToggleScript(): string
    {
        $currentTheme = self::getTheme();
        
        return <<<JS
<script>
(function() {
    'use strict';
    
    const STORAGE_KEY = 'palm_theme';
    const THEMES = ['auto', 'light', 'dark'];
    
    // Get current theme from session/cookie or default
    function getTheme() {
        // Try to get from cookie first
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === STORAGE_KEY) {
                return decodeURIComponent(value);
            }
        }
        return '{$currentTheme}';
    }
    
    // Set theme
    function setTheme(theme) {
        if (!THEMES.includes(theme)) {
            theme = 'auto';
        }
        
        // Save to cookie
        document.cookie = STORAGE_KEY + '=' + encodeURIComponent(theme) + '; path=/; max-age=31536000';
        
        // Apply theme
        applyTheme(theme);
    }
    
    // Apply theme to document
    function applyTheme(theme) {
        const html = document.documentElement;
        html.removeAttribute('data-theme');
        html.removeAttribute('data-bs-theme');
        
        if (theme === 'auto') {
            // Use system preference
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            html.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
            html.setAttribute('data-bs-theme', prefersDark ? 'dark' : 'light');
        } else {
            html.setAttribute('data-theme', theme);
            html.setAttribute('data-bs-theme', theme);
        }
    }
    
    // Initialize theme on page load
    const initialTheme = getTheme();
    applyTheme(initialTheme);
    
    // Listen for system theme changes (when in auto mode)
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (getTheme() === 'auto') {
            applyTheme('auto');
        }
    });
    
    // Expose toggle function globally
    window.palmTheme = {
        set: setTheme,
        get: getTheme,
        toggle: function() {
            const current = getTheme();
            const next = current === 'dark' ? 'light' : (current === 'light' ? 'auto' : 'dark');
            setTheme(next);
        }
    };
})();
</script>
JS;
    }

    /**
     * Generate theme toggle button HTML
     */
    public static function getToggleButton(string $label = 'Toggle Theme'): string
    {
        return <<<HTML
<button type="button" class="btn btn-outline-secondary" onclick="palmTheme.toggle()" aria-label="{$label}">
    <span class="theme-icon">🌓</span>
</button>
HTML;
    }

    /**
     * Generate CSS variables for dark mode
     */
    public static function getThemeCss(): string
    {
        return <<<CSS
:root {
    --bg-color: #ffffff;
    --text-color: #212529;
    --border-color: #dee2e6;
    --primary-color: #0d6efd;
}

[data-theme="dark"] {
    --bg-color: #212529;
    --text-color: #f8f9fa;
    --border-color: #495057;
    --primary-color: #0d6efd;
}

body {
    background-color: var(--bg-color);
    color: var(--text-color);
    transition: background-color 0.3s, color 0.3s;
}
CSS;
    }
}


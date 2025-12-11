<?php

namespace Frontend\Palm;

/**
 * View handler for .palm.php files
 * Renders PHP views directly
 */
class PalmViewHandler
{
    protected static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;
    }

    /**
     * Render a .palm.php file
     * 
     * @param string $filePath Path to .palm.php file
     * @param array $data Data to pass to view
     * @return array ['html' => string, 'js' => string, 'metadata' => array]
     */
    public static function render(string $filePath, array $data = []): array
    {
        self::init();

        // Extract variables for view
        extract($data);

        // Execute PHP file directly and capture output
        ob_start();
        require $filePath;
        $html = ob_get_clean();

        return [
            'html' => $html,
            'js' => '',
            'metadata' => [],
            'sourceMap' => [],
        ];
    }

    /**
     * Check if a file needs compilation
     */
    public static function needsCompilation(string $filePath): bool
    {
        return false; // No compilation needed
    }

    /**
     * Get compiled JS module path (deprecated - no compilation)
     */
    public static function getJsModulePath(string $filePath): string
    {
        return ''; // No compiled JS files
    }

    /**
     * Invalidate cache when file changes (no-op since no compilation)
     */
    public static function invalidate(string $filePath): void
    {
        // No compilation cache to invalidate
    }
}


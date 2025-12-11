<?php

namespace Frontend\Palm;

/**
 * Breadcrumb Helper
 * 
 * Generates breadcrumb navigation
 */
class Breadcrumb
{
    protected static array $items = [];

    /**
     * Add breadcrumb item
     */
    public static function add(string $label, ?string $url = null): void
    {
        self::$items[] = [
            'label' => $label,
            'url' => $url,
        ];
    }

    /**
     * Set breadcrumb items
     */
    public static function set(array $items): void
    {
        self::$items = $items;
    }

    /**
     * Clear breadcrumbs
     */
    public static function clear(): void
    {
        self::$items = [];
    }

    /**
     * Generate breadcrumb HTML
     */
    public static function render(array $options = []): string
    {
        $class = $options['class'] ?? 'breadcrumb';
        $separator = $options['separator'] ?? '/';
        $homeLabel = $options['home_label'] ?? 'Home';
        $homeUrl = $options['home_url'] ?? '/';

        if (empty(self::$items)) {
            return '';
        }

        $html = '<nav aria-label="Breadcrumb"><ol class="' . htmlspecialchars($class) . '">';
        
        // Home link (if first item is not home)
        if (empty(self::$items) || self::$items[0]['url'] !== '/' && self::$items[0]['label'] !== $homeLabel) {
            $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($homeUrl) . '">' . 
                     htmlspecialchars($homeLabel) . '</a></li>';
        }

        // Breadcrumb items
        $lastIndex = count(self::$items) - 1;
        foreach (self::$items as $index => $item) {
            $isLast = $index === $lastIndex;
            
            if ($isLast) {
                $html .= '<li class="breadcrumb-item active" aria-current="page">' . 
                         htmlspecialchars($item['label']) . '</li>';
            } else {
                $url = $item['url'] ?? '#';
                $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($url) . '">' . 
                         htmlspecialchars($item['label']) . '</a></li>';
            }
        }

        $html .= '</ol></nav>';

        return $html;
    }

    /**
     * Auto-generate breadcrumbs from current path
     */
    public static function fromPath(string $path = null, array $options = []): string
    {
        $path = $path ?? current_path();
        $parts = array_filter(explode('/', trim($path, '/')));
        
        $items = [];
        $currentPath = '';
        
        foreach ($parts as $part) {
            $currentPath .= '/' . $part;
            $label = ucfirst(str_replace(['-', '_'], ' ', $part));
            $items[] = [
                'label' => $label,
                'url' => $currentPath,
            ];
        }

        self::set($items);
        return self::render($options);
    }

    /**
     * Get all items
     */
    public static function getItems(): array
    {
        return self::$items;
    }
}


<?php

namespace Frontend\Palm;

/**
 * Page - Static class for managing page metadata and SEO
 * 
 * Usage:
 *   Page::meta->title = 'My Page Title';
 *   Page::meta->description = 'Page description';
 *   Page::title('My Page')->description('Description')->keywords(['seo', 'meta']);
 *   
 *   In layout: <?= Page::meta->render() ?>
 */
class Page
{
    /**
     * @var PageMeta Static meta instance
     */
    public static ?PageMeta $meta = null;

    /**
     * Initialize with default meta
     */
    public static function init(array $defaults = []): void
    {
        if (self::$meta === null) {
            self::$meta = new PageMeta($defaults);
        }
    }

    /**
     * Set page title (fluent)
     */
    public static function title(string $title): PageMeta
    {
        self::ensureInitialized();
        return self::$meta->title($title);
    }

    /**
     * Set page description (fluent)
     */
    public static function description(string $desc): PageMeta
    {
        self::ensureInitialized();
        return self::$meta->description($desc);
    }

    /**
     * Set keywords (fluent)
     */
    public static function keywords($keywords): PageMeta
    {
        self::ensureInitialized();
        return self::$meta->keywords($keywords);
    }

    /**
     * Set Open Graph image (fluent)
     */
    public static function ogImage(string $url): PageMeta
    {
        self::ensureInitialized();
        return self::$meta->ogImage($url);
    }

    /**
     * Set canonical URL (fluent)
     */
    public static function canonical(string $url): PageMeta
    {
        self::ensureInitialized();
        return self::$meta->canonical($url);
    }

    /**
     * Set author
     */
    public static function author(string $author): PageMeta
    {
        self::ensureInitialized();
        self::$meta->author = $author;
        return self::$meta;
    }

    /**
     * Set robots meta
     */
    public static function robots(string $robots): PageMeta
    {
        self::ensureInitialized();
        self::$meta->robots = $robots;
        return self::$meta;
    }

    /**
     * Add custom meta tag
     */
    public static function addMeta(string $name, string $content, string $type = 'name'): PageMeta
    {
        self::ensureInitialized();
        return self::$meta->addMeta($name, $content, $type);
    }

    /**
     * Reset meta to defaults
     */
    public static function reset(array $defaults = []): void
    {
        self::$meta = new PageMeta($defaults);
    }

    /**
     * Ensure meta is initialized
     */
    protected static function ensureInitialized(): void
    {
        if (self::$meta === null) {
            self::init();
        }
    }

    /**
     * Get the meta instance (for direct property access)
     * Usage: Page::getMeta()->title = 'value'
     */
    public static function getMeta(): PageMeta
    {
        self::ensureInitialized();
        return self::$meta;
    }

    /**
     * Get current meta as array
     */
    public static function toArray(): array
    {
        self::ensureInitialized();
        return self::$meta->toArray();
    }
}

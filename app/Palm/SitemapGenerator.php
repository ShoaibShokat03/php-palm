<?php

namespace Frontend\Palm;

/**
 * Sitemap Generator
 * 
 * Generates XML sitemap from registered routes
 */
class SitemapGenerator
{
    protected static string $publicPath = '';
    protected static array $routes = [];
    protected static string $baseUrl = '';

    /**
     * Initialize sitemap generator
     */
    public static function init(string $baseDir, string $baseUrl = ''): void
    {
        self::$publicPath = $baseDir . '/public';
        
        if (empty($baseUrl)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host;
        }
        
        self::$baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Set routes for sitemap generation
     */
    public static function setRoutes(array $routes): void
    {
        self::$routes = $routes;
    }

    /**
     * Generate sitemap.xml
     */
    public static function generate(array $options = []): bool
    {
        $priority = $options['priority'] ?? 0.8;
        $changefreq = $options['changefreq'] ?? 'weekly';
        $lastmod = $options['lastmod'] ?? date('c');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Get GET routes
        $getRoutes = self::$routes['GET'] ?? [];

        foreach ($getRoutes as $path => $handler) {
            // Skip API routes and special routes
            if (str_starts_with($path, '/api') || 
                str_starts_with($path, '/_') ||
                $path === '/offline' ||
                str_contains($path, '{')) { // Skip routes with parameters
                continue;
            }

            $url = self::$baseUrl . $path;

            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . htmlspecialchars($lastmod) . '</lastmod>' . "\n";
            $xml .= '    <changefreq>' . htmlspecialchars($changefreq) . '</changefreq>' . "\n";
            $xml .= '    <priority>' . htmlspecialchars((string)$priority) . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        $sitemapFile = self::$publicPath . '/sitemap.xml';
        return file_put_contents($sitemapFile, $xml) !== false;
    }

    /**
     * Generate robots.txt
     */
    public static function generateRobotsTxt(array $options = []): bool
    {
        $allowAll = $options['allow_all'] ?? true;
        $sitemapUrl = $options['sitemap_url'] ?? self::$baseUrl . '/sitemap.xml';
        $disallowPaths = $options['disallow'] ?? ['/api/', '/admin/'];

        $robots = '';
        
        if ($allowAll) {
            $robots .= "User-agent: *\n";
            $robots .= "Allow: /\n";
        }

        foreach ($disallowPaths as $path) {
            $robots .= "Disallow: {$path}\n";
        }

        $robots .= "\n";
        $robots .= "Sitemap: {$sitemapUrl}\n";

        $robotsFile = self::$publicPath . '/robots.txt';
        return file_put_contents($robotsFile, $robots) !== false;
    }
}


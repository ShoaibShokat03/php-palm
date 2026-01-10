<?php

namespace Frontend\Palm;

/**
 * Progressive Resource Loader
 * 
 * World's fastest delivery system:
 * - Loads main page instantly (critical resources only)
 * - Lazy loads all non-critical resources after page load
 * - Preloads next likely pages
 * - Inlines critical CSS
 * - Defers non-critical scripts
 * - Resource hints for DNS/TCP optimization
 */
class ProgressiveResourceLoader
{
    protected static array $criticalResources = [];
    protected static array $deferredResources = [];
    protected static array $preloadResources = [];
    protected static array $prefetchResources = [];
    protected static array $dnsPrefetch = [];
    protected static array $preconnect = [];
    protected static ?string $criticalCss = null;
    protected static ?AssetDependencyTreeLoader $treeLoader = null;
    protected static bool $initialized = false;

    /**
     * Initialize progressive loading
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        // Auto-load dependency tree if report exists
        require_once __DIR__ . '/AssetDependencyTreeLoader.php';
        self::$treeLoader = new AssetDependencyTreeLoader();

        if (self::$treeLoader->hasData()) {
            self::applyDependencyTreeOptimizations();
        }
    }

    /**
     * Apply optimizations based on the dependency tree
     */
    protected static function applyDependencyTreeOptimizations(): void
    {
        if (!self::$treeLoader) {
            return;
        }

        $assetsToDefer = self::$treeLoader->getAssetsToDefer();
        foreach ($assetsToDefer as $asset) {
            self::defer($asset['url'], $asset['type']);
        }
    }

    /**
     * Add critical resource (loaded immediately in <head>)
     */
    public static function critical(string $url, string $type = 'stylesheet', array $attributes = []): void
    {
        foreach (self::$criticalResources as $res) {
            if ($res['url'] === $url) return;
        }

        self::$criticalResources[] = [
            'url' => $url,
            'type' => $type,
            'attributes' => $attributes
        ];
    }

    /**
     * Add deferred resource (loaded after page load)
     */
    public static function defer(string $url, string $type = 'script', array $attributes = []): void
    {
        foreach (self::$deferredResources as $res) {
            if ($res['url'] === $url) return;
        }

        self::$deferredResources[] = [
            'url' => $url,
            'type' => $type,
            'attributes' => $attributes
        ];
    }

    /**
     * Add preload resource (high priority, early fetch)
     */
    public static function preload(string $url, string $as, string $type = '', array $attributes = []): void
    {
        foreach (self::$preloadResources as $res) {
            if ($res['url'] === $url) return;
        }

        self::$preloadResources[] = [
            'url' => $url,
            'as' => $as,
            'type' => $type,
            'attributes' => $attributes
        ];
    }

    /**
     * Add prefetch resource (low priority, future use)
     */
    public static function prefetch(string $url, string $as = 'document'): void
    {
        foreach (self::$prefetchResources as $res) {
            if ($res['url'] === $url) return;
        }

        self::$prefetchResources[] = [
            'url' => $url,
            'as' => $as
        ];
    }

    /**
     * Add DNS prefetch hint
     */
    public static function dnsPrefetch(string $domain): void
    {
        if (!in_array($domain, self::$dnsPrefetch, true)) {
            self::$dnsPrefetch[] = $domain;
        }
    }

    /**
     * Add preconnect hint (DNS + TCP + TLS)
     */
    public static function preconnect(string $domain, bool $crossorigin = false): void
    {
        $key = $domain . ($crossorigin ? ':crossorigin' : '');
        if (!isset(self::$preconnect[$key])) {
            self::$preconnect[$key] = ['domain' => $domain, 'crossorigin' => $crossorigin];
        }
    }

    /**
     * Set critical CSS (inlined in <head>)
     */
    public static function setCriticalCss(string $css): void
    {
        self::$criticalCss = $css;
    }

    /**
     * Generate resource hints HTML (DNS, preconnect, preload, prefetch)
     */
    public static function generateResourceHints(): string
    {
        $html = '';

        // DNS prefetch (fastest, just DNS lookup)
        foreach (self::$dnsPrefetch as $domain) {
            $html .= '<link rel="dns-prefetch" href="' . htmlspecialchars($domain) . '">' . "\n    ";
        }

        // Preconnect (DNS + TCP + TLS handshake)
        foreach (self::$preconnect as $preconnect) {
            $attrs = 'href="' . htmlspecialchars($preconnect['domain']) . '"';
            if ($preconnect['crossorigin']) {
                $attrs .= ' crossorigin';
            }
            $html .= '<link rel="preconnect" ' . $attrs . '>' . "\n    ";
        }

        // Preload (high priority resources)
        foreach (self::$preloadResources as $resource) {
            $attrs = 'href="' . htmlspecialchars($resource['url']) . '" as="' . htmlspecialchars($resource['as']) . '"';
            if ($resource['type']) {
                $attrs .= ' type="' . htmlspecialchars($resource['type']) . '"';
            }
            foreach ($resource['attributes'] as $key => $value) {
                if (is_bool($value) && $value) {
                    $attrs .= ' ' . htmlspecialchars($key);
                } elseif (!is_bool($value)) {
                    $attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                }
            }
            $html .= '<link rel="preload" ' . $attrs . '>' . "\n    ";
        }

        // Prefetch (low priority, future navigation)
        foreach (self::$prefetchResources as $resource) {
            $html .= '<link rel="prefetch" href="' . htmlspecialchars($resource['url']) . '" as="' . htmlspecialchars($resource['as']) . '">' . "\n    ";
        }

        return $html;
    }

    /**
     * Generate critical CSS (inlined in <head>)
     */
    public static function generateCriticalCss(): string
    {
        if (self::$criticalCss === null) {
            return '';
        }

        return '<style id="critical-css">' . self::$criticalCss . '</style>' . "\n    ";
    }

    /**
     * Generate critical resources (loaded immediately)
     */
    public static function generateCriticalResources(): string
    {
        $html = '';

        foreach (self::$criticalResources as $resource) {
            if ($resource['type'] === 'stylesheet') {
                $attrs = 'rel="stylesheet" href="' . htmlspecialchars($resource['url']) . '"';
                foreach ($resource['attributes'] as $key => $value) {
                    if (is_bool($value) && $value) {
                        $attrs .= ' ' . htmlspecialchars($key);
                    } elseif (!is_bool($value)) {
                        $attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                    }
                }
                $html .= '<link ' . $attrs . '>' . "\n    ";
            } elseif ($resource['type'] === 'script') {
                $attrs = 'src="' . htmlspecialchars($resource['url']) . '"';
                foreach ($resource['attributes'] as $key => $value) {
                    if (is_bool($value) && $value) {
                        $attrs .= ' ' . htmlspecialchars($key);
                    } elseif (!is_bool($value)) {
                        $attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                    }
                }
                $html .= '<script ' . $attrs . '></script>' . "\n    ";
            }
        }

        return $html;
    }

    /**
     * Generate deferred resources loader script (loads after page load)
     */
    public static function generateDeferredLoader(): string
    {
        if (empty(self::$deferredResources)) {
            return '';
        }

        $resources = json_encode(self::$deferredResources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<SCRIPT
<script id="palm-deferred-loader">
(function() {
    'use strict';
    var resources = {$resources};
    var loaded = {};
    
    function loadResource(resource) {
        var key = resource.url + '|' + resource.type;
        if (loaded[key]) return;
        loaded[key] = true;
        
        if (resource.type === 'stylesheet') {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = resource.url;
            Object.keys(resource.attributes || {}).forEach(function(k) {
                if (typeof resource.attributes[k] === 'boolean' && resource.attributes[k]) {
                    link.setAttribute(k, '');
                } else if (typeof resource.attributes[k] !== 'boolean') {
                    link.setAttribute(k, resource.attributes[k]);
                }
            });
            document.head.appendChild(link);
        } else if (resource.type === 'script') {
            var script = document.createElement('script');
            script.src = resource.url;
            Object.keys(resource.attributes || {}).forEach(function(k) {
                if (typeof resource.attributes[k] === 'boolean' && resource.attributes[k]) {
                    script.setAttribute(k, '');
                } else if (typeof resource.attributes[k] !== 'boolean') {
                    script.setAttribute(k, resource.attributes[k]);
                }
            });
            document.head.appendChild(script);
        } else if (resource.type === 'image') {
            var img = new Image();
            img.src = resource.url;
        }
    }
    
    // Load when page is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                resources.forEach(loadResource);
            }, 100);
        });
    } else {
        setTimeout(function() {
            resources.forEach(loadResource);
        }, 100);
    }
    
    // Also load on idle (if browser supports)
    if ('requestIdleCallback' in window) {
        requestIdleCallback(function() {
            resources.forEach(loadResource);
        }, { timeout: 2000 });
    }
})();
</script>
SCRIPT;
    }

    /**
     * Generate lazy loading script for images
     */
    public static function generateLazyImageLoader(): string
    {
        return <<<SCRIPT
<script id="palm-lazy-images">
(function() {
    'use strict';
    if ('IntersectionObserver' in window) {
        var imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        if (img.dataset.srcset) {
                            img.srcset = img.dataset.srcset;
                        }
                        img.removeAttribute('data-src');
                        img.removeAttribute('data-srcset');
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                }
            });
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            var lazyImages = document.querySelectorAll('img[data-src]');
            lazyImages.forEach(function(img) {
                imageObserver.observe(img);
            });
        });
    } else {
        // Fallback for older browsers
        document.addEventListener('DOMContentLoaded', function() {
            var lazyImages = document.querySelectorAll('img[data-src]');
            lazyImages.forEach(function(img) {
                img.src = img.dataset.src;
                if (img.dataset.srcset) {
                    img.srcset = img.dataset.srcset;
                }
                img.removeAttribute('data-src');
                img.removeAttribute('data-srcset');
            });
        });
    }
})();
</script>
SCRIPT;
    }

    /**
     * Inject all optimizations into HTML
     */
    public static function injectOptimizations(string $html): string
    {
        if (stripos($html, '<html') === false && stripos($html, '<!doctype') === false) {
            return $html;
        }

        // Ensure initialized (which loads dependency tree optimizations)
        self::init();

        // 🖼️ AUTOMATED IMAGE OPTIMIZATION (Core Web Vitals)
        // Add decoding="async" and loading="lazy" to images that don't have them
        $html = preg_replace_callback('/<img\s+([^>]+)>/i', function ($matches) {
            $attrs = $matches[1];

            // Skip if it's already optimized or has data-src (lazy handled by JS)
            if (stripos($attrs, 'decoding=') === false) {
                $attrs .= ' decoding="async"';
            }

            // LCP Optimization: Don't lazy load the first image in <main> if it's likely above fold
            // For simplicity, we'll assume the first <img> encountered might be LCP if it doesn't have loading="lazy"
            static $imgCount = 0;
            $imgCount++;

            if ($imgCount === 1 && stripos($attrs, 'loading=') === false) {
                $attrs .= ' fetchpriority="high"';
            } elseif (stripos($attrs, 'loading=') === false && stripos($attrs, 'data-src') === false) {
                $attrs .= ' loading="lazy"';
            }

            return "<img {$attrs}>";
        }, $html);

        // 🔤 AUTOMATED FONT PRELOADING
        // Search for local fonts in CSS if we can find them in the 1000 chars of head
        if (preg_match_all('/url\([\'"]?([^\'"\)]+\.(woff2|woff|ttf))[\'"]?\)/i', substr($html, 0, 10000), $fontMatches)) {
            foreach (array_unique($fontMatches[1]) as $fontUrl) {
                self::preload($fontUrl, 'font', 'font/' . pathinfo($fontUrl, PATHINFO_EXTENSION), ['crossorigin' => true]);
            }
        }

        // ♿ AUTOMATED ACCESSIBILITY (A11y)
        // Ensure <main> has an id for skip-links if not present
        if (stripos($html, '<main') !== false && stripos($html, 'id=') === false) {
            $html = preg_replace('/<main([^>]*)>/i', '<main id="main-content"$1>', $html, 1);
        }

        // Inject resource hints in <head>
        $resourceHints = self::generateResourceHints();
        $criticalCss = self::generateCriticalCss();
        $criticalResources = self::generateCriticalResources();

        if ($resourceHints || $criticalCss || $criticalResources) {
            $injection = $resourceHints . $criticalCss . $criticalResources;

            // Inject after <head> tag
            if (preg_match('/<head[^>]*>/i', $html)) {
                $html = preg_replace('/(<head[^>]*>)/i', '$1' . "\n    " . $injection, $html, 1);
            }
        }

        // Inject deferred loader and lazy image loader before </body>
        $deferredLoader = self::generateDeferredLoader();
        $lazyImageLoader = self::generateLazyImageLoader();

        if ($deferredLoader || $lazyImageLoader) {
            $scripts = $deferredLoader . "\n    " . $lazyImageLoader;

            if (stripos($html, '</body>') !== false) {
                $html = str_ireplace('</body>', $scripts . "\n</body>", $html);
            } elseif (stripos($html, '</html>') !== false) {
                $html = str_ireplace('</html>', $scripts . "\n</html>", $html);
            } else {
                $html .= $scripts;
            }
        }

        return $html;
    }

    /**
     * Preload next likely pages (based on navigation links)
     */
    public static function preloadNextPages(array $routes): void
    {
        foreach ($routes as $route) {
            self::prefetch($route, 'document');
        }
    }

    /**
     * Clear all resources (for testing)
     */
    public static function clear(): void
    {
        self::$criticalResources = [];
        self::$deferredResources = [];
        self::$preloadResources = [];
        self::$prefetchResources = [];
        self::$dnsPrefetch = [];
        self::$preconnect = [];
        self::$criticalCss = null;
    }

    /**
     * Get all resources (for debugging)
     */
    public static function getAllResources(): array
    {
        return [
            'critical' => self::$criticalResources,
            'deferred' => self::$deferredResources,
            'preload' => self::$preloadResources,
            'prefetch' => self::$prefetchResources,
            'dns_prefetch' => self::$dnsPrefetch,
            'preconnect' => self::$preconnect,
            'critical_css' => self::$criticalCss !== null ? 'set' : null
        ];
    }
}

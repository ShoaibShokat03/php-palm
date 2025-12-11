<?php

namespace Frontend\Palm;

/**
 * PWA Generator
 * 
 * Generates Progressive Web App files (manifest.json, service worker)
 */
class PwaGenerator
{
    protected static string $publicPath = '';
    protected static array $defaultManifest = [
        'name' => 'My App',
        'short_name' => 'App',
        'description' => 'Progressive Web App',
        'start_url' => '/',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#0d6efd',
        'orientation' => 'portrait',
        'icons' => [],
    ];

    /**
     * Initialize PWA generator
     */
    public static function init(string $baseDir): void
    {
        self::$publicPath = $baseDir . '/public';
    }

    /**
     * Generate manifest.json
     */
    public static function generateManifest(array $config = []): bool
    {
        $manifest = array_merge(self::$defaultManifest, $config);
        
        // Ensure icons array exists
        if (empty($manifest['icons'])) {
            $manifest['icons'] = self::generateDefaultIcons();
        }

        $manifestFile = self::$publicPath . '/manifest.json';
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return file_put_contents($manifestFile, $json) !== false;
    }

    /**
     * Generate service worker
     */
    public static function generateServiceWorker(array $config = []): bool
    {
        $cacheName = $config['cache_name'] ?? 'palm-cache-v1';
        $precacheFiles = $config['precache'] ?? [];
        $offlinePage = $config['offline_page'] ?? '/offline.html';
        $version = $config['version'] ?? '1.0.0';

        $swContent = <<<JS
// Service Worker for PHP Palm PWA
// Generated: <?= date('Y-m-d H:i:s') ?>

const CACHE_NAME = '{$cacheName}';
const OFFLINE_PAGE = '{$offlinePage}';
const VERSION = '{$version}';

const PRECACHE_FILES = <?= json_encode($precacheFiles, JSON_PRETTY_PRINT) ?>;

// Install event - precache files
self.addEventListener('install', (event) => {
    console.log('[Service Worker] Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[Service Worker] Precaching files');
                return cache.addAll(PRECACHE_FILES);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[Service Worker] Activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[Service Worker] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Skip cross-origin requests
    if (!event.request.url.startsWith(self.location.origin)) {
        return;
    }

    event.respondWith(
        caches.match(event.request)
            .then((cachedResponse) => {
                // Return cached version if available
                if (cachedResponse) {
                    return cachedResponse;
                }

                // Fetch from network
                return fetch(event.request)
                    .then((response) => {
                        // Don't cache non-successful responses
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }

                        // Clone response for caching
                        const responseToCache = response.clone();

                        caches.open(CACHE_NAME)
                            .then((cache) => {
                                cache.put(event.request, responseToCache);
                            });

                        return response;
                    })
                    .catch(() => {
                        // Network failed, return offline page for navigation requests
                        if (event.request.mode === 'navigate') {
                            return caches.match(OFFLINE_PAGE);
                        }
                    });
            })
    );
});

// Message event - handle messages from client
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
JS;

        $swFile = self::$publicPath . '/sw.js';
        return file_put_contents($swFile, $swContent) !== false;
    }

    /**
     * Generate default icons configuration
     */
    protected static function generateDefaultIcons(): array
    {
        return [
            [
                'src' => '/icon-192x192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ],
            [
                'src' => '/icon-512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ],
        ];
    }

    /**
     * Generate PWA meta tags
     */
    public static function generateMetaTags(array $config = []): string
    {
        $manifest = array_merge(self::$defaultManifest, $config);
        
        $html = '';
        
        // Manifest link
        $html .= '<link rel="manifest" href="/manifest.json">' . "\n    ";
        
        // Theme color
        if (isset($manifest['theme_color'])) {
            $html .= '<meta name="theme-color" content="' . htmlspecialchars($manifest['theme_color']) . '">' . "\n    ";
        }
        
        // Apple touch icon
        $html .= '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n    ";
        $html .= '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n    ";
        if (isset($manifest['name'])) {
            $html .= '<meta name="apple-mobile-web-app-title" content="' . htmlspecialchars($manifest['short_name'] ?? $manifest['name']) . '">' . "\n    ";
        }
        
        // Icons
        if (!empty($manifest['icons'])) {
            foreach ($manifest['icons'] as $icon) {
                if (isset($icon['src']) && isset($icon['sizes'])) {
                    $html .= '<link rel="icon" sizes="' . htmlspecialchars($icon['sizes']) . '" href="' . htmlspecialchars($icon['src']) . '">' . "\n    ";
                }
            }
        }
        
        return rtrim($html);
    }

    /**
     * Register service worker script
     */
    public static function getServiceWorkerScript(): string
    {
        return <<<JS
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.log('Service Worker registered:', registration);
            })
            .catch((error) => {
                console.log('Service Worker registration failed:', error);
            });
    });
}
</script>
JS;
    }
}


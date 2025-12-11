<?php

namespace Frontend\Palm;

class Route
{
    protected static array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    protected static array $viewRegistry = [];
    protected static array $pathToSlug = [];
    protected static array $routeNames = []; // For route naming support

    protected static string $basePath = '';
    protected static string $currentPath = '/';
    protected static bool $spaRequest = false;
    protected static bool $routesLoaded = false;

    // Route group stack for nested groups
    protected static array $groupStack = [];
    protected static array $middlewareStack = [];

    public static function init(string $basePath): void
    {
        self::$basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        
        // Define PALM_ROOT constant for absolute path resolution
        if (!defined('PALM_ROOT')) {
            define('PALM_ROOT', realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2));
        }
        
        // Initialize route cache
        RouteCache::init(dirname($basePath));
        
        // Initialize view cache
        ViewCache::init(dirname($basePath));
        
        // Try to load cached routes
        $cachedRoutes = RouteCache::load();
        if ($cachedRoutes !== null) {
            self::$routes = $cachedRoutes;
            self::$routesLoaded = true;
        }
    }

    public static function get(string $path, callable $handler, ?string $name = null): void
    {
        self::register('GET', $path, $handler, $name);
    }

    public static function post(string $path, callable $handler, ?string $name = null): void
    {
        self::register('POST', $path, $handler, $name);
    }

    // ============================================
    // INTERNAL ROUTE CALLING (No HTTP Request)
    // ============================================

    /**
     * Call a GET route internally (without HTTP request)
     * Returns the rendered HTML from the route handler
     * 
     * Usage: Route::callGet('/about') or Route::callGet('/contact', ['name' => 'John'])
     * 
     * @param string $path Route path
     * @param array $data Query parameters to pass (merged with $_GET)
     * @return string|null The rendered HTML output, or null on error
     */
    public static function callGet(string $path, array $data = []): ?string
    {
        return self::callRoute('GET', $path, $data);
    }

    /**
     * Call a POST route internally (without HTTP request)
     * 
     * Usage: Route::callPost('/contact', ['name' => 'John', 'message' => 'Hello'])
     * 
     * @param string $path Route path
     * @param array $data Request data to send (merged with $_POST)
     * @return string|null The rendered HTML output, or null on error
     */
    public static function callPost(string $path, array $data = []): ?string
    {
        return self::callRoute('POST', $path, $data);
    }

    /**
     * Generic method to call any route internally
     * 
     * Usage: 
     *   Route::call('/about', [], 'GET')
     *   Route::call('/contact', ['name' => 'John'], 'POST')
     * 
     * @param string $path Route path
     * @param array $data Request/query data
     * @param string $method HTTP method (GET, POST)
     * @return string|null The rendered HTML output, or null on error
     */
    public static function call(string $path, array $data = [], string $method = 'GET'): ?string
    {
        return self::callRoute($method, $path, $data);
    }

    /**
     * Get view data without rendering HTML
     * Useful when you just need the data passed to a view
     * 
     * Usage: Route::getViewData('/about')
     * 
     * @param string $path Route path
     * @param array $data Query parameters
     * @return array|null View data array, or null on error
     */
    public static function getViewData(string $path, array $data = []): ?array
    {
        $normalizedPath = self::normalizePath($path);
        
        // Check if route exists and is a view route
        if (!isset(self::$routes['GET'][$normalizedPath])) {
            return null;
        }

        $handler = self::$routes['GET'][$normalizedPath];
        
        // If handler is a ViewHandler, return its data
        if ($handler instanceof ViewHandler) {
            $viewData = $handler->getData();
            // Merge with provided data
            return array_merge($viewData, $data);
        }
        
        // For other handlers, we can't easily extract data
        // Return null or try to execute and capture (not recommended)
        return null;
    }

    /**
     * Internal method to call a route without HTTP request
     * 
     * @param string $method HTTP method (GET, POST)
     * @param string $path Route path
     * @param array $data Request data (for POST) or query params (for GET)
     * @return mixed The output from the route handler (HTML string), or null on error
     */
    protected static function callRoute(string $method, string $path, array $data = []): mixed
    {
        // Ensure routes are initialized
        if (empty(self::$basePath)) {
            // Try to auto-initialize if possible
            $possibleBasePath = dirname(__DIR__, 2) . '/src';
            if (is_dir($possibleBasePath)) {
                self::init($possibleBasePath);
            } else {
                return null;
            }
        }

        // Normalize path
        $normalizedPath = self::normalizePath($path);
        
        // Check if route exists
        if (!isset(self::$routes[$method][$normalizedPath])) {
            return null;
        }

        $handler = self::$routes[$method][$normalizedPath];

        // Store original request data
        $originalPost = $_POST ?? [];
        $originalGet = $_GET ?? [];
        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $originalUri = $_SERVER['REQUEST_URI'] ?? '/';
        $originalCurrentPath = self::$currentPath;
        $originalQueryString = $_SERVER['QUERY_STRING'] ?? '';

        try {
            // Set request data
            if ($method === 'POST') {
                $_POST = array_merge($originalPost, $data);
            } else {
                $_GET = array_merge($originalGet, $data);
                // Build query string for GET requests
                if (!empty($data)) {
                    $_SERVER['QUERY_STRING'] = http_build_query($data);
                }
            }
            
            $_SERVER['REQUEST_METHOD'] = $method;
            $_SERVER['REQUEST_URI'] = $path . (!empty($data) && $method === 'GET' ? '?' . http_build_query($data) : '');
            self::$currentPath = $normalizedPath;

            // Capture all output (HTML, headers, etc.)
            ob_start();
            
            try {
                // Execute handler
                // Most handlers call Route::render() which outputs HTML directly
                $handler();
                
                // Get captured output
                $output = ob_get_clean();
                
                // Return the HTML output
                return $output ?: null;
            } catch (\Throwable $e) {
                ob_end_clean();
                error_log('Frontend route call error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                return null;
            }
        } catch (\Throwable $e) {
            error_log('Frontend route call error: ' . $e->getMessage());
            return null;
        } finally {
            // Restore original request data
            $_POST = $originalPost;
            $_GET = $originalGet;
            $_SERVER['REQUEST_METHOD'] = $originalMethod;
            $_SERVER['REQUEST_URI'] = $originalUri;
            $_SERVER['QUERY_STRING'] = $originalQueryString;
            self::$currentPath = $originalCurrentPath;
        }
    }

    protected static function register(string $method, string $path, callable $handler, ?string $name = null): void
    {
        // Apply group prefix and middleware
        $groupAttributes = self::mergeGroupAttributes();
        $prefix = $groupAttributes['prefix'] ?? '';
        $namePrefix = $groupAttributes['name'] ?? '';
        $middleware = $groupAttributes['middleware'] ?? [];

        // Build full path with prefix
        $fullPath = self::normalizePath($prefix . $path);
        
        // Build full name with prefix
        $fullName = $name;
        if ($name !== null && $namePrefix !== '') {
            $fullName = $namePrefix . $name;
        }

        // Wrap handler with middleware if any
        if (!empty($middleware)) {
            $handler = self::wrapHandlerWithMiddleware($handler, $middleware);
        }

        $normalized = self::normalizePath($fullPath);

        if ($handler instanceof ViewHandler) {
            self::$pathToSlug[$normalized] = $handler->getSlug();
            self::$viewRegistry[$handler->getSlug()] = $handler->getData();
        }

        // Store route (with middleware info only if middleware exists for backwards compatibility)
        if (!empty($middleware)) {
            self::$routes[$method][$normalized] = [
                'handler' => $handler,
                'middleware' => $middleware,
            ];
        } else {
            // Store handler directly for backwards compatibility
            self::$routes[$method][$normalized] = $handler;
        }
        
        // Store route name if provided
        if ($fullName !== null) {
            self::$routeNames[$fullName] = [
                'method' => $method,
                'path' => $normalized,
            ];
        }
    }

    /**
     * Create a route group
     * 
     * Usage:
     * Route::group(['prefix' => 'admin', 'middleware' => 'auth'], function() {
     *     Route::get('/dashboard', ...);
     * });
     */
    public static function group(array $attributes, callable $callback): void
    {
        // Push group attributes to stack
        self::$groupStack[] = $attributes;
        
        // Execute callback (routes registered inside will use these attributes)
        $callback();
        
        // Pop group attributes
        array_pop(self::$groupStack);
    }

    /**
     * Create a route group with prefix
     */
    public static function prefix(string $prefix, callable $callback): void
    {
        self::group(['prefix' => $prefix], $callback);
    }

    /**
     * Create a route group with middleware
     */
    public static function middleware(array|string $middleware, callable $callback): void
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        self::group(['middleware' => $middleware], $callback);
    }

    /**
     * Merge all group attributes from the stack
     */
    protected static function mergeGroupAttributes(): array
    {
        $attributes = [
            'prefix' => '',
            'name' => '',
            'middleware' => [],
        ];

        foreach (self::$groupStack as $group) {
            if (isset($group['prefix'])) {
                $attributes['prefix'] .= $group['prefix'];
            }
            if (isset($group['name'])) {
                $attributes['name'] .= ($attributes['name'] ? '.' : '') . $group['name'];
            }
            if (isset($group['middleware'])) {
                $middleware = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $attributes['middleware'] = array_merge($attributes['middleware'], $middleware);
            }
        }

        return $attributes;
    }

    /**
     * Wrap handler with middleware
     */
    protected static function wrapHandlerWithMiddleware(callable $handler, array $middleware): callable
    {
        // Store middleware with handler - will be executed in dispatch
        return $handler;
    }

    /**
     * Execute middleware stack
     */
    protected static function executeMiddlewareStack(array $middleware, callable $handler): callable
    {
        if (empty($middleware)) {
            return $handler;
        }

        // Build middleware chain
        $next = $handler;
        foreach (array_reverse($middleware) as $middlewareItem) {
            $middlewareInstance = self::resolveMiddleware($middlewareItem);
            if ($middlewareInstance instanceof MiddlewareInterface) {
                $currentNext = $next;
                $next = function() use ($middlewareInstance, $currentNext) {
                    return $middlewareInstance->handle($currentNext);
                };
            }
        }

        return $next;
    }

    /**
     * Resolve middleware from string or instance
     */
    protected static function resolveMiddleware(string|MiddlewareInterface $middleware): ?MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        // Try to resolve from class name
        if (class_exists($middleware) && is_subclass_of($middleware, MiddlewareInterface::class)) {
            return new $middleware();
        }

        // Try Frontend\Palm\Middleware namespace
        $middlewareClass = 'Frontend\Palm\Middleware\\' . $middleware;
        if (class_exists($middlewareClass) && is_subclass_of($middlewareClass, MiddlewareInterface::class)) {
            return new $middlewareClass();
        }

        return null;
    }

    /**
     * Register resource routes
     * 
     * Usage: Route::resource('products', ProductController::class)
     * Generates:
     * GET    /products          -> index
     * GET    /products/create   -> create
     * POST   /products          -> store
     * GET    /products/{id}     -> show
     * GET    /products/{id}/edit -> edit
     * PUT    /products/{id}     -> update
     * DELETE /products/{id}     -> destroy
     */
    public static function resource(string $name, callable|string $controller, array $options = []): void
    {
        $only = $options['only'] ?? null;
        $except = $options['except'] ?? null;
        $names = $options['names'] ?? [];

        $routes = [
            'index' => ['GET', '', 'index'],
            'create' => ['GET', '/create', 'create'],
            'store' => ['POST', '', 'store'],
            'show' => ['GET', '/{id}', 'show'],
            'edit' => ['GET', '/{id}/edit', 'edit'],
            'update' => ['PUT', '/{id}', 'update'],
            'destroy' => ['DELETE', '/{id}', 'destroy'],
        ];

        foreach ($routes as $action => $route) {
            // Skip if in except list
            if ($except !== null && in_array($action, $except, true)) {
                continue;
            }

            // Skip if not in only list
            if ($only !== null && !in_array($action, $only, true)) {
                continue;
            }

            [$method, $path, $methodName] = $route;
            $routeName = $names[$action] ?? ($name . '.' . $action);
            $routePath = '/' . $name . $path;

            // Create handler
            if (is_string($controller) && class_exists($controller)) {
                $handler = function() use ($controller, $methodName) {
                    $instance = new $controller();
                    if (method_exists($instance, $methodName)) {
                        return $instance->$methodName();
                    }
                };
            } else {
                $handler = $controller;
            }

            // Register route
            if ($method === 'GET') {
                self::get($routePath, $handler, $routeName);
            } elseif ($method === 'POST') {
                self::post($routePath, $handler, $routeName);
            } else {
                // For PUT, DELETE, etc., register as POST with method override
                self::post($routePath, function() use ($handler, $method) {
                    $_SERVER['REQUEST_METHOD'] = $method;
                    return $handler();
                }, $routeName);
            }
        }
    }

    /**
     * Get route URL by name
     */
    public static function named(string $name, array $params = []): ?string
    {
        if (!isset(self::$routeNames[$name])) {
            return null;
        }

        $route = self::$routeNames[$name];
        $path = $route['path'];

        // Replace route parameters
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string)$value, $path);
        }

        return $path;
    }

    /**
     * Compile and save routes to cache
     */
    public static function compileCache(): bool
    {
        return RouteCache::save(self::$routes);
    }

    /**
     * Get all registered routes (for debugging/listing)
     */
    public static function all(): array
    {
        return self::$routes;
    }

    /**
     * Get route names
     */
    public static function names(): array
    {
        return self::$routeNames;
    }

    /**
     * Check if routes were loaded from cache
     */
    public static function isRoutesLoaded(): bool
    {
        return self::$routesLoaded;
    }

    /**
     * Find similar routes for 404 suggestions
     */
    public static function findSimilarRoutes(string $path, string $method): array
    {
        $suggestions = [];
        $pathParts = explode('/', trim($path, '/'));
        
        // Get all routes for the method
        $routes = self::$routes[$method] ?? [];
        
        foreach ($routes as $routePath => $handler) {
            $routeParts = explode('/', trim($routePath, '/'));
            
            // Calculate similarity (simple Levenshtein-like)
            $similarity = 0;
            
            // Check if route starts with same path
            if (count($routeParts) > 0 && count($pathParts) > 0) {
                if ($routeParts[0] === $pathParts[0]) {
                    $similarity += 3;
                }
            }
            
            // Check path length similarity
            $lengthDiff = abs(count($routeParts) - count($pathParts));
            if ($lengthDiff <= 1) {
                $similarity += 2;
            }
            
            // Check if any parts match
            foreach ($pathParts as $part) {
                if (in_array($part, $routeParts)) {
                    $similarity += 1;
                }
            }
            
            if ($similarity > 0) {
                $suggestions[] = [
                    'path' => $routePath,
                    'similarity' => $similarity,
                ];
            }
        }
        
        // Sort by similarity (highest first)
        usort($suggestions, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        
        // Return top 5 suggestions
        return array_slice($suggestions, 0, 5);
    }

    public static function dispatch(string $method, string $uri): void
    {
        // SPA request handling removed
        self::$spaRequest = false;

        $parsedUri = parse_url($uri);
        $path = self::normalizePath($parsedUri['path'] ?? '/');
        self::$currentPath = $path;

        // Parse query parameters and make them available
        if (isset($parsedUri['query'])) {
            parse_str($parsedUri['query'], $queryParams);
            $_GET = array_merge($_GET ?? [], $queryParams);
        }

        $route = self::$routes[$method][$path] ?? null;

        if ($route === null) {
            http_response_code(404);
            
            // Find similar routes for suggestion
            $suggestions = self::findSimilarRoutes($path, $method);
            
            self::render('home.home', [
                'title' => '404 - Page Not Found',
                'message' => 'No route defined for ' . htmlspecialchars($path),
                'suggested_routes' => $suggestions,
                'all_routes' => self::$routes,
            ]);
            return;
        }

        // Handle both old format (direct handler) and new format (array with handler/middleware)
        if (is_array($route) && isset($route['handler'])) {
            $handler = $route['handler'];
            $middleware = $route['middleware'] ?? [];
            // Execute middleware stack
            $finalHandler = self::executeMiddlewareStack($middleware, $handler);
            $finalHandler();
        } else {
            // Old format - direct handler
            $route();
        }
    }

    public static function view(string $slug, array $data = [], ?string $layout = null): ViewHandler
    {
        return new ViewHandler($slug, $data, $layout);
    }

    public static function render(string $slug, array $data = [], ?string $layout = null): void
    {
        $layoutIdentifier = $layout ?? ($data['layout'] ?? null);
        if (array_key_exists('layout', $data)) {
            unset($data['layout']);
        }

        self::$pathToSlug[self::$currentPath] = $slug;
        self::$viewRegistry[$slug] = $data + (self::$viewRegistry[$slug] ?? []);

        $base = self::$basePath;
        
        // Try .palm.php first, then .php
        $viewBasePath = $base . '/views/' . str_replace('.', '/', $slug);
        $viewPath = null;
        $isPalmFile = false;
        
        $palmPath = $viewBasePath . '.palm.php';
        $phpPath = $viewBasePath . '.php';
        
        if (file_exists($palmPath)) {
            $viewPath = $palmPath;
            $isPalmFile = true;
        } elseif (file_exists($phpPath)) {
            $viewPath = $phpPath;
            $isPalmFile = false;
        }
        
        if ($viewPath === null || !file_exists($viewPath)) {
            http_response_code(404);
            echo "<h1>View not found</h1><p>" . htmlspecialchars($slug) . "</p>";
            return;
        }

        // Render the view file - no compilation, just require directly
            // Ensure PALM_ROOT is defined
            if (!defined('PALM_ROOT')) {
                define('PALM_ROOT', realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2));
            }
            // Load helpers.php before including view
            require_once PALM_ROOT . '/app/Palm/helpers.php';
            extract($data);
        ob_start();
            require $viewPath;
        $content = ob_get_clean();
        $currentComponent = null;
        $currentScripts = [];
        $title = $data['title'] ?? self::humanizeSlug($slug);
        $meta = $data['meta'] ?? [];
        $currentPath = self::$currentPath;
        $currentSlug = $slug;
        // SPA views preloading removed - no longer needed
        $clientViews = [];
        $routeMap = [];

        // No compilation - components work directly from .palm.php files

        extract($data);

        $initialScripts = $currentScripts;
        $layoutPath = self::resolveLayoutPath($layoutIdentifier);
        
        // No compilation - just require layout directly
        // Ensure PALM_ROOT is defined
        if (!defined('PALM_ROOT')) {
            define('PALM_ROOT', realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2));
        }
        // Load helpers.php before including layout
        require_once PALM_ROOT . '/app/Palm/helpers.php';
        require $layoutPath;

        // Automatically inject scripts after layout is included
        self::outputLayoutScripts($clientViews, $currentSlug, $routeMap);
    }

    public static function currentPath(): string
    {
        return self::$currentPath;
    }

    protected static function exportClientViews(string $currentSlug, array $currentData, ?string $currentHtml = null, ?array $currentComponent = null, array $currentScripts = []): array
    {
        $views = [];
        
        // Use current view data if available (already rendered)
        if ($currentSlug && $currentHtml !== null) {
            $views[$currentSlug] = self::buildPayloadFromHtml($currentSlug, $currentData, $currentHtml, $currentComponent, $currentScripts);
        }
        
        // Preload other views for SPA navigation (lazy - only render on demand)
        // For now, we'll only preload views that are already in the registry
        // This can be optimized further with lazy loading
        foreach (self::$viewRegistry as $slug => $data) {
            if ($slug === $currentSlug) {
                // Already handled above
                continue;
            }

            // Only preload if view is simple (no complex dependencies)
            // For complex views, we can lazy load them on first navigation
            try {
                $views[$slug] = self::renderFragmentPayload($slug, $data);
            } catch (\Throwable $e) {
                // If preloading fails, create a placeholder that will be loaded on demand
                error_log("Palm: Failed to preload view {$slug}: " . $e->getMessage());
                $views[$slug] = [
                    'title' => self::humanizeSlug($slug),
                    'meta' => [],
                    'html' => '<div class="card"><p>Loading...</p></div>',
                    'state' => [],
                    'component' => null,
                    'scripts' => [],
                    '_lazy' => true, // Mark for lazy loading
                ];
            }
        }
        
        return $views;
    }
    
    /**
     * Build route map with query parameter support
     */
    protected static function buildRouteMap(): array
    {
        $routeMap = [];
        foreach (self::$pathToSlug as $path => $slug) {
            // Base path mapping (path without query)
            $routeMap[$path] = $slug;
        }
        return $routeMap;
    }

    protected static function buildPayloadFromHtml(string $slug, array $data, string $html, ?array $component = null, array $scripts = []): array
    {
        // Extract inline script tags from HTML to prevent JSON syntax errors
        $extractedScripts = [];
        $htmlWithoutScripts = preg_replace_callback(
            '/<script\b[^>]*>([\s\S]*?)<\/script>/i',
            function ($matches) use (&$extractedScripts) {
                $extractedScripts[] = [
                    'inline' => true,
                    'content' => $matches[1],
                    'attributes' => $matches[0] ? self::extractScriptAttributes($matches[0]) : [],
                ];
                // Replace with placeholder comment
                return '<!-- script extracted -->';
            },
            $html
        );

        return [
            'title' => $data['title'] ?? self::humanizeSlug($slug),
            'meta' => $data['meta'] ?? [],
            'html' => $htmlWithoutScripts,
            'extractedScripts' => $extractedScripts,
            'state' => $data['state'] ?? [],
            'component' => $component,
            'scripts' => array_merge($scripts, $extractedScripts),
        ];
    }

    protected static function extractScriptAttributes(string $scriptTag): array
    {
        $attrs = [];
        if (preg_match_all('/(\w+)=["\']([^"\']*)["\']/', $scriptTag, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrs[$match[1]] = $match[2];
            }
        }
        return $attrs;
    }

    protected static function renderFragmentPayload(string $slug, array $data): array
    {
        $base = self::$basePath;
        $viewBasePath = $base . '/views/' . str_replace('.', '/', $slug);
        
        // Check for .palm.php file first, then .php
        $viewPath = $viewBasePath . '.palm.php';
        $isPalmFile = file_exists($viewPath);
        
        if (!$isPalmFile) {
            $viewPath = $viewBasePath . '.php';
        }

        if (!file_exists($viewPath)) {
            return [
                'title' => self::humanizeSlug($slug),
                'meta' => [],
                'html' => "<div class=\"card\"><h2>Missing view</h2><p>" . htmlspecialchars($slug) . "</p></div>",
                'state' => [],
                'component' => null,
                'scripts' => [],
            ];
        }

        $title = $data['title'] ?? self::humanizeSlug($slug);
        $meta = $data['meta'] ?? [];
        
        try {
            // No compilation - just require view directly
                // Ensure PALM_ROOT is defined
                if (!defined('PALM_ROOT')) {
                    define('PALM_ROOT', realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2));
                }
                // Load helpers.php before including view
                require_once PALM_ROOT . '/app/Palm/helpers.php';
                extract($data);
            ob_start();
                require $viewPath;
            $html = ob_get_clean();

            return [
                'title' => $title,
                'meta' => $meta,
                'html' => $html,
                'state' => [],
                'component' => null,
                'scripts' => [],
            ];
        } catch (\Throwable $e) {
            error_log("Palm: Error rendering fragment payload for {$slug}: " . $e->getMessage());
            return [
                'title' => $title,
                'meta' => $meta,
                'html' => "<div class=\"card\"><h2>Error</h2><p>Failed to render view: " . htmlspecialchars($e->getMessage()) . "</p></div>",
                'state' => [],
                'component' => null,
                'scripts' => [],
            ];
        }
    }

    protected static function normalizePath(?string $path): string
    {
        if ($path === null || $path === '' || $path === false) {
            return '/';
        }

        // Remove query string and fragment for route matching
        $path = parse_url($path, PHP_URL_PATH) ?? $path;
        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        return $path;
    }
    
    /**
     * Get full path with query string for SPA navigation
     */
    protected static function getFullPath(string $path): string
    {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        if ($queryString) {
            return $path . '?' . $queryString;
        }
        return $path;
    }

    protected static function humanizeSlug(string $slug): string
    {
        $parts = explode('.', $slug);
        $parts = array_map(fn($part) => ucfirst($part), $parts);
        return implode(' · ', $parts);
    }

    protected static function resolveLayoutPath(?string $layoutIdentifier): string
    {
        $base = self::$basePath . '/layouts';
        
        // Try .palm.php first, then .php
        $fallbackPalm = $base . '/main.palm.php';
        $fallbackPhp = $base . '/main.php';
        $fallback = file_exists($fallbackPalm) ? $fallbackPalm : $fallbackPhp;

        if ($layoutIdentifier === null) {
            return $fallback;
        }

        $normalized = trim($layoutIdentifier);
        if ($normalized === '') {
            return $fallback;
        }

        if (str_starts_with($normalized, 'layout.')) {
            $normalized = substr($normalized, 7);
        } elseif (str_starts_with($normalized, 'layouts.')) {
            $normalized = substr($normalized, 8);
        }

        $normalized = str_replace('\\', '/', $normalized);
        $normalized = str_replace('..', '', $normalized);
        $normalized = trim($normalized, '/');

        $path = $base . '/' . str_replace('.', '/', $normalized);
        
        // Try .palm.php first, then .php
        $palmPath = $path . '.palm.php';
        $phpPath = $path . '.php';
        
        if (file_exists($palmPath)) {
            return $palmPath;
        }
        
        if (file_exists($phpPath)) {
            return $phpPath;
        }

        return $fallback;
    }

    // Component compilation removed - components work directly from .palm.php files

    protected static function outputLayoutScripts(array $clientViews, ?string $currentSlug, array $routeMap): void
    {
        $currentViewKey = $currentSlug ?? null;
        $bootComponent = ($currentViewKey && isset($clientViews[$currentViewKey]['component']))
            ? $clientViews[$currentViewKey]['component']
            : null;
        $bootComponents = $bootComponent ? [$bootComponent] : [];
        
        // Output all scripts directly from core (no frontend includes)
        self::outputScripts($clientViews, $currentSlug, $routeMap, $bootComponent, $bootComponents);
    }
    
    protected static function outputScripts(array $clientViews, ?string $currentSlug, array $routeMap, ?array $bootComponent, array $bootComponents): void
    {
        $currentViewKey = $currentSlug ?? null;
        $currentViewScripts = ($currentViewKey && isset($clientViews[$currentViewKey]['extractedScripts']))
            ? $clientViews[$currentViewKey]['extractedScripts']
            : [];

        // SPA functionality removed - no SPA attributes needed

        // Component state for hydration (no compilation needed)
        if ($bootComponent) {
            echo '<script type="application/json" data-psr-state>' . PHP_EOL;
            echo json_encode($bootComponent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            echo PHP_EOL . '</script>' . PHP_EOL;
        }

        // Re-inject extracted scripts from views
        foreach ($currentViewScripts as $script) {
            $attributes = '';
            if (!empty($script['attributes'])) {
                $attrs = [];
                foreach ($script['attributes'] as $key => $value) {
                    $attrs[] = $key . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                }
                $attributes = $attrs ? ' ' . implode(' ', $attrs) : '';
            }
            $content = $script['content'] ?? '';
            echo '<script' . $attributes . '>' . $content . '</script>' . PHP_EOL;
        }

        // SPA functionality removed - only hydration needed for component reactivity
    }
}

class ViewHandler
{
    public function __construct(
        protected string $slug,
        protected array $data = [],
        protected ?string $layout = null
    ) {
    }

    public function __invoke(): void
    {
        Route::render($this->slug, $this->data, $this->layout);
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function layout(string $identifier): self
    {
        $this->layout = $identifier;
        return $this;
    }

    public function getLayout(): ?string
    {
        return $this->layout;
    }

    /**
     * Required for var_export() serialization in route cache
     */
    public static function __set_state(array $properties): self
    {
        return new self(
            $properties['slug'] ?? '',
            $properties['data'] ?? [],
            $properties['layout'] ?? null
        );
    }
}


<?php

namespace PhpPalm\Core;

class Router
{
    protected array $routes = [];
    protected array $namedRoutes = []; // Named routes for URL generation
    protected static string $currentSource = 'api.php';

    // Optimized route lookup: method => [exact_path => route, ...]
    protected array $routeIndex = [];
    // Dynamic routes (with parameters) grouped by method
    protected array $dynamicRoutes = [];

    /**
     * Add route with optimized indexing for fast exact matches
     * Exact routes use O(1) array lookup, dynamic routes use pattern matching
     * 
     * @param string $method HTTP method
     * @param string $path Route path
     * @param callable|array $handler Route handler
     * @param string|null $source Route source (for conflict detection)
     * @param string|null $name Route name (for URL generation)
     */
    public function add(string $method, string $path, callable|array $handler, ?string $source = null, ?string $name = null): void
    {
        $method = strtoupper($method);
        $route = [
            'method'  => $method,
            'path'    => $this->convertPath($path),
            'handler' => $handler,
            'raw'     => $path,
            'source'  => $source ?? self::$currentSource
        ];

        $this->routes[] = $route;

        // Build optimized index for O(1) exact match lookup
        $normalizedPath = $this->normalizePath($path);
        $hasParams = strpos($path, '{') !== false;

        if (!$hasParams) {
            // Exact match route - stored in array for instant O(1) lookup
            // No loops needed during route matching
            if (!isset($this->routeIndex[$method])) {
                $this->routeIndex[$method] = [];
            }
            $this->routeIndex[$method][$normalizedPath] = $route;
        } else {
            // Dynamic route with parameters - stored separately for pattern matching
            // Only checked if exact match fails
            if (!isset($this->dynamicRoutes[$method])) {
                $this->dynamicRoutes[$method] = [];
            }
            $this->dynamicRoutes[$method][] = $route;
        }

        // Store named route for URL generation
        if ($name !== null) {
            $this->namedRoutes[$name] = $route;
        }
    }

    /**
     * Normalize path for indexing (remove trailing slash, lowercase)
     */
    private function normalizePath(string $path): string
    {
        $path = rtrim($path, '/');
        return empty($path) ? '/' : $path;
    }

    /**
     * Generate URL from named route
     */
    public function url(string $name, array $params = []): ?string
    {
        if (!isset($this->namedRoutes[$name])) {
            return null;
        }

        $route = $this->namedRoutes[$name];
        $path = $route['raw'];

        // Replace parameters
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }

        return $path;
    }

    /**
     * Get named route
     */
    public function getNamedRoute(string $name): ?array
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Set the current source for route registration
     */
    public static function setSource(string $source): void
    {
        self::$currentSource = $source;
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    private function br()
    {
        echo "</br>";
    }
    /**
     * Pre-compute target route path (called once, reused)
     */
    public function prepareRoutePath(string $uri): string
    {
        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        $targetRoute = str_replace($basePath . "/api", '', $uri);
        return $this->normalizePath($targetRoute);
    }

    /**
     * Fast route lookup with O(1) exact match - optimized for speed
     * Uses array indexing for exact matches (no loops)
     * Only loops through dynamic routes if exact match fails
     * 
     * @param string $method HTTP method
     * @param string $targetRoute Normalized route path
     * @return array|null Route info with handler and params, or null if not found
     */
    public function findRoute(string $method, string $targetRoute): ?array
    {
        $method = strtoupper($method);

        // Fast path: Direct O(1) array lookup for exact matches
        // No loops, no iteration - instant hash map access
        if (isset($this->routeIndex[$method]) && isset($this->routeIndex[$method][$targetRoute])) {
            return [
                'route' => $this->routeIndex[$method][$targetRoute],
                'params' => []
            ];
        }

        // Fallback: Try dynamic routes (only if no exact match found)
        // This is the only place we use a loop, and only for parameterized routes
        if (isset($this->dynamicRoutes[$method])) {
            foreach ($this->dynamicRoutes[$method] as $route) {
                if (preg_match($route['path'], $targetRoute, $matches)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    return [
                        'route' => $route,
                        'params' => $params
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Fast route dispatch - optimized for exact matches
     * Uses O(1) array lookup for exact routes, no loops
     * 
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @return array Response data
     */
    public function dispatch(string $method, string $uri)
    {
        try {
            // Pre-compute route path once (normalized)
            $targetRoute = $this->prepareRoutePath($uri);

            // Fast O(1) route lookup - direct array access, no loops for exact matches
            $routeInfo = $this->findRoute($method, $targetRoute);

            if ($routeInfo !== null) {
                // Execute route immediately - no iteration needed
                return $this->executeRoute($routeInfo['route'], $routeInfo['params']);
            }

            // Route not found - return 404
            http_response_code(404);
            return [
                'status' => 'error',
                'message' => 'Route not found',
                'uri' => $targetRoute,
                'method' => strtoupper($method),
                'available_routes' => $this->getAvailableRoutes(strtoupper($method))
            ];
        } catch (\Throwable $e) {
            http_response_code(500);
            return [
                'status' => 'error',
                'message' => 'Router dispatch error',
                'error_detail' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }
    }

    /**
     * Execute route handler (public for direct access)
     */
    public function executeRoute(array $route, array $params): array
    {
        try {
            $handler = $route['handler'];

            // Handle array callables like [ClassName::class, 'method']
            if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
                // Instantiate the class and create a callable with the instance
                $className = $handler[0];
                $methodName = $handler[1];
                $instance = new $className();
                $handler = [$instance, $methodName];
            }

            $result = call_user_func_array($handler, $params);

            // Ensure result is an array
            if (!is_array($result)) {
                return ['status' => 'success', 'data' => $result];
            }

            return $result;
        } catch (\Throwable $e) {
            // Log error details for debugging
            error_log('Route handler error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            http_response_code(500);
            return [
                'status' => 'error',
                'message' => 'Handler execution error',
                'error_detail' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }
    }

    /**
     * Get available routes for debugging
     * Separates simple routes and module routes grouped by module
     */
    private function getAvailableRoutes(string $method): array
    {
        $simpleRoutes = [];
        $moduleRoutes = [];

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            $source = $route['source'] ?? 'api.php';

            // Check if it's a simple route (from api.php)
            if ($source === 'api.php') {
                $simpleRoutes[] = $route['raw'];
            }
            // Check if it's a module route (source format: "module:ModuleName")
            elseif (strpos($source, 'module:') === 0) {
                $moduleName = substr($source, 7); // Remove "module:" prefix
                if (!isset($moduleRoutes[$moduleName])) {
                    $moduleRoutes[$moduleName] = [];
                }
                $moduleRoutes[$moduleName][] = $route['raw'];
            }
            // Fallback for any other source types
            else {
                if (!isset($moduleRoutes[$source])) {
                    $moduleRoutes[$source] = [];
                }
                $moduleRoutes[$source][] = $route['raw'];
            }
        }

        // Build structured response
        $result = [];

        if (!empty($simpleRoutes)) {
            $result['simple_routes'] = $simpleRoutes;
        }

        if (!empty($moduleRoutes)) {
            $result['module_routes'] = $moduleRoutes;
        }

        return $result;
    }


    private function convertPath(string $path): string
    {
        // Normalize path - remove trailing slash for matching
        $path = rtrim($path, '/');
        if (empty($path)) {
            $path = '/';
        }

        // Escape the path for regex using # delimiter
        $pattern = preg_quote($path, '#');

        // Convert route params like \{id\} (escaped by preg_quote) to named capture groups
        // Matches \{identifier\} -> (?P<identifier>[^/]+)
        $pattern = preg_replace('#\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}#', '(?P<\1>[^/]+)', $pattern);

        return '#^' . $pattern . '$#';
    }
}

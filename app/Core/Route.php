<?php

namespace PhpPalm\Core;

use PhpPalm\Core\Router;

class Route
{
    protected static ?Router $router = null;
    protected static array $groupStack = [];

    public static function init(): void
    {
        if (self::$router === null) {
            self::$router = new Router();
        }
    }

    public static function group(string $prefix, callable $callback): void
    {
        self::$groupStack[] = $prefix;
        $callback();
        array_pop(self::$groupStack);
    }

    protected static function getGroupPrefix(): string
    {
        return implode('', self::$groupStack);
    }

    protected static function applyPrefix(string $path): string
    {
        $prefix = self::getGroupPrefix();
        if (empty($prefix)) {
            return $path;
        }

        // Ensure standard / separator
        $path = $prefix . '/' . ltrim($path, '/');
        // Remove double slashes
        return preg_replace('#/+#', '/', $path);
    }

    public static function get(string $path, callable|array $handler, ?string $name = null): void
    {
        self::init();
        self::$router->add('GET', self::applyPrefix($path), $handler, null, $name);
    }

    public static function post(string $path, callable|array $handler, ?string $name = null): void
    {
        self::init();
        self::$router->add('POST', self::applyPrefix($path), $handler, null, $name);
    }

    public static function put(string $path, callable|array $handler, ?string $name = null): void
    {
        self::init();
        self::$router->add('PUT', self::applyPrefix($path), $handler, null, $name);
    }

    public static function delete(string $path, callable|array $handler, ?string $name = null): void
    {
        self::init();
        self::$router->add('DELETE', self::applyPrefix($path), $handler, null, $name);
    }

    public static function patch(string $path, callable|array $handler, ?string $name = null): void
    {
        self::init();
        self::$router->add('PATCH', self::applyPrefix($path), $handler, null, $name);
    }

    /**
     * Generate URL from named route
     */
    public static function url(string $name, array $params = []): ?string
    {
        self::init();
        return self::$router->url($name, $params);
    }


    /**
     * Set the source for route registration (used for conflict detection)
     */
    public static function setSource(string $source): void
    {
        self::init();
        Router::setSource($source);
    }

    /**
     * Get the router instance (for conflict checking)
     */
    public static function getRouter(): ?Router
    {
        self::init();
        return self::$router;
    }

    public static function dispatch()
    {
        try {
            self::init();
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

            if (empty($uri)) {
                $uri = '/';
            }

            return self::$router->dispatch($method, $uri);
        } catch (\Throwable $e) {
            http_response_code(500);
            return [
                'status' => 'error',
                'message' => 'Route dispatch error',
                'error_detail' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }
    }
}

<?php

namespace PhpPalm\Core;

use PhpPalm\Core\Router;

class Route
{
    protected static ?Router $router = null;

    public static function init(): void
    {
        if (self::$router === null) {
            self::$router = new Router();
        }
    }

    public static function get(string $path, callable|array $handler, ?string $name = null): void
    {
        self::init();
        self::$router->add('GET', $path, $handler, null, $name);
    }

    public static function post(string $path, callable|array $handler, ?string $name = null): void
    {
        self::init();
        self::$router->add('POST', $path, $handler, null, $name);
    }

    public static function put(string $path, callable|array $handler, ?string $name = null): void
    {
        self::init();
        self::$router->add('PUT', $path, $handler, null, $name);
    }

    public static function delete(string $path, callable|array $handler, ?string $name = null): void
    {
        self::init();
        self::$router->add('DELETE', $path, $handler, null, $name);
    }

    public static function patch(string $path, callable|array $handler, ?string $name = null): void
    {
        self::init();
        self::$router->add('PATCH', $path, $handler, null, $name);
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


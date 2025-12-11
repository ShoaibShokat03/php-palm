<?php

namespace App\Core;

/**
 * Middleware Helper
 * Easy-to-use static methods for applying middlewares
 * 
 * This is the framework core - keep it minimal and stable
 * Developers use this to easily apply their custom middlewares
 */
class MiddlewareHelper
{
    protected static ?MiddlewareLoader $loader = null;

    /**
     * Get middleware loader instance
     */
    protected static function getLoader(): MiddlewareLoader
    {
        if (self::$loader === null) {
            self::$loader = new MiddlewareLoader();
            self::$loader->loadMiddlewares();
        }
        return self::$loader;
    }

    /**
     * Use a middleware by name
     * 
     * Usage:
     * Route::get('/path', MiddlewareHelper::use('AuthMiddleware', [$controller, 'method']));
     * 
     * @param string $name Middleware name (filename without .php)
     * @param mixed ...$args Middleware constructor args (optional) and handler
     * @return callable
     */
    public static function use(string $name, ...$args): callable
    {
        $loader = self::getLoader();
        
        if (!$loader->has($name)) {
            throw new \Exception("Middleware '{$name}' not found. Make sure it exists in the middlewares/ directory.");
        }

        $middleware = $loader->get($name);
        
        // If last argument is callable, it's the handler
        // Everything before it are middleware constructor arguments
        $handler = null;
        $constructorArgs = [];
        
        foreach ($args as $arg) {
            if (is_callable($arg)) {
                $handler = $arg;
                break;
            }
            $constructorArgs[] = $arg;
        }

        if ($handler === null) {
            throw new \Exception("No handler provided for middleware '{$name}'");
        }

        // If middleware needs constructor arguments, create new instance
        if (!empty($constructorArgs)) {
            $middlewareClass = get_class($middleware);
            $middleware = new $middlewareClass(...$constructorArgs);
        }

        // Return wrapped handler
        return function (...$routeArgs) use ($middleware, $handler) {
            return $middleware->handle($handler, ...$routeArgs);
        };
    }

    /**
     * Alias for use() - shorter syntax
     */
    public static function apply(string $name, ...$args): callable
    {
        return self::use($name, ...$args);
    }
}


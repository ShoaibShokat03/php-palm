<?php

namespace App\Core;

/**
 * Base Middleware Class
 * All custom middlewares should extend this class
 * 
 * This is the framework core - keep it minimal and stable
 * Developers create their own middlewares in the root/middlewares/ directory
 */
abstract class Middleware
{
    /**
     * Handle the request
     * Override this method in your middleware
     * 
     * @param callable $handler The route handler to wrap
     * @param mixed ...$args Route parameters
     * @return mixed
     */
    abstract public function handle(callable $handler, ...$args);

    /**
     * Helper method to wrap a handler
     * Usage: return $this->wrap($handler, ...$args);
     */
    protected function wrap(callable $handler, ...$args)
    {
        return call_user_func_array($handler, $args);
    }

    /**
     * Helper method to return an error response
     */
    protected function error(string $message, int $statusCode = 400, array $errors = []): array
    {
        http_response_code($statusCode);
        return [
            'status' => 'error',
            'message' => $message,
            'errors' => $errors
        ];
    }

    /**
     * Helper method to return a success response
     */
    protected function success(array $data = [], string $message = 'Success', int $statusCode = 200): array
    {
        http_response_code($statusCode);
        return [
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ];
    }
}


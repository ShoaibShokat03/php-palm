<?php

namespace App\Core;

/**
 * Middleware Loader
 * Automatically discovers and loads all middlewares from the root/middlewares/ directory
 * 
 * This is the framework core - keep it minimal and stable
 */
class MiddlewareLoader
{
    protected array $middlewares = [];
    protected string $middlewaresPath;

    public function __construct(string $middlewaresPath = null)
    {
        // Point to middlewares folder in root directory (outside /app)
        $this->middlewaresPath = $middlewaresPath ?? dirname(__DIR__, 2) . '/middlewares';
    }

    /**
     * Load all middlewares from the middlewares directory
     */
    public function loadMiddlewares(): void
    {
        if (!is_dir($this->middlewaresPath)) {
            // Create directory if it doesn't exist
            @mkdir($this->middlewaresPath, 0755, true);
            return;
        }

        $files = glob($this->middlewaresPath . '/*.php');

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);

            if ($className && class_exists($className)) {
                $middleware = Container::getInstance()->make($className);
                if ($middleware instanceof Middleware) {
                    $name = $this->getMiddlewareName($file);
                    $this->middlewares[$name] = $middleware;
                }
            }
        }
    }

    /**
     * Get middleware by name
     */
    public function get(string $name): ?Middleware
    {
        return $this->middlewares[$name] ?? null;
    }

    /**
     * Get all loaded middlewares
     */
    public function all(): array
    {
        return $this->middlewares;
    }

    /**
     * Check if middleware exists
     */
    public function has(string $name): bool
    {
        return isset($this->middlewares[$name]);
    }

    /**
     * Get class name from file
     */
    protected function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        if (!$content) {
            return null;
        }

        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        // Extract class name
        $className = null;
        if (preg_match('/class\s+(\w+)\s+extends\s+Middleware/', $content, $matches)) {
            $className = $matches[1];
        }

        if ($className) {
            return $namespace ? $namespace . '\\' . $className : $className;
        }

        return null;
    }

    /**
     * Get middleware name from file (filename without .php)
     */
    protected function getMiddlewareName(string $file): string
    {
        return basename($file, '.php');
    }
}

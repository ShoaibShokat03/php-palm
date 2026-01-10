<?php

namespace App\Core;

/**
 * Dependency Injection Container
 * 
 * Features:
 * - Auto-resolving dependencies via type-hinting
 * - Singleton / scoped services
 * - Service binding and resolution
 * - Memory efficient with lazy loading
 */
class Container
{
    protected static Container $instance;
    protected array $bindings = [];
    protected array $singletons = [];
    protected array $resolved = [];

    /**
     * Get container instance (singleton)
     */
    public static function getInstance(): Container
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Bind a service
     * 
     * @param string $abstract Class name or interface
     * @param callable|string|null $concrete Factory function or class name
     * @param bool $singleton Whether to treat as singleton
     */
    public function bind(string $abstract, $concrete = null, bool $singleton = false): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'singleton' => $singleton
        ];
    }

    /**
     * Bind as singleton
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Resolve a service
     * 
     * @param string $abstract Class name or interface
     * @param array $parameters Optional parameters to pass to constructor
     * @return mixed Resolved instance
     */
    public function make(string $abstract, array $parameters = [])
    {
        // Check if already resolved as singleton
        if (isset($this->singletons[$abstract])) {
            return $this->singletons[$abstract];
        }

        // Check if already resolved in this request
        if (empty($parameters) && isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        // Get binding
        $binding = $this->bindings[$abstract] ?? null;

        if ($binding) {
            $concrete = $binding['concrete'];
            $isSingleton = $binding['singleton'] ?? false;
        } else {
            $concrete = $abstract;
            $isSingleton = false;
        }

        // Resolve instance
        try {
            $instance = $this->build($concrete, $parameters);

            // Store if singleton
            if ($isSingleton) {
                $this->singletons[$abstract] = $instance;
            } elseif (empty($parameters)) {
                $this->resolved[$abstract] = $instance;
            }

            return $instance;
        } catch (\Throwable $e) {
            if ($binding) {
                throw new \Exception("Target [$abstract] is not instantiable while building [" . (is_string($concrete) ? $concrete : 'Closure') . "].", 0, $e);
            }
            throw $e;
        }
    }

    /**
     * Build instance with auto-resolving dependencies
     * 
     * @param mixed $concrete
     * @param array $parameters
     * @return mixed
     */
    protected function build($concrete, array $parameters = [])
    {
        // If it's a callable (and not a string class name), execute it
        if ($concrete instanceof \Closure || (is_callable($concrete) && !is_string($concrete))) {
            return $concrete($this, $parameters);
        }

        // If it's not a class, return as-is
        if (!is_string($concrete) || !class_exists($concrete)) {
            return $concrete;
        }

        // Use reflection to auto-resolve dependencies
        try {
            $reflection = new \ReflectionClass($concrete);

            // Check if class is instantiable
            if (!$reflection->isInstantiable()) {
                throw new \Exception("Class {$concrete} is not instantiable");
            }

            // Get constructor
            $constructor = $reflection->getConstructor();

            // If no constructor, instantiate directly
            if ($constructor === null) {
                return new $concrete();
            }

            // Resolve constructor parameters
            $dependencies = $this->resolveMethodDependencies($constructor, $parameters);

            return $reflection->newInstanceArgs($dependencies);
        } catch (\ReflectionException $e) {
            throw new \Exception("Failed to build {$concrete}: " . $e->getMessage());
        }
    }

    /**
     * Call a method and resolve its dependencies
     * 
     * @param callable|string $callback
     * @param array $parameters
     * @return mixed
     */
    public function call($callback, array $parameters = [])
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            $parts = explode('@', $callback);
            $callback = [$this->make($parts[0]), $parts[1]];
        }

        try {
            if (is_array($callback)) {
                $reflection = new \ReflectionMethod($callback[0], $callback[1]);
            } else {
                $reflection = new \ReflectionFunction($callback);
            }

            $dependencies = $this->resolveMethodDependencies($reflection, $parameters);

            return call_user_func_array($callback, $dependencies);
        } catch (\ReflectionException $e) {
            throw new \Exception("Failed to call method: " . $e->getMessage());
        }
    }

    /**
     * Resolve dependencies for a method or function
     * 
     * @param \ReflectionFunctionAbstract $reflection
     * @param array $parameters
     * @return array
     */
    protected function resolveMethodDependencies(\ReflectionFunctionAbstract $reflection, array $parameters = []): array
    {
        $dependencies = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            // Check if provided in parameters array
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                // Class type hint - auto-resolve via container
                $dependencies[] = $this->make($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                // No type hint or built-in type, use default if available
                $dependencies[] = $parameter->getDefaultValue();
            } elseif ($parameter->isOptional()) {
                $dependencies[] = null;
            } else {
                throw new \Exception("Cannot resolve parameter [{$name}] for [" . $reflection->getName() . "]");
            }
        }

        return $dependencies;
    }

    /**
     * Check if service is bound
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }

    /**
     * Clear resolved instances (for testing)
     */
    public function clear(): void
    {
        $this->resolved = [];
        $this->singletons = [];
    }

    /**
     * Get all bindings
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}

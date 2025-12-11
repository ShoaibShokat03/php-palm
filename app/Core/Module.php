<?php

namespace App\Core;

use PhpPalm\Core\Route;
use PhpPalm\Core\Router;

/**
 * Base Module Class
 * All modules should extend this class
 */
abstract class Module
{
    protected string $name;
    protected string $prefix;

    public function __construct(string $name, string $prefix = '')
    {
        $this->name = $name;
        $this->prefix = $prefix;
    }

    /**
     * Register routes for this module
     * Override this method in your module to define routes
     */
    abstract public function registerRoutes(): void;

    /**
     * Get module name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get route prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Helper method to prefix routes
     */
    protected function route(string $path): string
    {
        if (empty($this->prefix)) {
            return $path;
        }
        
        // If path is empty, just return the prefix
        if (empty($path)) {
            return $this->prefix;
        }
        
        // Combine prefix and path, ensuring no double slashes
        $prefix = rtrim($this->prefix, '/');
        $path = ltrim($path, '/');
        return $prefix . '/' . $path;
    }

    // ============================================
    // INTERNAL ROUTE CALLING (No HTTP Request)
    // ============================================

    /**
     * Call a GET route internally (without HTTP request)
     * Returns the data from the route handler
     * 
     * Usage: UsersModule::get('/users') or UsersModule::get('/users/1')
     * 
     * @param string $path Route path (with or without module prefix)
     * @param array $params Route parameters for dynamic routes (e.g., ['id' => 1])
     * @return mixed The data from the route response, or null if route not found/error
     */
    public static function get(string $path, array $params = [])
    {
        return static::callRoute('GET', $path, null, $params);
    }

    /**
     * Call a POST route internally (without HTTP request)
     * 
     * Usage: UsersModule::post('/users', ['name' => 'John', 'email' => 'john@example.com'])
     * 
     * @param string $path Route path
     * @param array|null $data Request data to send
     * @param array $params Route parameters for dynamic routes
     * @return mixed The data from the route response
     */
    public static function post(string $path, ?array $data = null, array $params = [])
    {
        return static::callRoute('POST', $path, $data, $params);
    }

    /**
     * Call a PUT route internally (without HTTP request)
     * 
     * Usage: UsersModule::put('/users/1', ['name' => 'Updated Name'])
     * 
     * @param string $path Route path
     * @param array|null $data Request data to send
     * @param array $params Route parameters for dynamic routes
     * @return mixed The data from the route response
     */
    public static function put(string $path, ?array $data = null, array $params = [])
    {
        return static::callRoute('PUT', $path, $data, $params);
    }

    /**
     * Call a DELETE route internally (without HTTP request)
     * 
     * Usage: UsersModule::delete('/users/1')
     * 
     * @param string $path Route path
     * @param array $params Route parameters for dynamic routes
     * @return mixed The data from the route response
     */
    public static function delete(string $path, array $params = [])
    {
        return static::callRoute('DELETE', $path, null, $params);
    }

    /**
     * Call a PATCH route internally (without HTTP request)
     * 
     * Usage: UsersModule::patch('/users/1', ['status' => 'active'])
     * 
     * @param string $path Route path
     * @param array|null $data Request data to send
     * @param array $params Route parameters for dynamic routes
     * @return mixed The data from the route response
     */
    public static function patch(string $path, ?array $data = null, array $params = [])
    {
        return static::callRoute('PATCH', $path, $data, $params);
    }

    /**
     * Internal method to call a route without HTTP request
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE, PATCH)
     * @param string $path Route path
     * @param array|null $data Request data (for POST, PUT, PATCH)
     * @param array $params Route parameters for dynamic routes
     * @return mixed The data from the route response, or null on error
     */
    protected static function callRoute(string $method, string $path, ?array $data = null, array $params = []): mixed
    {
        // Ensure ApplicationBootstrap is initialized and routes are loaded
        if (!class_exists('App\Core\ApplicationBootstrap')) {
            return null;
        }
        
        // Initialize bootstrap to load routes if not already loaded
        try {
            ApplicationBootstrap::init();
            ApplicationBootstrap::load();
        } catch (\Throwable $e) {
            // Bootstrap might already be initialized, continue
        }
        
        // Get module instance to access prefix
        $moduleInstance = static::getModuleInstance();
        $router = Route::getRouter();
        
        if ($router === null) {
            // Router not initialized, try to initialize it
            Route::init();
            $router = Route::getRouter();
            if ($router === null) {
                return null;
            }
        }

        // Normalize path - add module prefix if path doesn't start with it
        $normalizedPath = static::normalizePath($path, $moduleInstance);
        
        // Find the route
        $routeInfo = $router->findRoute($method, $normalizedPath);
        
        if ($routeInfo === null) {
            // Try with params if provided
            if (!empty($params)) {
                $pathWithParams = static::buildPathWithParams($normalizedPath, $params);
                $routeInfo = $router->findRoute($method, $pathWithParams);
            }
            
            // If still not found, try without module prefix (in case path already includes it)
            if ($routeInfo === null && strpos($path, $moduleInstance->prefix) === 0) {
                $routeInfo = $router->findRoute($method, $path);
            }
        }

        if ($routeInfo === null) {
            return null;
        }

        // Store original request data
        $originalPost = $_POST ?? [];
        $originalGet = $_GET ?? [];
        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Reset Request class cache if it exists (using reflection)
        try {
            $requestReflection = new \ReflectionClass('PhpPalm\Core\Request');
            $staticProperties = ['method', 'post', 'get', 'parsedBody', 'body', 'allInput'];
            $originalRequestData = [];
            
            foreach ($staticProperties as $prop) {
                if ($requestReflection->hasProperty($prop)) {
                    $property = $requestReflection->getProperty($prop);
                    $property->setAccessible(true);
                    $originalRequestData[$prop] = $property->getValue();
                    $property->setValue(null); // Reset to null to force re-initialization
                }
            }
        } catch (\Throwable $e) {
            // Request class might not be loaded or structure different, continue
            $originalRequestData = [];
        }

        try {
            // Set request data for POST/PUT/PATCH
            if (in_array($method, ['POST', 'PUT', 'PATCH']) && $data !== null) {
                $_POST = $data;
                $_GET = $params; // Also set params in GET for consistency
                $_SERVER['REQUEST_METHOD'] = $method;
            } else {
                $_GET = $params;
                $_SERVER['REQUEST_METHOD'] = $method;
            }

            // Merge params into route params if route has dynamic segments
            $routeParams = array_merge($routeInfo['params'], $params);

            // Execute the route
            $response = $router->executeRoute($routeInfo['route'], array_values($routeParams));

            // Extract data from response
            // Controllers typically return: ['status' => 'success', 'data' => ..., 'message' => ...]
            if (is_array($response)) {
                // If response has 'status' and it's error, return null
                if (isset($response['status']) && $response['status'] === 'error') {
                    return null;
                }
                
                // If response has 'data' key, extract it
                if (isset($response['data'])) {
                    $data = $response['data'];
                    
                    // If data has 'items' key (common pattern for collections), return items
                    if (is_array($data) && isset($data['items']) && (is_array($data['items']) || $data['items'] instanceof \App\Core\ModelCollection)) {
                        // Convert ModelCollection to array if needed
                        if ($data['items'] instanceof \App\Core\ModelCollection) {
                            return $data['items']->toArray();
                        }
                        return $data['items'];
                    }
                    
                    // If data is a ModelCollection, convert to array
                    if ($data instanceof \App\Core\ModelCollection) {
                        return $data->toArray();
                    }
                    
                    return $data;
                }
                
                // If response has 'status' and it's success but no 'data', return the whole response
                if (isset($response['status']) && $response['status'] === 'success') {
                    return $response;
                }
                
                // Otherwise return the whole response
                return $response;
            }

            return $response;
        } catch (\Throwable $e) {
            error_log('Internal route call error: ' . $e->getMessage());
            return null;
        } finally {
            // Restore original request data
            $_POST = $originalPost;
            $_GET = $originalGet;
            $_SERVER['REQUEST_METHOD'] = $originalMethod;
            
            // Restore Request class cache
            try {
                $requestReflection = new \ReflectionClass('PhpPalm\Core\Request');
                foreach ($originalRequestData as $prop => $value) {
                    if ($requestReflection->hasProperty($prop)) {
                        $property = $requestReflection->getProperty($prop);
                        $property->setAccessible(true);
                        $property->setValue($value);
                    }
                }
            } catch (\Throwable $e) {
                // Ignore errors restoring Request cache
            }
        }
    }

    /**
     * Get module instance (create if needed)
     * Uses cached instances from ModuleLoader if available
     */
    protected static function getModuleInstance(): self
    {
        static $instances = [];
        $className = static::class;
        
        if (!isset($instances[$className])) {
            // Try to get from ModuleLoader cache if available
            $moduleLoader = new ModuleLoader();
            $modules = $moduleLoader->getModules();
            
            foreach ($modules as $module) {
                if ($module instanceof $className) {
                    $instances[$className] = $module;
                    return $module;
                }
            }
            
            // If not found in loader, try to create instance
            // Most modules have no-arg constructors that set name/prefix internally
            try {
                $reflection = new \ReflectionClass($className);
                $constructor = $reflection->getConstructor();
                
                if ($constructor && $constructor->getNumberOfParameters() === 0) {
                    // No constructor params, create instance
                    $instances[$className] = new $className();
                } else {
                    // Has constructor params, try to create with defaults
                    // Extract module name from class name (e.g., App\Modules\Users\Module -> Users)
                    $parts = explode('\\', $className);
                    $moduleName = $parts[count($parts) - 2] ?? 'Module';
                    $prefix = '/' . strtolower($moduleName);
                    $instances[$className] = new $className($moduleName, $prefix);
                }
            } catch (\Throwable $e) {
                // Fallback: create minimal instance
                $instances[$className] = new $className('Module', '');
            }
        }
        
        return $instances[$className];
    }

    /**
     * Normalize path - add module prefix if needed
     */
    protected static function normalizePath(string $path, self $moduleInstance): string
    {
        $path = ltrim($path, '/');
        $prefix = ltrim($moduleInstance->prefix, '/');
        
        // If path already starts with prefix, return as is
        if (!empty($prefix) && strpos($path, $prefix) === 0) {
            return '/' . $path;
        }
        
        // If prefix is empty, just return the path
        if (empty($prefix)) {
            return '/' . $path;
        }
        
        // Combine prefix and path
        $normalized = rtrim($prefix, '/') . '/' . ltrim($path, '/');
        return '/' . $normalized;
    }

    /**
     * Build path with parameters for dynamic routes
     */
    protected static function buildPathWithParams(string $path, array $params): string
    {
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string)$value, $path);
        }
        return $path;
    }
}


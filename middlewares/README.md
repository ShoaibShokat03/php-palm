# Custom Middlewares Development Guide

This directory is for your custom middlewares. This guide explains how to create, structure, and use custom middlewares in PHP Palm framework.

## Table of Contents

1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [Middleware Structure](#middleware-structure)
4. [Creating Middlewares](#creating-middlewares)
5. [Using Middlewares](#using-middlewares)
6. [Examples](#examples)
7. [Best Practices](#best-practices)
8. [Advanced Patterns](#advanced-patterns)

## Quick Start

1. Create a new PHP file in this directory (e.g., `MyMiddleware.php`)
2. Extend the `Middleware` base class
3. Implement the `handle()` method
4. Use it in your routes!

## Example: Creating a Custom Middleware

```php
<?php

namespace App\Middlewares;

use App\Core\Middleware;
use PhpPalm\Core\Request;

class RateLimitMiddleware extends Middleware
{
    protected int $maxRequests;
    protected int $timeWindow;

    public function __construct(int $maxRequests = 100, int $timeWindow = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
    }

    public function handle(callable $handler, ...$args)
    {
        $ip = Request::ip();
        $key = "rate_limit_{$ip}";
        
        // Your rate limiting logic here
        // ...
        
        // If rate limit exceeded
        // return $this->error('Rate limit exceeded', 429);
        
        // Otherwise, proceed
        return $this->wrap($handler, ...$args);
    }
}
```

## Using Your Middleware

### Basic Usage

```php
use App\Core\MiddlewareHelper;
use PhpPalm\Core\Route;

// Simple usage (no constructor parameters)
Route::get('/api/data', MiddlewareHelper::use('AuthMiddleware', [$controller, 'index']));

// With constructor parameters (handler must be last!)
Route::post('/api/submit', MiddlewareHelper::use('RateLimitMiddleware', 50, 30, [$controller, 'store']));
//                                                                    ^max  ^time ^handler
```

### In Your Module

```php
<?php

namespace App\Modules\Product;

use App\Core\Module as BaseModule;
use App\Core\MiddlewareHelper;
use App\Modules\Product\Controller;
use PhpPalm\Core\Route;

class Module extends BaseModule
{
    public function registerRoutes(): void
    {
        $controller = new Controller();

        // Public route
        Route::get('/products', [$controller, 'listPublic']);

        // Protected with middleware
        Route::get('/products/{id}', MiddlewareHelper::use('AuthMiddleware', [$controller, 'show']));
        Route::post('/products', MiddlewareHelper::use('AuthMiddleware', [$controller, 'store']));
    }
}
```

**See `MIDDLEWARE_USAGE.md` for complete examples and best practices!**

## Available Helper Methods

In your middleware, you can use:

- `$this->wrap($handler, ...$args)` - Execute the handler
- `$this->error($message, $statusCode, $errors)` - Return error response
- `$this->success($data, $message, $statusCode)` - Return success response

## Examples

### 1. Simple Authentication Check

```php
<?php

namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Auth;

class RequireAuthMiddleware extends Middleware
{
    public function handle(callable $handler, ...$args)
    {
        if (!Auth::check()) {
            return $this->error('Authentication required', 401);
        }
        
        return $this->wrap($handler, ...$args);
    }
}
```

### 2. API Key Validation

```php
<?php

namespace App\Middlewares;

use App\Core\Middleware;
use PhpPalm\Core\Request;

class ApiKeyMiddleware extends Middleware
{
    protected string $validApiKey;

    public function __construct(string $apiKey = null)
    {
        $this->validApiKey = $apiKey ?? $_ENV['API_KEY'] ?? '';
    }

    public function handle(callable $handler, ...$args)
    {
        $providedKey = Request::apiKey();
        
        if (!$providedKey || $providedKey !== $this->validApiKey) {
            return $this->error('Invalid API key', 401);
        }
        
        return $this->wrap($handler, ...$args);
    }
}
```

### 3. Request Logging

```php
<?php

namespace App\Middlewares;

use App\Core\Middleware;
use PhpPalm\Core\Request;

class LogRequestMiddleware extends Middleware
{
    public function handle(callable $handler, ...$args)
    {
        // Log before
        $this->logRequest();
        
        // Execute handler
        $result = $this->wrap($handler, ...$args);
        
        // Log after
        $this->logResponse($result);
        
        return $result;
    }

    protected function logRequest(): void
    {
        error_log(sprintf(
            '[%s] %s %s from %s',
            date('Y-m-d H:i:s'),
            Request::getMethod(),
            Request::path(),
            Request::ip()
        ));
    }

    protected function logResponse($result): void
    {
        // Log response if needed
    }
}
```

### 4. CORS Middleware

```php
<?php

namespace App\Middlewares;

use App\Core\Middleware;

class CorsMiddleware extends Middleware
{
    protected array $allowedOrigins;

    public function __construct(array $allowedOrigins = ['*'])
    {
        $this->allowedOrigins = $allowedOrigins;
    }

    public function handle(callable $handler, ...$args)
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        
        if (in_array('*', $this->allowedOrigins) || in_array($origin, $this->allowedOrigins)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization");
        }
        
        // Handle preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        return $this->wrap($handler, ...$args);
    }
}
```

## Best Practices

1. **Keep it simple** - Middlewares should do one thing well
2. **Use constructor parameters** - Make middlewares configurable
3. **Return early** - If validation fails, return error immediately
4. **Use helper methods** - Use `$this->error()` and `$this->success()` for consistent responses
5. **Namespace correctly** - Always use `App\Middlewares` namespace

## Naming Convention

- Use descriptive names: `AuthMiddleware`, `RateLimitMiddleware`, `LogRequestMiddleware`
- End with `Middleware` suffix
- File name should match class name: `AuthMiddleware.php` → `class AuthMiddleware`

## Auto-Loading

Middlewares are automatically discovered and loaded from this directory. Just create a file and it's available!

### How Auto-Loading Works

1. Framework scans `middlewares/` directory
2. Files matching `*Middleware.php` pattern are loaded
3. Classes must extend `App\Core\Middleware`
4. No manual registration needed!

## Advanced Patterns

### Middleware with Database Access

```php
<?php

namespace App\Middlewares;

use App\Core\Middleware;
use App\Modules\User\Model as UserModel;

class UserActivityMiddleware extends Middleware
{
    public function handle(callable $handler, ...$args)
    {
        $user = Auth::user();
        
        if ($user) {
            // Log user activity
            UserActivityModel::create([
                'user_id' => $user['id'],
                'action' => Request::path(),
                'method' => Request::getMethod(),
                'ip' => Request::ip(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return $this->wrap($handler, ...$args);
    }
}
```

### Middleware with Caching

```php
<?php

namespace App\Middlewares;

use App\Core\Middleware;

class CacheMiddleware extends Middleware
{
    protected int $ttl;

    public function __construct(int $ttl = 3600)
    {
        $this->ttl = $ttl;
    }

    public function handle(callable $handler, ...$args)
    {
        $cacheKey = $this->getCacheKey();
        
        // Check cache
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Execute handler
        $response = $this->wrap($handler, ...$args);
        
        // Cache response
        $this->saveToCache($cacheKey, $response, $this->ttl);
        
        return $response;
    }
    
    protected function getCacheKey(): string
    {
        return md5(Request::path() . serialize(Request::all()));
    }
    
    protected function getFromCache(string $key)
    {
        // Your cache implementation
        return null;
    }
    
    protected function saveToCache(string $key, $data, int $ttl): void
    {
        // Your cache implementation
    }
}
```

### Middleware with Request Transformation

```php
<?php

namespace App\Middlewares;

use App\Core\Middleware;
use PhpPalm\Core\Request;

class SanitizeInputMiddleware extends Middleware
{
    public function handle(callable $handler, ...$args)
    {
        // Sanitize all input
        $_POST = $this->sanitizeArray($_POST);
        $_GET = $this->sanitizeArray($_GET);
        
        return $this->wrap($handler, ...$args);
    }
    
    protected function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            } else {
                $data[$key] = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
            }
        }
        return $data;
    }
}
```

### Middleware with Response Headers

```php
<?php

namespace App\Middlewares;

use App\Core\Middleware;

class SecurityHeadersMiddleware extends Middleware
{
    public function handle(callable $handler, ...$args)
    {
        // Set security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        
        return $this->wrap($handler, ...$args);
    }
}
```

## Testing Middlewares

### Unit Testing

```php
// tests/Middlewares/AuthMiddlewareTest.php
class AuthMiddlewareTest extends TestCase
{
    public function testUnauthenticatedRequest()
    {
        $middleware = new AuthMiddleware();
        $handler = function() { return ['data' => 'test']; };
        
        $result = $middleware->handle($handler);
        
        $this->assertEquals('error', $result['status']);
        $this->assertEquals(401, http_response_code());
    }
}
```

## Common Middleware Patterns

### Pattern 1: Before/After Execution

```php
public function handle(callable $handler, ...$args)
{
    // Before handler execution
    $this->before();
    
    // Execute handler
    $result = $this->wrap($handler, ...$args);
    
    // After handler execution
    $this->after($result);
    
    return $result;
}
```

### Pattern 2: Conditional Execution

```php
public function handle(callable $handler, ...$args)
{
    if (!$this->shouldExecute()) {
        return $this->wrap($handler, ...$args);
    }
    
    // Execute middleware logic
    return $this->wrap($handler, ...$args);
}
```

### Pattern 3: Early Return

```php
public function handle(callable $handler, ...$args)
{
    // Check condition
    if ($this->shouldBlock()) {
        return $this->error('Blocked', 403);
    }
    
    // Continue if allowed
    return $this->wrap($handler, ...$args);
}
```

## Middleware Execution Order

When multiple middlewares are chained, they execute in order:

```php
// Execution order:
// 1. LoggingMiddleware (before)
// 2. AuthMiddleware (before)
// 3. RateLimitMiddleware (before)
// 4. Handler execution
// 5. RateLimitMiddleware (after)
// 6. AuthMiddleware (after)
// 7. LoggingMiddleware (after)

Route::post('/api', 
    MiddlewareHelper::use('LoggingMiddleware',
        MiddlewareHelper::use('AuthMiddleware',
            MiddlewareHelper::use('RateLimitMiddleware',
                [$controller, 'store']
            )
        )
    )
);
```

## Performance Considerations

1. **Keep Middlewares Lightweight**: Avoid heavy operations in middleware
2. **Cache Expensive Checks**: Cache authentication checks, rate limit data, etc.
3. **Early Returns**: Return errors early to avoid unnecessary processing
4. **Database Queries**: Minimize database queries in middleware

## Troubleshooting

### Middleware Not Found

- Check file is in `middlewares/` directory
- Verify class name matches filename
- Ensure namespace is `App\Middlewares`
- Run `composer dump-autoload`

### Middleware Not Executing

- Verify middleware is registered in route
- Check `handle()` method calls `$this->wrap()`
- Ensure no errors in middleware code
- Check error logs for exceptions

### Handler Not Receiving Data

- Ensure `$this->wrap($handler, ...$args)` preserves arguments
- Don't modify `$args` unless necessary
- Check route parameter order

## Additional Resources

- **`MIDDLEWARE_USAGE.md`** - Complete middleware usage guide
- **`app/Core/Middleware.php`** - Base middleware class source
- **`app/Core/MiddlewareHelper.php`** - Middleware helper utilities


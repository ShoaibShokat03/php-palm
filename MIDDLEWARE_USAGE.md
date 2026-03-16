# 🛡️ Middleware Documentation

Middleware provides a convenient mechanism for filtering HTTP requests entering your application. For example, PHP Palm includes middleware that verifies the user of your application is authenticated.

---

## 📂 Directory Structure

All of your custom middlewares should be stored in the root `middlewares/` directory.

```text
/
├── middlewares/
│   ├── AuthMiddleware.php
│   └── VisitorCounterMiddleware.php
└── app/
    └── Core/
        └── Middleware.php (Base Class)
```

---

## 🛠️ Creating Middleware

All middlewares must extend the `App\Core\Middleware` base class. This base class implements `Frontend\Palm\MiddlewareInterface`, making your middleware compatible with both **Frontend** and **Core** routes.

### Basic Template

```php
<?php

namespace App\Middlewares;

use App\Core\Middleware;

class MyCustomMiddleware extends Middleware
{
    /**
     * Handle the request
     * 
     * @param callable $next The next middleware or route handler
     * @param mixed ...$args Route parameters (for Core routes)
     * @return mixed
     */
    public function handle(callable $next, ...$args): mixed
    {
        // 1. Logic BEFORE the request
        // e.g., check header, start timer, log IP
        
        // 2. Execute the next handler/middleware
        $response = $next(...$args);
        
        // 3. Logic AFTER the request
        // e.g., add headers to response, log execution time
        
        return $response;
    }
}
```

---

## 🚀 Usage in Routes

### 1. Frontend Routes (`src/routes/web.php`)

Frontend routes support both individual assignment and group assignment.

#### Individual Route
```php
use App\Core\MiddlewareHelper;

Route::get('/profile', MiddlewareHelper::use('AuthMiddleware', function() {
    Route::render('user.profile');
}));
```

#### Route Grouping (Recommended)
You can wrap multiple routes in a middleware group:
```php
use App\Middlewares\AuthMiddleware;

Route::middleware([AuthMiddleware::class], function() {
    Route::get('/dashboard', ...);
    Route::get('/settings', ...);
});
```

---

### 2. Core/API Routes (`routes/api.php`)

For API routes, middleware is usually applied via the `MiddlewareHelper`.

```php
use App\Core\MiddlewareHelper;

Route::get('/api/data', MiddlewareHelper::use('AuthMiddleware', [$controller, 'getData']));
```

---

## 💡 Helper Methods

The base `Middleware` class provides several helper methods to simplify your logic:

| Method | Description |
|--------|-------------|
| `$this->success($data, $message, $code)` | Returns a formatted JSON success response. |
| `$this->error($message, $code, $errors)` | Returns a formatted JSON error response. |
| `$this->wrap($handler, ...$args)` | Manually executes a handler. |

---

## 📝 Best Practices

1. **Namespace**: Always use `namespace App\Middlewares;`.
2. **Handle Signature**: Always match the signature `handle(callable $next, ...$args): mixed`.
3. **Early Exit**: If a condition fails (e.g., unauthorized), return a response immediately instead of calling `$next()`.
4. **Thin Middlewares**: Keep middlewares focused on a single task (e.g., only Authentication, only Logging).

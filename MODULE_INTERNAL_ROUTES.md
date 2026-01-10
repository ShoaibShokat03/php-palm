# Internal Routes - Usage Guide

Complete guide to calling routes internally without HTTP requests. This feature allows you to call routes like functions, making it perfect for server-side rendering, internal API calls, and code reuse.

**Two Types of Internal Routes:**
1. **Module Routes** - Call API/module routes (returns data)
2. **Frontend Routes** - Call frontend/view routes (returns HTML)

---

## Table of Contents

1. [Introduction](#introduction)
2. [Quick Start](#quick-start)
3. [Basic Usage](#basic-usage)
4. [Advanced Usage](#advanced-usage)
5. [Use Cases](#use-cases)
6. [Best Practices](#best-practices)
7. [API Reference](#api-reference)
8. [Examples](#examples)

---

## Introduction

PHP Palm's **Internal Route Calling** feature allows you to call module routes directly as methods, without making HTTP requests. This is similar to calling a function, but it executes the full route handler including controllers, services, and models.

### Key Benefits

- ✅ **No HTTP Overhead** - Direct function calls, no network requests
- ✅ **Server-Side Rendering** - Perfect for rendering data in views
- ✅ **Code Reuse** - Use the same route logic in multiple places
- ✅ **Type Safety** - Full IDE autocomplete support
- ✅ **Same Response Format** - Returns the same data structure as HTTP routes
- ✅ **Automatic Data Extraction** - Intelligently extracts data from responses

### How It Works

When you call `UsersModule::get('/users')`, the framework:
1. Finds the route in the router
2. Sets up temporary request data
3. Executes the route handler (Controller → Service → Model)
4. Extracts and returns the data
5. Cleans up request state

---

## Quick Start

### Basic Example

```php
use App\Modules\Users\Module as UsersModule;

// Get all users (no HTTP request!)
$users = UsersModule::get('/users');

// Use the data
foreach ($users as $user) {
    echo $user['name'];
}
```

### In Views

```php
<?php
use App\Modules\Users\Module as UsersModule;

// Call route internally
$users = UsersModule::get('/users');
?>

<div class="users">
    <?php foreach ($users as $user): ?>
        <div class="user">
            <h3><?= htmlspecialchars($user['name']) ?></h3>
            <p><?= htmlspecialchars($user['email']) ?></p>
        </div>
    <?php endforeach; ?>
</div>
```

---

## Basic Usage

### GET Requests

Get data from routes:

```php
use App\Modules\Users\Module as UsersModule;
use App\Modules\Product\Module as ProductModule;

// Get all users
$users = UsersModule::get('/users');

// Get user by ID (dynamic route)
$user = UsersModule::get('/users/1');

// Get products with query
$products = ProductModule::get('/products');
```

### POST Requests

Create new records:

```php
use App\Modules\Users\Module as UsersModule;

// Create a new user
$newUser = UsersModule::post('/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secure123'
]);

if ($newUser) {
    echo "User created: " . $newUser['name'];
}
```

### PUT Requests

Update existing records:

```php
use App\Modules\Users\Module as UsersModule;

// Update user
$updated = UsersModule::put('/users/1', [
    'name' => 'John Updated',
    'email' => 'john.updated@example.com'
]);

if ($updated) {
    echo "User updated successfully";
}
```

### DELETE Requests

Delete records:

```php
use App\Modules\Users\Module as UsersModule;

// Delete user
$deleted = UsersModule::delete('/users/1');

if ($deleted) {
    echo "User deleted successfully";
} else {
    echo "User not found or deletion failed";
}
```

### PATCH Requests

Partial updates:

```php
use App\Modules\Users\Module as UsersModule;

// Update only specific fields
$updated = UsersModule::patch('/users/1', [
    'status' => 'active'
]);
```

---

## Advanced Usage

### Dynamic Routes with Parameters

For routes with parameters like `/users/{id}`, pass parameters as an array:

```php
use App\Modules\Users\Module as UsersModule;

// Get user by ID
$user = UsersModule::get('/users/1');

// Or use parameters array (for complex routes)
$user = UsersModule::get('/users/{id}', ['id' => 1]);
```

### Handling Responses

The methods automatically extract data from controller responses:

```php
use App\Modules\Users\Module as UsersModule;

$response = UsersModule::get('/users');

// Response structure depends on controller:
// If controller returns: ['status' => 'success', 'data' => [...]]
// Method returns: [...] (just the data)

// If controller returns: ['status' => 'success', 'data' => ['items' => [...], 'total' => 5]]
// Method returns: [...] (just the items array)

// If response is error: ['status' => 'error', ...]
// Method returns: null
```

### Error Handling

```php
use App\Modules\Users\Module as UsersModule;

$user = UsersModule::get('/users/999');

if ($user === null) {
    // Route not found, error occurred, or record doesn't exist
    echo "User not found";
} else {
    // Success - use the data
    echo $user['name'];
}
```

### Working with ModelCollections

When routes return ModelCollections, they're automatically converted to arrays:

```php
use App\Modules\Users\Module as UsersModule;

// Service returns: ['total' => 10, 'items' => ModelCollection]
// Method automatically extracts 'items' and converts to array
$users = UsersModule::get('/users');

// $users is now a plain array, ready to use
foreach ($users as $user) {
    echo $user['name'];
}
```

---

## Use Cases

### 1. Server-Side Rendering in Views

Render data directly in PHP views without AJAX:

```php
<?php
use App\Modules\Product\Module as ProductModule;

// Get featured products
$products = ProductModule::get('/products/featured');
?>

<div class="featured-products">
    <?php foreach ($products as $product): ?>
        <div class="product-card">
            <h3><?= htmlspecialchars($product['name']) ?></h3>
            <p><?= htmlspecialchars($product['description']) ?></p>
            <span class="price">$<?= number_format($product['price'], 2) ?></span>
        </div>
    <?php endforeach; ?>
</div>
```

### 2. Internal API Calls

Call routes from other services or controllers:

```php
namespace App\Modules\Order;

use App\Modules\Product\Module as ProductModule;
use App\Modules\User\Module as UserModule;

class Service extends BaseService
{
    public function createOrder(array $data): array
    {
        // Get product internally (no HTTP call)
        $product = ProductModule::get('/products/' . $data['product_id']);
        
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        // Get user internally
        $user = UserModule::get('/users/' . $data['user_id']);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Create order with validated data
        // ...
    }
}
```

### 3. Data Aggregation

Combine data from multiple routes:

```php
use App\Modules\Product\Module as ProductModule;
use App\Modules\Category\Module as CategoryModule;
use App\Modules\Review\Module as ReviewModule;

// Get product with related data
$product = ProductModule::get('/products/1');
$category = CategoryModule::get('/categories/' . $product['category_id']);
$reviews = ReviewModule::get('/reviews?product_id=1');

$productData = [
    'product' => $product,
    'category' => $category,
    'reviews' => $reviews
];
```

### 4. Background Jobs

Use routes in background jobs or scheduled tasks:

```php
namespace App\Jobs;

use App\Modules\Email\Module as EmailModule;
use App\Modules\User\Module as UserModule;

class SendNewsletterJob
{
    public function execute(): void
    {
        // Get all active users
        $users = UserModule::get('/users?status=active');
        
        foreach ($users as $user) {
            // Send email using internal route
            EmailModule::post('/emails/send', [
                'to' => $user['email'],
                'subject' => 'Newsletter',
                'body' => '...'
            ]);
        }
    }
}
```

### 5. Testing

Test route handlers without HTTP:

```php
use App\Modules\Users\Module as UsersModule;

// In your test
public function testGetUsers()
{
    $users = UsersModule::get('/users');
    
    $this->assertIsArray($users);
    $this->assertNotEmpty($users);
    $this->assertArrayHasKey('name', $users[0]);
}
```

---

## Best Practices

### 1. Use Aliases for Cleaner Code

```php
// ✅ Good: Use aliases
use App\Modules\Users\Module as UsersModule;
use App\Modules\Product\Module as ProductModule;

$users = UsersModule::get('/users');
$products = ProductModule::get('/products');

// ❌ Avoid: Long class names
$users = \App\Modules\Users\Module::get('/users');
```

### 2. Handle Null Responses

Always check for null responses:

```php
$user = UsersModule::get('/users/999');

if ($user === null) {
    // Handle error case
    return ['error' => 'User not found'];
}

// Use the data
return ['user' => $user];
```

### 3. Use in Views, Not in Controllers

```php
// ✅ Good: Use in views for server-side rendering
// src/views/products/index.php
$products = ProductModule::get('/products');

// ❌ Avoid: Using in controllers (use services instead)
// In Controller:
public function index() {
    $products = ProductModule::get('/products'); // Redundant!
    return $this->success($products);
}
```

### 4. Cache Results When Appropriate

```php
// Cache expensive internal calls
$cacheKey = 'users_list';
$users = cache()->remember($cacheKey, 3600, function() {
    return UsersModule::get('/users');
});
```

### 5. Don't Overuse

Use internal routes when it makes sense:

```php
// ✅ Good: When you need route logic (validation, business logic)
$user = UsersModule::post('/users', $data); // Uses full route logic

// ❌ Avoid: When you just need data (use models directly)
$users = UserModel::all(); // Direct model access is better
```

---

## API Reference

### Static Methods

All methods are static and available on any Module class.

#### `Module::get(string $path, array $params = []): mixed`

Call a GET route internally.

**Parameters:**
- `$path` (string) - Route path (e.g., '/users' or '/users/1')
- `$params` (array) - Route parameters for dynamic routes (optional)

**Returns:** Mixed - The data from the route response, or `null` on error

**Example:**
```php
$users = UsersModule::get('/users');
$user = UsersModule::get('/users/1');
```

#### `Module::post(string $path, ?array $data = null, array $params = []): mixed`

Call a POST route internally.

**Parameters:**
- `$path` (string) - Route path
- `$data` (array|null) - Request data to send (optional)
- `$params` (array) - Route parameters for dynamic routes (optional)

**Returns:** Mixed - The data from the route response, or `null` on error

**Example:**
```php
$user = UsersModule::post('/users', [
    'name' => 'John',
    'email' => 'john@example.com'
]);
```

#### `Module::put(string $path, ?array $data = null, array $params = []): mixed`

Call a PUT route internally.

**Parameters:**
- `$path` (string) - Route path
- `$data` (array|null) - Request data to send (optional)
- `$params` (array) - Route parameters for dynamic routes (optional)

**Returns:** Mixed - The data from the route response, or `null` on error

**Example:**
```php
$user = UsersModule::put('/users/1', [
    'name' => 'Updated Name'
]);
```

#### `Module::delete(string $path, array $params = []): mixed`

Call a DELETE route internally.

**Parameters:**
- `$path` (string) - Route path
- `$params` (array) - Route parameters for dynamic routes (optional)

**Returns:** Mixed - The data from the route response, or `null` on error

**Example:**
```php
$result = UsersModule::delete('/users/1');
```

#### `Module::patch(string $path, ?array $data = null, array $params = []): mixed`

Call a PATCH route internally.

**Parameters:**
- `$path` (string) - Route path
- `$data` (array|null) - Request data to send (optional)
- `$params` (array) - Route parameters for dynamic routes (optional)

**Returns:** Mixed - The data from the route response, or `null` on error

**Example:**
```php
$user = UsersModule::patch('/users/1', [
    'status' => 'active'
]);
```

---

## Examples

### Complete CRUD Example

```php
use App\Modules\Users\Module as UsersModule;

// Create
$newUser = UsersModule::post('/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Read
$users = UsersModule::get('/users');
$user = UsersModule::get('/users/1');

// Update
$updated = UsersModule::put('/users/1', [
    'name' => 'John Updated'
]);

// Delete
$deleted = UsersModule::delete('/users/1');
```

### View with Data

```php
<?php
use App\Modules\Product\Module as ProductModule;
use App\Modules\Category\Module as CategoryModule;

// Get data
$products = ProductModule::get('/products');
$categories = CategoryModule::get('/categories');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Products</title>
</head>
<body>
    <h1>Products</h1>
    
    <div class="categories">
        <?php foreach ($categories as $category): ?>
            <a href="/categories/<?= $category['id'] ?>">
                <?= htmlspecialchars($category['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div class="products">
        <?php foreach ($products as $product): ?>
            <div class="product">
                <h3><?= htmlspecialchars($product['name']) ?></h3>
                <p><?= htmlspecialchars($product['description']) ?></p>
                <span class="price">$<?= number_format($product['price'], 2) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
```

### Service Using Internal Routes

```php
namespace App\Modules\Order;

use App\Modules\Product\Module as ProductModule;
use App\Modules\User\Module as UserModule;
use App\Modules\Inventory\Module as InventoryModule;

class Service extends BaseService
{
    public function createOrder(array $data): array
    {
        // Validate product exists
        $product = ProductModule::get('/products/' . $data['product_id']);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        // Validate user exists
        $user = UserModule::get('/users/' . $data['user_id']);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Check inventory
        $inventory = InventoryModule::get('/inventory/' . $data['product_id']);
        if (!$inventory || $inventory['quantity'] < $data['quantity']) {
            return ['success' => false, 'message' => 'Insufficient inventory'];
        }
        
        // Create order
        $order = OrderModel::create([
            'user_id' => $data['user_id'],
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'total' => $product['price'] * $data['quantity']
        ]);
        
        // Update inventory
        InventoryModule::put('/inventory/' . $data['product_id'], [
            'quantity' => $inventory['quantity'] - $data['quantity']
        ]);
        
        return ['success' => true, 'data' => $order];
    }
}
```

### Error Handling

```php
use App\Modules\Users\Module as UsersModule;

function getUserData(int $userId): ?array
{
    $user = UsersModule::get('/users/' . $userId);
    
    if ($user === null) {
        // Handle different error cases
        error_log("User {$userId} not found");
        return null;
    }
    
    return $user;
}

// Usage
$user = getUserData(1);
if ($user) {
    echo "User: " . $user['name'];
} else {
    echo "User not found";
}
```

---

## Response Format

### Success Response

When a route returns:
```php
['status' => 'success', 'data' => [...], 'message' => '...']
```

The method extracts and returns just the data:
```php
$users = UsersModule::get('/users');
// Returns: [...] (just the data array)
```

### Collection Response

When a route returns:
```php
['status' => 'success', 'data' => ['items' => ModelCollection, 'total' => 10]]
```

The method extracts items and converts ModelCollection to array:
```php
$users = UsersModule::get('/users');
// Returns: [...] (array of user data)
```

### Error Response

When a route returns:
```php
['status' => 'error', 'message' => '...']
```

The method returns `null`:
```php
$user = UsersModule::get('/users/999');
// Returns: null
```

---

## Limitations

1. **No Middleware Execution** - Middlewares are not executed for internal route calls
2. **No HTTP Headers** - Response headers are not set
3. **Request State** - Original request state is temporarily modified
4. **Performance** - Still executes full route handler (Controller → Service → Model)

---

## Troubleshooting

### Route Not Found

If `get()` returns `null`, check:
- Route path is correct
- Route is registered in Module::registerRoutes()
- ApplicationBootstrap has loaded routes

### Data Not Extracted Correctly

If data format is unexpected:
- Check controller response format
- Verify service return structure
- Use `var_dump()` to inspect response

### Request Data Not Available

For POST/PUT/PATCH routes:
- Ensure data is passed as second parameter
- Check controller uses `$this->getRequestData()`

---

## Comparison with HTTP Routes

| Feature | HTTP Routes | Internal Routes |
|---------|-------------|-----------------|
| Network Overhead | Yes | No |
| Middleware | Yes | No |
| Headers | Yes | No |
| Response Format | JSON | PHP Array |
| Use Case | API/External | Internal/Views |
| Performance | Slower | Faster |

---

## Frontend Route Calling

PHP Palm also supports calling **frontend routes** internally, which is useful for rendering views or getting HTML output without HTTP requests.

### Quick Start

```php
use Frontend\Palm\Route;

// Call a frontend route and get HTML
$html = Route::callGet('/about');

// Use the HTML
echo $html;
```

### Available Methods

```php
use Frontend\Palm\Route;

// Call GET route
$html = Route::callGet('/about');
$html = Route::callGet('/contact', ['name' => 'John']); // With query params

// Call POST route
$html = Route::callPost('/contact', [
    'name' => 'John',
    'message' => 'Hello'
]);

// Generic call method
$html = Route::call('/about', [], 'GET');
$html = Route::call('/contact', ['name' => 'John'], 'POST');

// Get view data without rendering
$data = Route::getViewData('/about');
```

### Use Cases

#### 1. Render Views in Other Views

```php
<?php
use Frontend\Palm\Route;

// Render a partial view
$headerHtml = Route::callGet('/partials/header');
$footerHtml = Route::callGet('/partials/footer');
?>

<?= $headerHtml ?>
<main>
    <!-- Your content -->
</main>
<?= $footerHtml ?>
```

#### 2. Email Templates

```php
use Frontend\Palm\Route;

// Render email template
$emailHtml = Route::callGet('/emails/welcome', [
    'user_name' => 'John',
    'activation_link' => 'https://example.com/activate'
]);

// Send email
mail($userEmail, 'Welcome!', $emailHtml, [
    'Content-Type: text/html'
]);
```

#### 3. Generate Static Content

```php
use Frontend\Palm\Route;

// Generate static HTML files
$aboutHtml = Route::callGet('/about');
file_put_contents('static/about.html', $aboutHtml);

$contactHtml = Route::callGet('/contact');
file_put_contents('static/contact.html', $contactHtml);
```

### Frontend vs Module Routes

| Feature | Module Routes | Frontend Routes |
|---------|---------------|-----------------|
| **Returns** | Data (array) | HTML (string) |
| **Use Case** | API calls, data fetching | View rendering, HTML generation |
| **Example** | `UsersModule::get('/users')` | `Route::callGet('/about')` |
| **Output** | `['id' => 1, 'name' => 'John']` | `'<div>About Page</div>'` |

### Complete Example

```php
<?php
use Frontend\Palm\Route;
use App\Modules\Users\Module as UsersModule;

// Get data from API route
$users = UsersModule::get('/users');

// Render a view with that data
$usersHtml = Route::callGet('/users/list', ['users' => $users]);

// Or render a partial
$headerHtml = Route::callGet('/partials/header');
$footerHtml = Route::callGet('/partials/footer');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Users</title>
</head>
<body>
    <?= $headerHtml ?>
    <main>
        <?= $usersHtml ?>
    </main>
    <?= $footerHtml ?>
</body>
</html>
```

---

**For more information, see:**
- [README.md](README.md) - Main framework documentation
- [ACTIVERECORD_USAGE.md](ACTIVERECORD_USAGE.md) - ActiveRecord guide
- Module examples in `modules/` directory
- Frontend routes in `src/routes/web.php`


# PHP Palm 🚀 - Modular API Framework

A modern, lightweight PHP framework designed for rapid API development with a modular architecture. Perfect for building RESTful APIs quickly and efficiently!

---

## 📋 Table of Contents

1. [Introduction](#introduction)
2. [Installation & Setup](#installation--setup)
3. [Project Structure](#project-structure)
4. [Environment Configuration](#environment-configuration)
5. [Two Routing Approaches](#two-routing-approaches)
6. [Module System](#module-system)
7. [Internal Route Calling (Call Routes Like Functions)](#internal-route-calling-call-routes-like-functions)
8. [ActiveRecord Usage](#activerecord-usage)
9. [Palm CLI Commands](#palm-cli-commands)
10. [Frontend Scaffolding (src/)](#frontend-scaffolding-src)
11. [Google Authentication](#google-authentication)
12. [Building CRUD APIs](#building-crud-apis)
12. [Request & Response](#request--response)
13. [Examples & Use Cases](#examples--use-cases)
14. [Best Practices](#best-practices)
15. [Troubleshooting](#troubleshooting)

---

## Introduction

### What is PHP Palm?

PHP Palm is a modern, lightweight PHP framework designed for rapid API development with a modular architecture. It combines the simplicity of PHP with powerful features like ActiveRecord ORM, middleware support, authentication, and a flexible routing system. Perfect for building RESTful APIs and full-stack web applications quickly and efficiently!

### Key Features

✅ **Dual Routing System** - Simple routes OR modular architecture (your choice!)  
✅ **Modular Architecture** - Organize code into self-contained modules (like NestJS)  
✅ **Auto-Generated Code** - Create modules with one command using `palm make`  
✅ **ActiveRecord Pattern** - Easy database operations without writing SQL  
✅ **Built-in Authentication** - Bearer token authentication with role-based access control  
✅ **Google OAuth** - Easy Google authentication with helper functions and automatic OAuth flow  
✅ **Middleware System** - Flexible middleware for authentication, rate limiting, logging, and more  
✅ **Built-in Security** - Rate limiting, CORS, security headers, SQL injection protection, XSS protection, CSP  
✅ **Comprehensive Error Handling** - Automatic error catching with detailed responses  
✅ **CLI Tools** - Generate code with `palm` commands (`palm make ...`)  
✅ **Frontend Router** - Clean PHP Route::get()/Route::post() helpers with route groups, prefixes, and resource routes  
✅ **Advanced Routing** - Route groups, prefixes, resource routes, and frontend middleware  
✅ **Query Builder** - Fluent query builder with relationships and eager loading  
✅ **Modern Web Features** - PWA support, Dark Mode, i18n, SEO tools, Analytics integration  
✅ **Developer Experience** - 65+ helper functions, component system, form builder, API helpers  
✅ **Performance** - Route caching, view caching, output compression, HTTP caching  
✅ **Beginner Friendly** - Clear structure and comprehensive documentation

### Why PHP Palm?

- **Fast Development**: Generate complete CRUD modules in seconds
- **Type-Safe**: Full IDE autocomplete support with proper type hints
- **Modern PHP**: Uses PHP 7.4+ features and best practices
- **No Boilerplate**: Auto-generated code follows framework conventions
- **Flexible**: Use simple routes for prototypes or modules for production
- **Production Ready**: Built-in security, error handling, and best practices  

---

## Installation & Setup

### Prerequisites

- **PHP 7.4 or higher** (PHP 8.0+ recommended for better performance)
- **Composer** (PHP package manager) - [Download Composer](https://getcomposer.org/)
- **MySQL 5.7+** or **MariaDB 10.2+** (or any PDO-compatible database)
- **Web server** (Apache/Nginx) or PHP built-in server for development
- **Git** (optional, for version control)

### System Requirements

- **Memory**: Minimum 128MB PHP memory limit (256MB recommended)
- **Extensions**: 
  - `pdo` and `pdo_mysql` (for database)
  - `mbstring` (for string operations)
  - `json` (for JSON handling)
  - `openssl` (for secure connections)

### Step 1: Install Dependencies

```bash
composer install
```

This installs all required packages including:
- `vlucas/phpdotenv` - Environment variable management
- `php-palm/core` - Core routing and framework components
- `graham-campbell/result-type` - Result type handling
- `phpoption/phpoption` - Optional value handling
- `symfony/polyfill-*` - PHP polyfills for compatibility

### Step 1.5: Verify Installation

After installation, verify everything is set up correctly:

```bash
# Check PHP version
php -v

# Check Composer
composer --version

# Verify dependencies
composer show
```

### Step 2: Configure Environment

Create a `.env` file in the `config/` folder:

```env
# Database Configuration
DATABASE_SERVER_NAME=localhost
DATABASE_USERNAME=root
DATABASE_PASSWORD=your_password
DATABASE_NAME=your_database

# Optional: API Configuration
API_KEY=your_api_key_here
DEBUG_MODE=false
```

### Step 3: Start the Server

**Windows:**
```bash
serve.bat
```

**Linux/Mac:**
```bash
php -S localhost:8000
```

Your API will be available at: `http://localhost:8000/api`

### Step 4: Verify Installation

Test that your installation is working:

```bash
# Test the API endpoint
curl http://localhost:8000/api

# Or open in browser
# http://localhost:8000/api
```

You should see a JSON response indicating the API is running.

### Step 5: Create Your First Module (Optional)

Try creating your first module to test the CLI:

```bash
palm make module Product /products
```

This creates a complete CRUD module in `modules/Product/` with all necessary files.

---

## Project Structure

Here's the complete folder structure of PHP Palm:

```
php-palm-moduler/
├── app/                          # Application core
│   ├── Core/                     # Base classes (framework core)
│   │   ├── Controller.php        # Base controller class
│   │   ├── Service.php           # Base service class
│   │   ├── Model.php             # Base model class (ActiveRecord)
│   │   ├── Module.php            # Base module class
│   │   ├── ModuleLoader.php      # Auto-loads modules
│   │   ├── QueryBuilder.php      # Query builder for ActiveRecord
│   │   ├── Request.php           # Request handling
│   │   ├── Route.php             # Route registration
│   │   ├── Router.php            # Route dispatcher
│   │   └── ...
│   │
│   ├── Database/                 # Database layer
│   │   └── Db.php                # Database connection class
│   │
│   ├── scripts/                  # Code generators
│   │   ├── make-module.php       # Generate complete module
│   │   ├── make-controller.php   # Generate controller
│   │   ├── make-model.php        # Generate model
│   │   ├── make-service.php      # Generate service
│   │   ├── usetable.php          # Generate from database tables
│   │   └── *.bat                 # Windows batch files
│   │
│   └── storage/                  # Storage files
│       └── ratelimit/            # Rate limiting data
│
├── modules/                      # YOUR MODULES GO HERE ✨
│   ├── Users/                    # Example: Users module
│   │   ├── Module.php            # Route definitions
│   │   ├── Controller.php        # HTTP request handlers
│   │   ├── Service.php           # Business logic
│   │   └── Model.php             # Database model
│   └── [Your other modules...]
│
├── routes/                       # SIMPLE ROUTES (Optional)
│   └── api.php                   # Simple route file
│
├── config/                       # Configuration files
│   ├── .env                      # Environment variables
│   └── cors.php                  # CORS configuration
│
├── public/                       # Public files (images, CSS, JS)
│   └── [your public files]
│
├── vendor/                       # Composer dependencies (auto-generated)
├── index.php                     # Entry point (main file)
├── palm.bat                      # Cross-platform CLI launcher
├── serve.bat                     # Development server script
├── public/                      # Public assets (includes palm-spa.js runtime)
└── composer.json                # PHP dependencies
```

### Folder Explanation

#### `app/Core/`
Contains base classes that all your modules extend. **Don't modify these files** - they're the framework core.

#### `modules/`
This is where **your code goes**. Each module is a self-contained feature with its own Controller, Service, Model, and Module files.

#### `routes/`
For simple, quick routes. Perfect for prototypes or simple APIs. Optional if you're using modules.

#### `config/`
Configuration files including environment variables (`.env`) and CORS settings.

#### `public/`
Static files like images, CSS, JavaScript that are served directly.

---

## Environment Configuration

### Creating `.env` File

Create a file named `.env` in the `config/` folder:

```env
# ============================================
# Database Configuration
# ============================================
DATABASE_SERVER_NAME=localhost
DATABASE_USERNAME=root
DATABASE_PASSWORD=your_password_here
DATABASE_NAME=my_database

# ============================================
# Optional: Application Settings
# ============================================
API_KEY=your_secret_api_key
DEBUG_MODE=false
APP_NAME=My API
APP_URL=http://localhost:8000
```

### Accessing Environment Variables

In your PHP code:

```php
// Get environment variable
$dbName = $_ENV['DATABASE_NAME'];
$apiKey = $_ENV['API_KEY'] ?? 'default_value';

// Use in database connection
$db = new Db(); // Automatically reads from .env
```

### Important Notes

- ✅ `.env` file is loaded automatically from `config/` folder
- ✅ Never commit `.env` to version control (add to `.gitignore`)
- ✅ Use `.env.example` as a template for other developers
- ✅ All database settings are read from `.env` automatically

---

## Two Routing Approaches

PHP Palm offers **two ways** to create routes - choose what works best for you!

### Approach 1: Simple Routes (Fastest)

Perfect for quick prototypes, simple APIs, or learning.

**Location:** `routes/api.php`

```php
use PhpPalm\Core\Route;
use PhpPalm\Core\Request;

// Simple GET route
Route::get('/hello', function() {
    return ['message' => 'Hello World!'];
});

// Route with parameter
Route::get('/user/{id}', function($id) {
    return ['user_id' => $id];
});

// POST route with JSON data
Route::post('/users', function() {
    $data = Request::getJson();
    return ['status' => 'success', 'data' => $data];
});

// PUT route
Route::put('/users/{id}', function($id) {
    $data = Request::getJson();
    return ['status' => 'updated', 'id' => $id, 'data' => $data];
});

// DELETE route
Route::delete('/users/{id}', function($id) {
    return ['status' => 'deleted', 'id' => $id];
});
```

**Advantages:**
- ✅ Super fast to write
- ✅ No file structure needed
- ✅ Perfect for prototypes
- ✅ Great for learning

**When to use:**
- Quick prototypes
- Simple APIs
- Learning the framework
- Small projects

### Approach 2: Modular Routes (Recommended for Production)

Organized, scalable, production-ready code structure.

**Location:** `modules/YourModule/Module.php`

```php
namespace App\Modules\Product;

use App\Core\Module as BaseModule;
use PhpPalm\Core\Route;

class Module extends BaseModule
{
    public function __construct()
    {
        parent::__construct('Product', '/products');
    }

    public function registerRoutes(): void
    {
        $controller = new Controller();

        // CRUD routes
        Route::get($this->route(''), [$controller, 'index']);           // GET /products
        Route::get($this->route('/{id}'), [$controller, 'show']);       // GET /products/1
        Route::post($this->route(''), [$controller, 'store']);          // POST /products
        Route::put($this->route('/{id}'), [$controller, 'update']);     // PUT /products/1
        Route::delete($this->route('/{id}'), [$controller, 'destroy']); // DELETE /products/1

        // Custom routes
        Route::get($this->route('/featured'), [$controller, 'featured']);
        Route::get($this->route('/search/{query}'), [$controller, 'search']);
    }
}
```

**Advantages:**
- ✅ Organized code structure
- ✅ Separation of concerns
- ✅ Easy to maintain
- ✅ Perfect for production
- ✅ Auto-generated with `palm make`

**When to use:**
- Production applications
- Large projects
- Team development
- When you need organization

---

## Module System

A module is a complete feature with 4 files working together:

### Module Architecture

```
Module (Product)
├── Module.php      → Defines routes
├── Controller.php  → Handles HTTP requests
├── Service.php     → Business logic & validation
└── Model.php       → Database operations (ActiveRecord)
```

### 1. Module.php - Route Definitions

**Purpose:** Defines which URLs map to which controller methods.

```php
namespace App\Modules\Product;

use App\Core\Module as BaseModule;
use PhpPalm\Core\Route;

class Module extends BaseModule
{
    public function __construct()
    {
        parent::__construct('Product', '/products');
        //                          ↑        ↑
        //                    Module Name  Route Prefix
    }

    public function registerRoutes(): void
    {
        $controller = new Controller();

        // Standard CRUD routes
        Route::get($this->route(''), [$controller, 'index']);
        Route::get($this->route('/{id}'), [$controller, 'show']);
        Route::post($this->route(''), [$controller, 'store']);
        Route::put($this->route('/{id}'), [$controller, 'update']);
        Route::delete($this->route('/{id}'), [$controller, 'destroy']);
    }
}
```

### 2. Controller.php - HTTP Request Handlers

**Purpose:** Receives HTTP requests and returns responses.

```php
namespace App\Modules\Product;

use App\Core\Controller as BaseController;

class Controller extends BaseController
{
    protected Service $service;

    public function __construct()
    {
        $this->service = new Service();
    }

    // GET /products
    public function index(): array
    {
        $data = $this->service->getAll();
        return $this->success($data, 'Products retrieved successfully');
    }

    // GET /products/{id}
    public function show(string $id): array
    {
        $data = $this->service->getById((int)$id);
        
        if ($data) {
            return $this->success($data, 'Product retrieved successfully');
        }
        
        return $this->error('Product not found', [], 404);
    }

    // POST /products
    public function store(): array
    {
        $requestData = $this->getRequestData();
        $result = $this->service->create($requestData);
        
        if ($result['success']) {
            return $this->success($result['data'], 'Product created successfully', 201);
        }
        
        return $this->error($result['message'], $result['errors'] ?? [], 400);
    }

    // PUT /products/{id}
    public function update(string $id): array
    {
        $requestData = $this->getRequestData();
        $result = $this->service->update((int)$id, $requestData);
        
        if ($result['success']) {
            return $this->success($result['data'], 'Product updated successfully');
        }
        
        return $this->error($result['message'], $result['errors'] ?? [], 400);
    }

    // DELETE /products/{id}
    public function destroy(string $id): array
    {
        $result = $this->service->delete((int)$id);
        
        if ($result['success']) {
            return $this->success([], 'Product deleted successfully');
        }
        
        return $this->error($result['message'], [], 404);
    }
}
```

### 3. Service.php - Business Logic

**Purpose:** Contains business logic, validation, and data processing.

```php
namespace App\Modules\Product;

use App\Core\Service as BaseService;
use App\Modules\Product\Model;

class Service extends BaseService
{
    /**
     * Get all products
     */
    public function getAll(): array
    {
        $products = Model::all(); // ModelCollection
        return [
            'total' => $products->count(),
            'items' => $products
        ];
    }

    /**
     * Get product by ID
     */
    public function getById(int $id): ?array
    {
        $model = Model::find($id);
        return $model ? $model->toArray() : null;
    }

    /**
     * Create new product
     */
    public function create(array $data): array
    {
        // Validation
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }
        
        if (isset($data['price']) && $data['price'] < 0) {
            $errors['price'] = 'Price must be positive';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors
            ];
        }

        // Create using ActiveRecord
        $model = Model::create($data);

        if ($model) {
            return [
                'success' => true,
                'data' => $model->toArray()
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create product'
        ];
    }

    /**
     * Update product
     */
    public function update(int $id, array $data): array
    {
        $model = Model::find($id);
        
        if (!$model) {
            return [
                'success' => false,
                'message' => 'Product not found'
            ];
        }

        // Update attributes
        foreach ($data as $key => $value) {
            $model->$key = $value;
        }

        if ($model->save()) {
            return [
                'success' => true,
                'data' => $model->toArray()
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to update product'
        ];
    }

    /**
     * Delete product
     */
    public function delete(int $id): array
    {
        $model = Model::find($id);
        
        if (!$model) {
            return [
                'success' => false,
                'message' => 'Product not found'
            ];
        }

        if ($model->delete()) {
            return ['success' => true];
        }

        return [
            'success' => false,
            'message' => 'Failed to delete product'
        ];
    }
}
```

### 4. Model.php - Database Operations (ActiveRecord)

**Purpose:** Handles all database operations using ActiveRecord pattern.

```php
namespace App\Modules\Product;

use App\Core\Model as BaseModel;

class Model extends BaseModel
{
    protected string $table = 'products';
    
    // Optional: Define fields for IDE autocomplete
    // public $id;
    // public $name;
    // public $price;
    // public $description;
    // public $created_at;
}
```

**That's it!** The Model class automatically handles all CRUD operations through ActiveRecord methods.

---

## Internal Route Calling (Call Routes Like Functions)

PHP Palm allows you to call module routes internally without HTTP requests - just like calling a function! This is perfect for server-side rendering, internal API calls, and code reuse.

> 📖 **For complete documentation, see [MODULE_INTERNAL_ROUTES.md](MODULE_INTERNAL_ROUTES.md)**

### Quick Start

```php
use App\Modules\Users\Module as UsersModule;

// Call route internally (no HTTP request!)
$users = UsersModule::get('/users');

// Use the data
foreach ($users as $user) {
    echo $user['name'];
}
```

### Available Methods

All Module classes have static methods to call routes:

```php
// GET request
$users = UsersModule::get('/users');
$user = UsersModule::get('/users/1');

// POST request (create)
$newUser = UsersModule::post('/users', [
    'name' => 'John',
    'email' => 'john@example.com'
]);

// PUT request (update)
$updated = UsersModule::put('/users/1', [
    'name' => 'Updated Name'
]);

// DELETE request
$deleted = UsersModule::delete('/users/1');

// PATCH request
$patched = UsersModule::patch('/users/1', ['status' => 'active']);
```

### Use in Views

Perfect for server-side rendering:

```php
<?php
use App\Modules\Product\Module as ProductModule;

// Get products directly (no AJAX needed!)
$products = ProductModule::get('/products');
?>

<div class="products">
    <?php foreach ($products as $product): ?>
        <div class="product">
            <h3><?= htmlspecialchars($product['name']) ?></h3>
            <p><?= htmlspecialchars($product['description']) ?></p>
        </div>
    <?php endforeach; ?>
</div>
```

### Key Features

- ✅ **No HTTP Overhead** - Direct function calls
- ✅ **Automatic Data Extraction** - Intelligently extracts data from responses
- ✅ **ModelCollection Support** - Converts to arrays automatically
- ✅ **Error Handling** - Returns `null` on errors
- ✅ **Same Route Logic** - Uses the same controllers, services, and models

### Benefits

1. **Server-Side Rendering** - Render data in views without AJAX
2. **Code Reuse** - Use route logic in multiple places
3. **Internal API Calls** - Call routes from services or other controllers
4. **Testing** - Test route handlers without HTTP
5. **Performance** - Faster than HTTP requests (no network overhead)

### Example: Complete CRUD

```php
use App\Modules\Users\Module as UsersModule;

// Create
$user = UsersModule::post('/users', [
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

### Frontend Route Calling

You can also call **frontend routes** internally to get HTML output:

```php
use Frontend\Palm\Route;

// Call frontend route and get HTML
$aboutHtml = Route::callGet('/about');
$contactHtml = Route::callPost('/contact', ['name' => 'John']);

// Use in views
echo $aboutHtml;
```

**Frontend vs Module Routes:**
- **Module Routes** (`UsersModule::get()`) - Returns data (arrays)
- **Frontend Routes** (`Route::callGet()`) - Returns HTML (strings)

### How It Works

When you call `UsersModule::get('/users')`:
1. Framework finds the route in the router
2. Sets up temporary request data
3. Executes the route handler (Controller → Service → Model)
4. Extracts and returns the data
5. Cleans up request state

The response format is automatically handled:
- Success responses: `['status' => 'success', 'data' => [...]]` → Returns `[...]`
- Error responses: `['status' => 'error', ...]` → Returns `null`
- Collections: `['items' => ModelCollection]` → Returns array

---

## ActiveRecord Usage

PHP Palm uses the **ActiveRecord pattern** - you don't need to write SQL queries! The Model class handles all database operations through intuitive PHP methods.

> 📖 **For complete ActiveRecord documentation, see [ACTIVERECORD_USAGE.md](ACTIVERECORD_USAGE.md)**

### Quick Start

#### Basic CRUD Operations

```php
use App\Modules\Product\Model;

// Create
$product = Model::create([
    'name' => 'Laptop',
    'price' => 999.99,
    'status' => 'active'
]);

// Read - Get all
$products = Model::all(); // Returns ModelCollection

// Read - Find by ID
$product = Model::find(1);

// Read - Query with conditions
$activeProducts = Model::where('status', 'active')->all();

// Update
$product = Model::find(1);
$product->name = 'Updated Laptop';
$product->save();

// Delete
$product = Model::find(1);
$product->delete();
```

### Query Building

PHP Palm provides a fluent query builder for complex queries:

```php
// Simple queries
$products = Model::where('status', 'active')->all();
$products = Model::where('price', '>', 100)->all();

// Multiple conditions
$products = Model::where('status', 'active')
    ->where('price', '>', 50)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->all();

// OR conditions
$products = Model::where('status', 'active')
    ->orWhere('status', 'pending')
    ->all();

// WHERE IN
$products = Model::whereIn('id', [1, 2, 3, 4, 5])->all();

// Search
$products = Model::search('laptop', ['name', 'description'])->all();

// Count and exists
$count = Model::where('status', 'active')->count();
$exists = Model::where('id', 1)->exists();
```

### Model Collections

All query results return a `ModelCollection` that behaves like an array:

```php
$products = Model::all();

// Array-like access
$first = $products[0];
$total = $products->count();

// Collection methods
$first = $products->first();
$array = $products->toArray();
$names = $products->map(fn($p) => $p->name);

// Iterate
foreach ($products as $product) {
    echo $product->name;
}
```

### Relationships

Define relationships in your models:

```php
// In User Model
    public function posts()
    {
        return $this->hasMany(PostModel::class, 'user_id');
    }

    public function profile()
    {
        return $this->hasOne(ProfileModel::class, 'user_id');
    }

    public function company()
    {
        return $this->belongsTo(CompanyModel::class, 'company_id');
}
```

Access relationships:

```php
// Lazy loading
$user = UserModel::find(1);
$posts = $user->posts; // Loads when accessed

// Eager loading (recommended for performance)
$users = UserModel::with('posts')->all(); // Avoids N+1 queries
```

### Available Methods

**Static Methods:**
- `Model::all()` - Get all records
- `Model::find($condition)` - Find by ID or condition
- `Model::findOne($condition)` - Find single record
- `Model::findOrFail($condition)` - Find or throw exception
- `Model::where($column, $operator, $value)` - Start query builder
- `Model::create($attributes)` - Create new record
- `Model::count($condition)` - Count records
- `Model::exists($condition)` - Check if exists

**Instance Methods:**
- `$model->save()` - Save (insert or update)
- `$model->update($attributes)` - Update record
- `$model->delete()` - Delete record
- `$model->toArray()` - Convert to array

**Query Builder Methods:**
- `->where()`, `->orWhere()`, `->whereIn()`, `->whereNotIn()`
- `->orderBy()`, `->limit()`, `->offset()`, `->skip()`, `->take()`
- `->select()`, `->search()`, `->with()`
- `->asModels()`, `->asObjects()`, `->asArrays()`
- `->all()`, `->one()`, `->count()`, `->exists()`

### Performance Tips

1. **Use Eager Loading**: Always use `with()` to avoid N+1 queries
2. **Select Specific Columns**: Use `select()` to limit data transfer
3. **Connection Pooling**: Framework automatically reuses connections
4. **Query Caching**: Automatic caching with APCu when available

### Complete Documentation

For detailed documentation including:
- All available methods and parameters
- Advanced query examples
- Relationship definitions and usage
- Performance optimization techniques
- Best practices and patterns

See **[ACTIVERECORD_USAGE.md](ACTIVERECORD_USAGE.md)** for the complete guide.

---

## Palm CLI Commands

PHP Palm includes a powerful CLI tool to generate code automatically via the `palm` command.

### Quick Start (Recommended)

Use the `palm.bat` launcher for better console readability on Windows:

```batch
palm.bat serve
palm.bat make view home.about
palm.bat help
```

**💡 Tip**: For best readability, run `palm-console-setup.bat` once to configure your console font. See [Console Setup Guide](README_CONSOLE_SETUP.md).

Or use PHP directly:

```bash
php app/scripts/palm.php <command> [arguments]
```

### Main Command

```bash
palm <command> [arguments]
```

### Available Commands

#### 0. Scaffold Frontend (`src/`)

Creates or refreshes the `src/` directory that powers the Palm frontend router.

```bash
palm make frontend
```

**What you get (created if missing, skipped otherwise):**

| Path | Description |
|------|-------------|
| `src/routes/main.php` | Boots the frontend router using `app/Palm/Route.php` |
| `src/layouts/main.php` | Default layout with nav |
| `src/views/home/{index,about,contact}.palm.php` | Example views |

The command is idempotent—existing files are left untouched so you can re-run it safely after upgrading Palm.

#### 1. Create Complete Module

Creates a full module with all 4 files (Module, Controller, Service, Model) with CRUD operations.

```bash
palm make module <ModuleName> [route-prefix]
```

**Examples:**
```bash
# Create Product module with /products route
palm make module Product /products

# Create Order module with /orders route
palm make module Order /orders

# Create Category module (route will be /categories by default)
palm make module Category

# Module name can be lowercase (auto-converted)
palm make module products /products
```

**What gets created:**
- ✅ `modules/Product/Module.php` - Route definitions
- ✅ `modules/Product/Controller.php` - HTTP handlers with CRUD methods
- ✅ `modules/Product/Service.php` - Business logic with validation
- ✅ `modules/Product/Model.php` - Database model (ActiveRecord)

#### 2. Create Controller Only

Adds a controller to an existing module.

```bash
palm make controller <ModuleName> <ControllerName>
```

**Example:**
```bash
palm make controller Product ProductController
```

**Note:** Creates `Controller.php` in the module folder. The module must exist first.

#### 3. Create Model Only

Adds a model to an existing module.

```bash
palm make model <ModuleName> <ModelName> [table-name]
```

**Examples:**
```bash
# Create model with default table name (ProductModels)
palm make model Product ProductModel

# Create model with custom table name
palm make model Product ProductModel products
```

#### 4. Create Service Only

Adds a service to an existing module.

```bash
palm make service <ModuleName> <ServiceName>
```

**Example:**
```bash
palm make service Product ProductService
```

#### 5. Generate from Database Tables

Automatically generate modules from your database tables.

```bash
palm make usetable all
```

**What it does:**
- Scans all tables in your database
- Creates modules for each table
- Auto-generates Model with all fields from database
- Creates complete CRUD operations

**Example:**
```bash
# Generate modules for ALL tables
palm make usetable all

# This will create:
# - modules/User/ (from users table)
# - modules/Product/ (from products table)
# - modules/Order/ (from orders table)
# etc.
```

### Command Summary

| Command | Description | Example |
|---------|-------------|---------|
| `palm make frontend` | Scaffold `src/` layouts, views, SPA assets | `palm make frontend` |
| `palm make module <Name> [prefix]` | Create complete module | `palm make module Product /products` |
| `palm make controller <Module> <Name>` | Create controller | `palm make controller Product ProductController` |
| `palm make model <Module> <Name> [table]` | Create model | `palm make model Product ProductModel products` |
| `palm make service <Module> <Name>` | Create service | `palm make service Product ProductService` |
| `palm make usetable all` | Generate from database | `palm make usetable all` |

---

## Frontend Scaffolding (src/)

Need a landing page and layout without wiring everything manually? Run:

```bash
palm make frontend
```

This drops a ready-to-edit `src/` tree that mirrors the structure used in this repo:

```
src/
├── routes/
│   └── main.php             # Route::get('/about', Route::view('home.about')) etc.
├── layouts/
│   └── main.php             # Shared shell with nav
└── views/
    └── home/
        ├── index.palm.php   # Home page
        ├── about.palm.php   # About page
        └── contact.palm.php # Contact form
```

All frontend Palm classes are autoloaded via Composer (PSR-4), so you can use `use Frontend\Palm\Route;` and other classes directly without require statements. The scaffold uses the latest runtime from `app/Palm/`—you never end up with duplicate vendor code inside `src/`.

**Typical flow**
1. Run `palm make frontend`.
2. Map non-API requests to `src/routes/main.php` (already done in `index.php`).
3. Customize the views/layouts, or add new view files under `src/views/<folder>/<view>.palm.php`.

Whenever Palm ships new frontend helpers, re-run `palm make frontend`; new files are added, existing files stay untouched.

## Frontend Router

Palm ships with a lightweight PHP router so you can define clean URLs
directly in `src/main.php`—no query strings and no client-side JS
needed:

```php
use Frontend\Route;

Route::init(__DIR__);

Route::get('/', Route::view('home.home', ['title' => 'Welcome']));
Route::get('/about', Route::view('about.about'));

Route::post('/contact', function () {
    Route::render('contact.contact', [
        'flash' => 'Thanks! We will reply soon.',
        'prefill' => ['name' => $_POST['name'] ?? '', 'message' => $_POST['message'] ?? ''],
    ]);
});

Route::dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
```

- Views live in `src/views/<folder>/<view>.palm.php` (e.g. `home.index`).
- Layouts live in `src/layouts/`.
- `Route::view($slug, $data)` returns a closure to render a view; `Route::render`
  lets you return a view from inside a POST handler.
- All views are server-rendered PHP files—no JavaScript build step required.

---

## Google Authentication

PHP Palm includes easy-to-use Google OAuth authentication with helper functions and automatic OAuth flow handling.

### Quick Setup

1. **Create Google OAuth Credentials**
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Create a project and enable Google+ API
   - Create OAuth 2.0 Client ID credentials
   - Set authorized redirect URI: `http://localhost:8000/auth/google/callback`

2. **Configure Environment Variables**

   Add to `config/.env`:
   ```env
   GOOGLE_CLIENT_ID=your-client-id-here
   GOOGLE_CLIENT_SECRET=your-client-secret-here
   GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
   ```

3. **Use in Your Views**

   ```php
   <?php
   require_once dirname(__DIR__, 2) . '/app/Palm/helpers.php';
   
   if (google_auth_check()): 
       $user = google_auth_user();
   ?>
       <p>Welcome, <?= htmlspecialchars($user['name']) ?>!</p>
       <img src="<?= htmlspecialchars($user['picture']) ?>" alt="Profile">
       <a href="/auth/google/logout">Logout</a>
   <?php else: ?>
       <a href="/auth/google">Login with Google</a>
   <?php endif; ?>
   ```

### Available Routes

The following routes are automatically available when you scaffold the frontend:

- `GET /auth/google` - Redirect to Google login
- `GET /auth/google/callback` - Handle OAuth callback (auto-configured)
- `GET /auth/google/logout` - Logout user

### Helper Functions

```php
// Check if authenticated
google_auth_check(): bool

// Get user data
google_auth_user(): ?array
// Returns: ['id', 'email', 'name', 'picture', 'verified_email']

// Get specific user fields
google_auth_id(): ?string        // User ID
google_auth_email(): ?string     // Email address
google_auth_name(): ?string      // Full name
google_auth_picture(): ?string   // Profile picture URL

// Get auth URL
google_auth_url(): string

// Redirect to Google login
google_auth_redirect(): void

// Logout
google_auth_logout(): void
```

### Protecting Routes

```php
use Frontend\Palm\Route;
require_once dirname(__DIR__, 2) . '/app/Palm/helpers.php';

Route::get('/dashboard', function () {
    if (!google_auth_check()) {
        header('Location: /auth/google?redirect=' . urlencode('/dashboard'));
        exit;
    }
    
    $user = google_auth_user();
    Route::render('dashboard', [
        'title' => 'Dashboard',
        'user' => $user,
    ]);
});
```

### Advanced Usage

**Custom Scopes:**
```php
use Frontend\Palm\GoogleAuth;

$url = GoogleAuth::getAuthUrl([
    'openid', 
    'email', 
    'profile',
    'https://www.googleapis.com/auth/calendar'
]);
```

**Manual Initialization:**
```php
use Frontend\Palm\GoogleAuth;

GoogleAuth::init(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret',
    redirectUri: 'http://localhost:8000/auth/google/callback'
);
```

### Features

- ✅ **Automatic OAuth Flow** - Handles the complete OAuth 2.0 flow
- ✅ **CSRF Protection** - State parameter validation
- ✅ **Session Management** - Secure session-based storage
- ✅ **Token Refresh** - Automatic token renewal
- ✅ **Easy Helpers** - Simple functions for common operations
- ✅ **User Data** - Access to ID, email, name, picture

### Documentation

For detailed documentation, examples, and troubleshooting, see **[GOOGLE_AUTH_USAGE.md](GOOGLE_AUTH_USAGE.md)**.

---

## Building CRUD APIs

Let's build a complete CRUD API step by step.

### Step 1: Create the Module

```bash
palm make module Product /products
```

This creates all 4 files automatically.

### Step 2: Create Database Table

```sql
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Step 3: Update Model Table Name

Open `modules/Product/Model.php` and verify:

```php
protected string $table = 'products';
```

### Step 4: Add Validation in Service

Open `modules/Product/Service.php` and update the `create()` method:

```php
public function create(array $data): array
{
    // Validation
    $errors = [];
    
    if (empty($data['name'])) {
        $errors['name'] = 'Name is required';
    }
    
    if (empty($data['price']) || !is_numeric($data['price'])) {
        $errors['price'] = 'Valid price is required';
    } elseif ($data['price'] < 0) {
        $errors['price'] = 'Price must be positive';
    }

    if (!empty($errors)) {
        return [
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ];
    }

    // Create using ActiveRecord
    $model = Model::create($data);

    if ($model) {
        return [
            'success' => true,
            'data' => $model->toArray()
        ];
    }

    return [
        'success' => false,
        'message' => 'Failed to create product'
    ];
}
```

### Step 5: Test Your API

Start the server:
```bash
serve.bat
```

Test the endpoints:

```bash
# Get all products
GET http://localhost:8000/api/products

# Get one product
GET http://localhost:8000/api/products/1

# Create product
POST http://localhost:8000/api/products
Content-Type: application/json

{
    "name": "Laptop",
    "price": 999.99,
    "description": "Gaming laptop",
    "status": "active"
}

# Update product
PUT http://localhost:8000/api/products/1
Content-Type: application/json

{
    "name": "Updated Laptop",
    "price": 899.99
}

# Delete product
DELETE http://localhost:8000/api/products/1
```

### Complete CRUD Example Response

**GET /api/products**
```json
{
    "status": "success",
    "message": "Products retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "Laptop",
            "price": "999.99",
            "description": "Gaming laptop",
            "status": "active",
            "created_at": "2024-01-15 10:30:00"
        }
    ]
}
```

**POST /api/products** (Success)
```json
{
    "status": "success",
    "message": "Product created successfully",
    "data": {
        "id": 1,
        "name": "Laptop",
        "price": "999.99",
        "description": "Gaming laptop",
        "status": "active",
        "created_at": "2024-01-15 10:30:00"
    }
}
```

**POST /api/products** (Validation Error)
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "name": "Name is required",
        "price": "Valid price is required"
    }
}
```

---

## Request & Response

### Getting Request Data

#### In Controllers

```php
// Get all request data (JSON or form data)
$data = $this->getRequestData();

// Returns array of all input data
```

#### Using Request Class Directly

```php
use PhpPalm\Core\Request;

// Get JSON data
$json = Request::getJson();

// Get POST data
$name = Request::post('name', 'Default Name');
$allPost = Request::post(); // All POST data

// Get GET/Query parameters
$id = Request::get('id');
$allGet = Request::get(); // All GET data

// Get input from any source (GET, POST, body)
$value = Request::input('key', 'default');

// Get specific headers
$token = Request::bearerToken();
$apiKey = Request::apiKey();
$referrer = Request::referrer();
$userAgent = Request::userAgent();

// Get file uploads
$file = Request::files('image');

// Check request type
if (Request::isPost()) {
    // Handle POST
}

if (Request::isJson()) {
    // Request is JSON
}

if (Request::isAjax()) {
    // Request is AJAX
}
```

### Request Helper Methods

```php
// Type casting
$age = Request::integer('age', 0);
$price = Request::float('price', 0.0);
$isActive = Request::boolean('active', false);
$name = Request::string('name', '');

// Check existence
if (Request::has('email')) {
    // Key exists
}

if (Request::filled('email')) {
    // Key exists and is not empty
}

// Get only specific keys
$data = Request::only(['name', 'email', 'phone']);

// Get all except specific keys
$data = Request::except(['password', 'token']);

// Get all input
$all = Request::all();
```

### Sending Responses

#### In Controllers

```php
// Success response
return $this->success($data, 'Message', 200);

// Error response
return $this->error('Error message', ['field' => 'error'], 400);

// Custom JSON
return $this->json(['custom' => 'data'], 201);
```

#### Response Format

**Success:**
```json
{
    "status": "success",
    "message": "Products retrieved successfully",
    "data": [...]
}
```

**Error:**
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "name": "Name is required"
    }
}
```

---

## Examples & Use Cases

### Example 1: E-commerce API

```bash
# Create modules
palm make module Product /products
palm make module Order /orders
palm make module Category /categories
palm make module User /users
```

### Example 2: Blog API

```bash
# Create modules
palm make module Post /posts
palm make module Comment /comments
palm make module Author /authors
```

### Example 3: Custom Route in Module

Add custom routes to your module:

```php
// In Module.php
public function registerRoutes(): void
{
    $controller = new Controller();

    // Standard CRUD
    Route::get($this->route(''), [$controller, 'index']);
    Route::get($this->route('/{id}'), [$controller, 'show']);
    
    // Custom routes
    Route::get($this->route('/featured'), [$controller, 'featured']);
    Route::get($this->route('/search/{query}'), [$controller, 'search']);
    Route::post($this->route('/{id}/publish'), [$controller, 'publish']);
}
```

Add methods to Controller:

```php
public function featured(): array
{
    $products = $this->service->getFeatured();
    return $this->success($products, 'Featured products retrieved');
}

public function search(string $query): array
{
    $products = $this->service->search($query);
    return $this->success($products, 'Search results');
}
```

### Example 4: Advanced Querying

```php
// In Service.php
public function getFeatured(): array
{
    $products = Model::where('featured', 1)
        ->where('status', 'active')
        ->orderBy('created_at', 'DESC')
        ->limit(10)
        ->all();
    
    return [
        'total' => $products->count(),
        'items' => $products
    ];
}

public function search(string $query): array
{
    $results = Model::where('name', 'LIKE', "%{$query}%")
        ->orWhere('description', 'LIKE', "%{$query}%")
        ->orderBy('name', 'ASC')
        ->all();
    
    return [
        'total' => $results->count(),
        'items' => $results
    ];
}
```

---

## Best Practices

### 1. Keep Controllers Thin

✅ **Good:**
```php
public function store(): array
{
    $data = $this->getRequestData();
    $result = $this->service->create($data);
    return $result['success'] 
        ? $this->success($result['data'], 'Created', 201)
        : $this->error($result['message'], $result['errors']);
}
```

❌ **Bad:**
```php
public function store(): array
{
    $data = $this->getRequestData();
    // Don't put business logic here!
    if (empty($data['name'])) {
        return $this->error('Name required');
    }
    // ... more logic
}
```

### 2. Validate in Services

Always validate in Service layer, not Controller:

```php
// In Service.php
public function create(array $data): array
{
    $errors = [];
    
    if (empty($data['name'])) {
        $errors['name'] = 'Name is required';
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Validation failed', 'errors' => $errors];
    }
    
    // Create...
}
```

### 3. Use ActiveRecord Methods

✅ **Good:**
```php
$product = Model::find(1);
$product->name = 'New Name';
$product->save();
```

❌ **Bad:**
```php
$db = $this->getDb();
$db->query("UPDATE products SET name = 'New Name' WHERE id = 1");
```

### 4. Use Eager Loading for Relationships

✅ **Good:**
```php
$users = UserModel::with('posts')->all(); // One query
```

❌ **Bad:**
```php
$users = UserModel::all();
foreach ($users as $user) {
    $user->posts; // N+1 queries!
}
```

### 5. Organize by Modules

One feature = One module:
- ✅ `modules/Product/` - Product feature
- ✅ `modules/Order/` - Order feature
- ✅ `modules/User/` - User feature

---

## Troubleshooting

### Module Not Loading

**Problem:** Module routes not working

**Solution:**
1. Check module folder exists in `modules/`
2. Verify `Module.php` exists and extends `App\Core\Module`
3. Check `registerRoutes()` method is public
4. Run `composer dump-autoload`
5. Restart the server

### Route Not Found (404)

**Problem:** Getting 404 errors

**Solution:**
1. Check route prefix in module constructor
2. Verify routes are registered in `registerRoutes()`
3. Make sure you're accessing `/api/your-route`
4. Check controller methods are public
5. Look at error response - it shows available routes

### Database Connection Error

**Problem:** Can't connect to database

**Solution:**
1. Check `.env` file in `config/` folder
2. Verify database credentials
3. Make sure database exists
4. Check MySQL service is running
5. Verify `DATABASE_NAME` matches your database name

### Class Not Found

**Problem:** `Class 'App\Modules\...' not found`

**Solution:**
```bash
composer dump-autoload
```

### ActiveRecord Methods Not Working

**Problem:** `Model::all()` or other methods not working

**Solution:**
1. Make sure Model extends `App\Core\Model`
2. Verify `$table` property is set
3. Check database connection is working
4. Ensure table exists in database

### Port Already in Use

**Problem:** Can't start server on port 8000

**Solution:**
Edit `serve.bat`:
```batch
set "PORT=8001"
```

Or use a different port:
```bash
php -S localhost:8001
```

---

## Quick Reference

### Create Module
```bash
palm make module Product /products
```

### Start Server
```bash
serve.bat
# or
php -S localhost:8000
```

### Common ActiveRecord Methods
```php
Model::all()                          // Get all
Model::find(1)                        // Find by ID
Model::where('status', 'active')->all() // Query
Model::create(['name' => 'Test'])     // Create
$model->save()                        // Update
$model->delete()                      // Delete
```

### Common Request Methods
```php
Request::getJson()                    // Get JSON
Request::post('key')                  // Get POST
Request::get('key')                   // Get GET
Request::input('key')                 // Get any input
Request::bearerToken()                // Get Bearer token
```

### Common Response Methods (in Controllers)
```php
$this->success($data, 'Message')      // Success
$this->error('Message', [], 400)      // Error
$this->json(['data'], 200)            // Custom
```

---

## Additional Resources

### Documentation Files

- **`ACTIVERECORD_USAGE.md`** - Complete ActiveRecord ORM guide with comprehensive examples, API reference, and best practices
- **`MODULE_INTERNAL_ROUTES.md`** - Guide to calling module routes internally without HTTP requests (like functions)
- **`GOOGLE_AUTH_USAGE.md`** - Complete guide to Google OAuth authentication setup and usage
- **`middlewares/README.md`** - Middleware development documentation
- **`app/Core/Security/README.md`** - Security features and implementation guide

### Code Examples

- **Example Modules** - Check `modules/Users/` for a complete example
- **Base Classes** - Explore `app/Core/` to understand framework internals
- **Routes** - See `routes/api.php` for simple route examples

### Architecture Overview

```
Request Flow:
1. index.php (Entry Point)
   ↓
2. Router (Route Matching)
   ↓
3. Middleware (Optional - Auth, Rate Limit, etc.)
   ↓
4. Controller (Request Handler)
   ↓
5. Service (Business Logic)
   ↓
6. Model (Database Operations - ActiveRecord)
   ↓
7. Response (JSON/View)
```

### Framework Components

- **Routing**: `app/Core/Route.php`, `app/Core/Router.php`
- **Database**: `app/Database/Db.php`, `app/Core/Model.php`
- **Authentication**: `app/Core/Auth.php`, `app/Palm/GoogleAuth.php`
- **Middleware**: `app/Core/Middleware.php`, `app/Core/MiddlewareHelper.php`
- **Request/Response**: `app/Core/Request.php`, `app/Core/Controller.php`
- **Frontend**: `app/Palm/Route.php`, `app/Palm/helpers.php`

### Getting Help

1. **Check Documentation**: Start with this README and specialized guides
2. **Review Examples**: Look at `modules/Users/` for a complete module example
3. **Error Messages**: Framework provides detailed error messages with debugging info
4. **Code Inspection**: Explore `app/Core/` to understand how things work internally

### Contributing

If you find bugs or want to contribute:
1. Check existing issues
2. Follow the code style used in the framework
3. Write clear commit messages
4. Test your changes thoroughly

---

**Happy Coding! 🚀**

For questions or issues:
1. Check this README first
2. Review specialized guides (ACTIVERECORD_USAGE.md, AUTH_GUIDE.md, etc.)
3. Review example modules in `modules/`
4. Check error messages - they include helpful debugging info!
5. Explore `app/Core/` to understand framework internals
"# php-palm" 
"# php-palm" 

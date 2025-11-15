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
7. [ActiveRecord Usage](#activerecord-usage)
8. [Palm CLI Commands](#palm-cli-commands)
9. [Frontend Scaffolding (src/)](#frontend-scaffolding-src)
10. [Building CRUD APIs](#building-crud-apis)
11. [Request & Response](#request--response)
12. [Examples & Use Cases](#examples--use-cases)
13. [Best Practices](#best-practices)
14. [Troubleshooting](#troubleshooting)

---

## Introduction

### What is PHP Palm?

PHP Palm is a PHP framework that helps you build RESTful APIs quickly and easily. It uses a **modular architecture**, which means you organize your code into self-contained modules (like building blocks).

### Key Features

✅ **Dual Routing System** - Simple routes OR modular architecture (your choice!)  
✅ **Modular Architecture** - Organize code into modules (like NestJS)  
✅ **Auto-Generated Code** - Create modules with one command  
✅ **ActiveRecord Pattern** - Easy database operations without writing SQL  
✅ **Built-in Security** - Rate limiting, CORS, security headers  
✅ **Comprehensive Error Handling** - Automatic error catching with detailed responses  
✅ **CLI Tools** - Generate code with `palm` commands (`palm make ...`)  
✅ **Frontend Router** - Clean PHP Route::get()/Route::post() helpers for `/about`, `/contact`, etc.  
✅ **Beginner Friendly** - Clear structure and documentation  

---

## Installation & Setup

### Prerequisites

- PHP 7.4 or higher
- Composer (PHP package manager)
- MySQL (or any database)
- Web server (Apache/Nginx) or PHP built-in server

### Step 1: Install Dependencies

```bash
composer install
```

This installs all required packages including:
- `vlucas/phpdotenv` - Environment variable management
- `php-palm/core` - Core routing and framework components

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

## ActiveRecord Usage

PHP Palm uses ActiveRecord pattern - you don't need to write SQL queries! The Model class handles everything.

### Basic Operations

#### Get All Records

```php
use App\Modules\Product\Model;

// Get all products (returns ModelCollection)
$products = Model::all(); // ModelCollection

// Iterate like an array
foreach ($products as $product) {
    echo $product->name;
    echo $product->price;
}

// Collection helpers
$total = $products->count();
$first = $products->first();
$asArray = $products->toArray();

// Need lightweight stdClass objects or raw arrays?
$objects = Model::query()->asObjects()->all(); // [ (object) ['name' => 'Laptop'], ... ]
$arrays  = Model::query()->asArrays()->all();  // [ ['name' => 'Laptop'], ... ]

// Collections also provide helpers like map(), first(), toArray()
$names = $products->map(fn($model) => $model->name);
```

##### ModelCollection Quick Reference
- `count()` – total results (implements `Countable`)
- `first()` / `all()` – quick access helpers
- `map(callable)` – transform each record while preserving collection semantics
- Array access (`$products[0]`) and `foreach` friendly
- Implements `JsonSerializable`, so you can return a collection directly from controllers/services

#### Find by ID

```php
// Find single record
$product = Model::find(1);

if ($product) {
    echo $product->name;
}

// Find or throw exception
$product = Model::findOrFail(1); // Throws exception if not found
```

#### Query with Conditions

```php
// Simple where
$activeProducts = Model::where('status', 'active')->all();

// Where with operator
$expensiveProducts = Model::where('price', '>', 100)->all();

// Multiple conditions (AND)
$products = Model::where('status', 'active')
    ->andWhere('price', '>', 50)
    ->all();

// OR conditions
$products = Model::where('status', 'active')
    ->orWhere('status', 'pending')
    ->all();

// Array where (multiple AND)
$products = Model::where([
    'status' => 'active',
    'featured' => 1
])->all();

// Filter helper (alias of where) keeps chains expressive
$products = Model::filter(['status' => 'active'])
    ->filter('price', '>', 50)
    ->all();

// Use a callable with filter for complex logic
$products = Model::filter(function ($query) {
    $query->where('status', 'active')
        ->orWhere('status', 'pending');
})->all();

// WHERE IN
$products = Model::whereIn('id', [1, 2, 3, 4, 5])->all();

// WHERE NOT IN
$products = Model::whereNotIn('status', ['deleted', 'archived'])->all();
```

#### Ordering and Limiting

```php
// Order by
$products = Model::where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->orderBy('name', 'ASC')
    ->all();

// Limit results
$products = Model::where('status', 'active')
    ->limit(10)
    ->offset(20) // Skip first 20
    ->all();

// Pagination aliases (skip / take)
$products = Model::skip(20)  // same as offset(20)
    ->take(10)               // same as limit(10)
    ->orderBy('created_at', 'DESC')
    ->all();

// Select specific columns
$products = Model::select(['id', 'name', 'price'])
    ->where('status', 'active')
    ->all();
```

#### Count and Exists

```php
// Count records
$count = Model::where('status', 'active')->count();

// Check if exists
$exists = Model::where('id', 1)->exists();

// Search across multiple columns (LIKE %term%)
$term = 'laptop';
$products = Model::search($term, ['name', 'description'])->all();
```

### Creating Records

```php
// Create new record
$product = Model::create([
    'name' => 'Laptop',
    'price' => 999.99,
    'description' => 'Gaming laptop',
    'status' => 'active'
]);

// Returns Model instance with ID
echo $product->id;
```

### Updating Records

```php
// Method 1: Update using save()
$product = Model::find(1);
$product->name = 'Updated Laptop';
$product->price = 899.99;
$product->save();

// Method 2: Update using update() method
$product = Model::find(1);
$product->update([
    'name' => 'Updated Laptop',
    'price' => 899.99
]);
```

### Deleting Records

```php
// Delete record
$product = Model::find(1);
$product->delete();

// Returns true on success, false on failure
```

### Relationships

#### Define Relationships

```php
namespace App\Modules\User;

use App\Core\Model as BaseModel;

class Model extends BaseModel
{
    protected string $table = 'users';

    // User has many posts
    public function posts()
    {
        return $this->hasMany(PostModel::class, 'user_id');
    }

    // User has one profile
    public function profile()
    {
        return $this->hasOne(ProfileModel::class, 'user_id');
    }

    // User belongs to company
    public function company()
    {
        return $this->belongsTo(CompanyModel::class, 'company_id');
    }
}
```

#### Access Relationships

```php
$user = UserModel::find(1);

// Lazy loading (loads when accessed)
$posts = $user->posts;        // Array of PostModel
$profile = $user->profile;    // ProfileModel or null
$company = $user->company;     // CompanyModel or null
```

#### Eager Loading (Performance Optimization)

```php
// Load users with their posts (avoids N+1 query problem)
$users = UserModel::with('posts')->all();

// Load multiple relationships
$users = UserModel::with(['posts', 'profile', 'company'])->all();

// Access relationships (already loaded)
foreach ($users as $user) {
    echo $user->name;
    foreach ($user->posts as $post) {
        echo $post->title;
    }
}
```

### Converting to Array/JSON

```php
$product = Model::find(1);

// Convert to array (includes relationships if loaded)
$array = $product->toArray();

// For JSON response
return json_encode($product->toArray());
```

### Complete Example

```php
// Get active products with price > 50, ordered by name, limit 10
$products = Model::where('status', 'active')
    ->where('price', '>', 50)
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->all();

// Process products
foreach ($products as $product) {
    echo "Name: {$product->name}\n";
    echo "Price: {$product->price}\n";
    
    // Convert to array for API response
    $data[] = $product->toArray();
}

return $this->success($data, 'Products retrieved successfully');
```

---

## Palm CLI Commands

PHP Palm includes a powerful CLI tool to generate code automatically via the `palm` command.

### Main Command

```bash
palm <command> [arguments]
```

### Available Commands

#### 0. Scaffold Frontend (SPA-ready `src/`)

Creates or refreshes the `src/` directory that powers the Palm frontend router.

```bash
palm make frontend
```

**What you get (created if missing, skipped otherwise):**

| Path | Description |
|------|-------------|
| `src/main.php` | Boots the frontend router using `app/Palm/Route.php` |
| `src/layouts/main.php` | Default layout with nav + SPA placeholders |
| `src/views/home/{home,about,contact}.php` | Example views showing state/actions |
| `public/palm-assets/palm-spa.js` | Lightweight runtime for SPA navigation |

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

Need a landing page, layout, and SPA runtime without wiring everything manually? Run:

```bash
palm make frontend
```

This drops a ready-to-edit `src/` tree that mirrors the structure used in this repo:

```
src/
├── main.php                 # Route::get('/about', Route::view('home.about')) etc.
├── layouts/
│   └── main.php             # Shared shell with nav + palm-spa snippets
└── views/
    └── home/
        ├── home.php         # Rich state/action example
        ├── about.php        # Stateful text/content demo
        └── contact.php      # Classic form with flash handling
public/
└── palm-assets/
    └── palm-spa.js          # Lightweight SPA runtime shipped with every scaffold
```

Because the scaffold points to `app/Palm/helpers.php` and `app/Palm/Route.php`, it always uses the latest runtime—you never end up with duplicate vendor code inside `src/`.

**Typical flow**
1. Run `palm make frontend`.
2. Map non-API requests to `src/main.php` (already done in `index.php`).
3. Customize the views/layouts, or add new view files under `src/views/<folder>/<view>.php`.
4. Link between pages with `<a palm-spa-link>` to keep instant navigation.

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

- Views live in `src/views/<folder>/<view>.php` (e.g. `home.home`).
- Layouts live in `src/layouts/`.
- `Route::view($slug, $data)` returns a closure to render a view; `Route::render`
  lets you return a view from inside a POST handler.
- Palm automatically pre-renders every registered view and ships it to the browser.
  Add `palm-spa-link` to anchors (e.g. `<a href="/about" palm-spa-link>About</a>`)
  and `public/palm-assets/palm-spa.js` will swap the DOM via `history.pushState` without extra
  fetch calls.
- POST forms submit via the SPA runtime automatically. Use
  `<form data-spa-form="false">...</form>` if you need a classic full-page POST.

### View State (PHP-authored)

Use the runtime helpers in your PHP views—no custom JS or attributes needed.

```php
<?php
$count = State(0);

Action('add', function () use ($count) {
    $count->increment();
});

Action('subtract', function () use ($count) {
    $count->decrement();
});
?>

<button type="button" onclick="subtract">−</button>
Total <?= $count ?>
<button type="button" onclick="add">+</button>
```

`State()` registers a local, component-scoped state slot. `Action()` captures a PHP
closure that mutates your state (`set`, `increment`, `decrement`, `toggle`). Palm
compiles those actions to JavaScript, builds a virtual DOM description for the
component, and keeps the DOM in sync automatically. You only write PHP; Palm turns
the markup + state into a SPA at runtime.

Run `palm make frontend` to scaffold the layout, router, SPA runtime, and sample views under `src/`.

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

- **`ACTIVERECORD_USAGE.md`** - Detailed ActiveRecord guide
- **`SCRIPTS_UPGRADE.md`** - Script upgrade documentation
- **Example Modules** - Check `modules/` folder
- **Base Classes** - Explore `app/Core/` to understand framework

---

**Happy Coding! 🚀**

For questions or issues:
1. Check this README first
2. Review example modules in `modules/`
3. Check error messages - they include helpful debugging info!
4. Review `ACTIVERECORD_USAGE.md` for database operations
"# php-palm" 
"# php-palm" 

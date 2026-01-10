# 🌴 PHP Palm Framework - Complete Documentation

**Modern, Modular, Developer-Friendly PHP Framework**

---

## 📚 Table of Contents

1. [Quick Start](#quick-start)
2. [CLI Commands](#cli-commands)
3. [Module System](#module-system)
4. [Model Validation](#model-validation)
5. [ActiveRecord ORM](#activerecord-orm)
6. [Routing](#routing)
7. [Frontend Features](#frontend-features)
8. [Database & Migrations](#database--migrations)
9. [Security](#security)
10. [Performance](#performance)
11. [Best Practices](#best-practices)

---

## 🚀 Quick Start

### Installation
```bash
# Install dependencies
composer install

# Create environment configuration
cp config/.env.example config/.env
# Edit config/.env with your database credentials

# Start development server
palm serve
```

Your application is now running at `http://localhost:8000`!

---

## 🛠 CLI Commands

Palm CLI (`palm`) provides powerful code generation and management tools.

### Code Generation

| Command | Description | Example |
|---------|-------------|---------|
| `palm make:module <Name>` | Full module (Controller/Service/Model) | `palm make:module Products` |
| `palm make:model <Module> <Name>` | Model with validation | `palm make:model Products Product` |
| `palm make:controller <Module> <Name>` | Controller only | `palm make:controller Products ProductController` |
| `palm make:service <Module> <Name>` | Service only | `palm make:service Products ProductService` |
| `palm make:middleware <Name>` | Middleware | `palm make:middleware AuthMiddleware` |
| `palm make:frontend` | Scaffold frontend structure | `palm make:frontend` |
| `palm make:view <name>` | Create view file | `palm make:view home.about` |
| `palm make:component <Name>` | Create component | `palm make:component Button` |
| `palm make:pwa [name]` | Generate PWA files | `palm make:pwa MyApp` |

### Database & Migrations

| Command | Description |
|---------|-------------|
| `palm make:migration <name>` | Create migration file |
| `palm migrate` | Run all pending migrations |
| `palm migrate:rollback` | Rollback last migration |
| `palm migrate:reset` | Rollback all migrations |
| `palm migrate:refresh` | Reset and re-run all migrations |
| `palm migrate:status` | Show migration status |
| `palm make:seeder <name>` | Create seeder file |
| `palm db:seed` | Run all seeders |
| `palm make:usetable <table>` | Generate module from DB table |

### Server & Development

| Command | Description |
|---------|-------------|
| `palm serve [--port=8000]` | Start dev server with hot reload |
| `palm serve:worker` | Start background worker |

### Cache Management

| Command | Description |
|---------|-------------|
| `palm cache:clear` | Clear all application cache |
| `palm route:list` | List all registered routes |
| `palm route:clear` | Clear route cache |
| `palm view:clear` | Clear view cache |

### Logging

| Command | Description |
|---------|-------------|
| `palm logs:view [--lines=50]` | View recent log entries |
| `palm logs:tail` | Watch logs in real-time |
| `palm logs:clear` | Clear log files |

### Internationalization

| Command | Description |
|---------|-------------|
| `palm i18n:extract` | Extract translation strings |
| `palm i18n:generate <locale>` | Generate translation file |
| `palm i18n:check` | Check for missing translations |

### Security & Optimization

| Command | Description |
|---------|-------------|
| `palm security:headers [preset]` | Configure security headers |
| `palm make:security:policy` | Generate security policy |
| `palm optimize` | Optimize application (cache routes, views) |
| `palm sitemap:generate` | Generate sitemap.xml |

---

## 📦 Module System

Modules are self-contained features organized in `modules/`.

### Structure
```
modules/
└── Products/
    ├── Module.php      # Route definitions
    ├── Controller.php  # HTTP handlers
    ├── Service.php     # Business logic
    └── Model.php       # Database & Validation
```

### 1. Model (Database + Validation)

Models handle database operations AND validation using PHP 8 Attributes.

**`modules/Products/Model.php`**
```php
<?php
namespace App\Modules\Products;

use App\Core\Model as BaseModel;
use Frontend\Palm\Validation\Attributes\Required;
use Frontend\Palm\Validation\Attributes\IsString;
use Frontend\Palm\Validation\Attributes\Min;
use Frontend\Palm\Validation\Attributes\Length;

class Model extends BaseModel
{
    protected string $table = 'products';

    // Primary Key
    public int $id;

    // Validated Fields
    #[Required]
    #[IsString]
    #[Length(min: 3, max: 100)]
    public string $name;

    #[Required]
    #[Min(0)]
    public float $price;

    #[IsString]
    public ?string $description = null;

    // Timestamps
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
```

### 2. Service (Business Logic)

Services contain business logic and use `Model::validate()` for automatic validation.

**`modules/Products/Service.php`**
```php
<?php
namespace App\Modules\Products;

use App\Core\Service as BaseService;
use Frontend\Palm\Validation\ValidationException;

class Service extends BaseService
{
    public function create(array $data): array
    {
        // Automatic validation & hydration
        // Throws ValidationException if invalid
        $model = Model::validate($data);

        // Save to database
        if ($model->save()) {
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

    public function getAll(): array
    {
        $products = Model::all();
        return [
            'total' => $products->count(),
            'items' => $products
        ];
    }

    public function getById(int $id): ?array
    {
        $model = Model::find($id);
        return $model ? $model->toArray() : null;
    }
}
```

### 3. Controller (HTTP Handling)

Controllers handle HTTP requests and responses.

**`modules/Products/Controller.php`**
```php
<?php
namespace App\Modules\Products;

use App\Core\Controller as BaseController;
use Frontend\Palm\Validation\ValidationException;

class Controller extends BaseController
{
    protected Service $service;

    public function __construct()
    {
        $this->service = new Service();
    }

    // POST /products
    public function store(): array
    {
        try {
            $result = $this->service->create($this->getRequestData());
            return $this->success($result['data'], 'Product created', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed', $e->getErrors(), 422);
        }
    }

    // GET /products
    public function index(): array
    {
        $data = $this->service->getAll();
        return $this->success($data);
    }

    // GET /products/{id}
    public function show(string $id): array
    {
        $data = $this->service->getById((int)$id);
        return $data 
            ? $this->success($data)
            : $this->error('Product not found', [], 404);
    }
}
```

### 4. Module (Route Registration)

**`modules/Products/Module.php`**
```php
<?php
namespace App\Modules\Products;

use App\Core\Module as BaseModule;
use PhpPalm\Core\Route;

class Module extends BaseModule
{
    public function __construct()
    {
        parent::__construct('Products', '/products');
    }

    public function registerRoutes(): void
    {
        $c = new Controller();

        // CRUD Routes
        Route::get($this->route(''), [$c, 'index']);
        Route::post($this->route(''), [$c, 'store']);
        Route::get($this->route('/{id}'), [$c, 'show']);
        Route::put($this->route('/{id}'), [$c, 'update']);
        Route::delete($this->route('/{id}'), [$c, 'destroy']);

        // Custom Routes
        Route::get($this->route('/featured'), [$c, 'featured']);
    }
}
```

---

## 🛡 Model Validation

Validate data declaratively using PHP 8 Attributes.

### Available Attributes

```php
#[Required]              // Field is required
#[Optional]              // Field is optional (default)
#[IsString]              // Must be string
#[IsInt]                 // Must be integer
#[IsBool]                // Must be boolean
#[IsArray]               // Must be array
#[IsEmail]               // Must be valid email
#[IsUrl]                 // Must be valid URL
#[IsDate]                // Must be valid date
#[Length(min: 5, max: 20)]  // String length constraints
#[Min(10)]               // Minimum value (numeric)
#[Max(100)]              // Maximum value (numeric)
#[Matches('/^[A-Z]/')]   // Regex pattern match
#[Enum(['active', 'pending'])]  // Must be one of values
```

### Usage Example

```php
class UserModel extends BaseModel
{
    #[Required]
    #[IsString]
    #[Length(min: 3, max: 50)]
    public string $username;

    #[Required]
    #[IsEmail]
    public string $email;

    #[Required]
    #[Length(min: 8)]
    public string $password;

    #[IsInt]
    #[Min(18)]
    #[Max(120)]
    public ?int $age = null;

    #[Enum(['active', 'pending', 'banned'])]
    public string $status = 'pending';
}
```

### Validating in Service

```php
public function create(array $data): array
{
    try {
        // Validates AND hydrates model
        $user = UserModel::validate($data);
        $user->save();
        return ['success' => true, 'data' => $user->toArray()];
    } catch (ValidationException $e) {
        return [
            'success' => false,
            'errors' => $e->getErrors()
        ];
    }
}
```

---

## 🗄️ ActiveRecord ORM

Palm includes a powerful ActiveRecord ORM for database operations.

### Basic CRUD

```php
// Find by ID
$user = User::find(1);

// Find or fail (throws exception)
$user = User::findOrFail(1);

// Get all records
$users = User::all();

// Create
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Update
$user->name = 'Jane Doe';
$user->save();

// Delete
$user->delete();
```

### Query Builder

```php
// Basic WHERE
$users = User::where('status', 'active')->all();

// Multiple conditions
$users = User::where('status', 'active')
             ->where('age', '>', 18)
             ->all();

// WHERE IN
$users = User::whereIn('id', [1, 2, 3])->all();

// ORDER BY
$users = User::where('status', 'active')
             ->orderBy('created_at', 'DESC')
             ->all();

// LIMIT & OFFSET
$users = User::limit(10)->offset(20)->all();

// Count
$count = User::where('status', 'active')->count();

// Exists
$exists = User::where('email', 'john@example.com')->exists();
```

### Aggregates

```php
$total = Order::sum('amount');
$average = Employee::avg('salary');
$max = Product::max('price');
$min = Product::min('price');
```

### Relationships

```php
// In Model
public function posts()
{
    return $this->hasMany(Post::class, 'user_id');
}

// Usage
$user = User::find(1);
$posts = $user->posts; // Lazy loading
```

---

## 🛣 Routing

### Modular Routes (Recommended)

Defined in `Module.php`:

```php
Route::get($this->route('/path'), [$controller, 'method']);
Route::post($this->route('/path'), [$controller, 'method']);
Route::put($this->route('/{id}'), [$controller, 'method']);
Route::delete($this->route('/{id}'), [$controller, 'method']);
```

### Internal Calls (HMVC)

Call routes internally without HTTP overhead:

```php
use App\Modules\Users\Module as UserModule;

// GET request
$users = UserModule::get('/users');

// POST request  
$newUser = UserModule::post('/users', [
    'name' => 'John',
    'email' => 'john@example.com'
]);

// Update
$updated = UserModule::put('/users/1', ['name' => 'Jane']);

// Delete
$deleted = UserModule::delete('/users/1');
```

### Route Groups

```php
Route::group(['prefix' => '/api/v1'], function() {
    Route::get('/users', [$controller, 'index']);
    Route::get('/products', [$controller, 'products']);
});
```

---

## 🎨 Frontend Features

### Views

View files use `.palm.php` extension in `src/views/`:

**`src/views/products/list.palm.php`**
```php
<div class="products">
    <h1>Products</h1>
    <?php foreach($products as $product): ?>
        <div class="product-card">
            <h3><?= htmlspecialchars($product['name']) ?></h3>
            <p><?= htmlspecialchars($product['description']) ?></p>
            <span class="price">$<?= number_format($product['price'], 2) ?></span>
        </div>
    <?php endforeach; ?>
</div>
```

**Rendering in Route:**
```php
// In frontend Route
Route::view('/products', 'products.list', [
    'products' => ProductModule::get('/products')
]);
```

### Components

Create reusable components:

```bash
palm make:component Button
```

**Usage:**
```php
<?= Component::render('Button', ['text' => 'Click Me', 'type' => 'primary']) ?>
```

### SEO & Meta Tags

```php
use Frontend\Palm\PageMeta;

// Set page meta
PageMeta::setTitle('Product List');
PageMeta::setDescription('Browse our amazing products');
PageMeta::setKeywords(['products', 'shop', 'ecommerce']);

// Open Graph
PageMeta::setOgImage('/images/products.jpg');
PageMeta::setOgType('website');
```

### PWA Support

Generate PWA files:

```bash
palm make:pwa "My App" "MyApp"
```

This creates:
- `manifest.json`
- Service worker
- Offline page support

### Hot Reload

Development server includes WebSocket-based hot reload:

```bash
palm serve
```

Files are watched automatically. Changes trigger instant browser refresh.

---

## 💾 Database & Migrations

### Creating Migrations

```bash
palm make:migration create_products_table
```

**`database/migrations/YYYY_MM_DD_HHMMSS_create_products_table.php`**
```php
return [
    'up' => function($db) {
        return $db->createTable('products', [
            'name' => ['type' => 'VARCHAR', 'length' => 100],
            'price' => ['type' => 'DECIMAL', 'precision' => 10, 'scale' => 2],
            'description' => ['type' => 'TEXT', 'nullable' => true],
        ]);
    },
    'down' => function($db) {
        return $db->dropTable('products');
    }
];
```

### Running Migrations

```bash
# Run all pending
palm migrate

# Rollback last
palm migrate:rollback

# Reset all
palm migrate:reset

# Reset and re-run
palm migrate:refresh
```

### Seeders

```bash
palm make:seeder ProductSeeder
```

**`database/seeders/ProductSeeder.php`**
```php
return function($db) {
    $products = [
        ['name' => 'Product 1', 'price' => 29.99],
        ['name' => 'Product 2', 'price' => 49.99],
    ];
    
    foreach ($products as $product) {
        $db->insert('products', $product);
    }
};
```

**Run seeders:**
```bash
palm db:seed
```

---

## 🔒 Security

### Built-in Security Features

- **CSRF Protection**: Automatic token injection
- **XSS Protection**: Output escaping helpers
- **SQL Injection Protection**: Prepared statements in ORM
- **Security Headers**: CSP, X-Frame-Options, etc.
- **Rate Limiting**: Configurable per route
- **Input Validation**: Attribute-based validation

### Security Headers

Configure security headers:

```bash
palm security:headers strict
```

Presets: `default`, `strict`, `development`

### CSRF Protection

Automatically injected in forms:

```php
<form method="POST">
    <?= csrf_field() ?>
    <!-- form fields -->
</form>
```

---

## ⚡ Performance

### Caching

```php
// Route caching
palm route:cache

// View caching  
palm view:cache

// Clear all cache
palm cache:clear
```

### Response Optimization

Automatic:
- Gzip compression
- Brotli compression
- HTML minification
- Asset minification

### Progressive Resource Loading

Automatically optimizes:
- Image lazy loading
- Script deferral
- Preloading critical resources
- Prefetching next pages

---

## 📖 Best Practices

### 1. Use Modules for Organization

Organize features into modules rather than scattered files.

### 2. Leverage Model Validation

Define validation rules in models using attributes instead of manual validation.

### 3. Use Services for Business Logic

Keep controllers thin, move logic to services.

### 4. Utilize Internal Calls

Use `Module::get()` for internal API calls to avoid HTTP overhead.

### 5. Cache Aggressively

Use route and view caching in production:

```bash
palm optimize
```

### 6. Follow Naming Conventions

- Models: Singular (`Product`, `User`)
- Tables: Plural (`products`, `users`)
- Controllers: Descriptive (`ProductController`)
- Services: Descriptive (`ProductService`)

---

## 🔧 Configuration

### Environment Variables

Edit `config/.env`:

```env
DATABASE_SERVER_NAME=localhost
DATABASE_USERNAME=root
DATABASE_PASSWORD=secret
DATABASE_NAME=myapp

APP_NAME=My Application
APP_URL=http://localhost:8000
DEBUG_MODE=true
```

### CORS Configuration

Edit `config/cors.php` to configure allowed origins, methods, and headers.

---

## 📚 Additional Resources

- **ActiveRecord ORM**: See `ACTIVERECORD_USAGE.md`
- **Internal Routes**: See `MODULE_INTERNAL_ROUTES.md`
- **SEO Features**: See `SEO_META_TAGS.md`
- **Google Auth**: See `My_Docs/GOOGLE_AUTH_USAGE.md`

---

## 🤝 Contributing

Contributions welcome! Please follow the existing code style and include tests for new features.

## 📄 License

MIT License

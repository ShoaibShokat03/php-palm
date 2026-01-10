# ActiveRecord Usage Guide

Complete guide to using PHP Palm's ActiveRecord ORM implementation. This document covers all available methods, patterns, and best practices for database operations.

---

## Table of Contents

1. [Introduction](#introduction)
2. [Model Setup](#model-setup)
3. [Basic CRUD Operations](#basic-crud-operations)
4. [Query Building](#query-building)
5. [Advanced Queries](#advanced-queries)
6. [Relationships](#relationships)
7. [Model Collections](#model-collections)
8. [Performance Optimization](#performance-optimization)
9. [Best Practices](#best-practices)
10. [API Reference](#api-reference)

---

## Introduction

PHP Palm implements the **ActiveRecord pattern**, which means your model classes represent database tables, and instances represent table rows. You can perform database operations using intuitive methods without writing SQL queries.

### Key Features

- ✅ **No SQL Required** - All operations use PHP methods
- ✅ **Fluent Query Builder** - Chain methods for complex queries
- ✅ **Relationship Support** - Easy eager loading and lazy loading
- ✅ **Type Safety** - Full IDE autocomplete support
- ✅ **Performance Optimized** - Connection pooling, query caching, reflection caching
- ✅ **Model Collections** - Array-like behavior with helper methods

---

## Model Setup

### Creating a Model

Every model extends the base `App\Core\Model` class and defines a table name:

```php
<?php

namespace App\Modules\Product;

use App\Core\Model as BaseModel;

class Model extends BaseModel
{
    protected string $table = 'products';
    
    // Optional: Define public properties for IDE autocomplete
    // These are not required - the model works without them
    public $id;
    public $name;
    public $price;
    public $description;
    public $status;
    public $created_at;
    public $updated_at;
}
```

### Model Properties

- **`$table`** (required): The database table name
- **Public properties** (optional): Define for IDE autocomplete, but not required for functionality
- The model automatically handles attribute access via `__get()` and `__set()` magic methods

### Primary Key

By default, models use `id` as the primary key. To override:

```php
public static function getPrimaryKey(): string
{
    return 'product_id'; // Custom primary key
}
```

---

## Basic CRUD Operations

### Create (Insert)

#### Method 1: Static `create()` Method

```php
use App\Modules\Product\Model;

// Create a new product
$product = Model::create([
    'name' => 'Laptop',
    'price' => 999.99,
    'description' => 'Gaming laptop',
    'status' => 'active'
]);

// Returns Model instance with ID populated
echo $product->id; // Auto-generated ID
```

#### Method 2: Instance `save()` Method

```php
$product = new Model();
$product->name = 'Laptop';
$product->price = 999.99;
$product->description = 'Gaming laptop';
$product->status = 'active';

if ($product->save()) {
    echo "Created with ID: " . $product->id;
}
```

### Read (Select)

#### Get All Records

```php
// Get all products (returns ModelCollection)
$products = Model::all();

// Iterate like an array
foreach ($products as $product) {
    echo $product->name . "\n";
}

// Get count
$total = $products->count();

// Get first item
$first = $products->first();
```

#### Find by ID

```php
// Find by primary key
$product = Model::find(1);

if ($product) {
    echo $product->name;
}

// Find or throw exception
$product = Model::findOrFail(1); // Throws exception if not found
```

#### Find with Conditions

```php
// Find by single condition
$product = Model::findOne(['status' => 'active']);

// Find by multiple conditions
$product = Model::findOne([
    'status' => 'active',
    'featured' => 1
]);

// Find all matching conditions
$products = Model::findAll(['status' => 'active']);
```

### Update

#### Method 1: Update via `save()`

```php
$product = Model::find(1);
$product->name = 'Updated Laptop';
$product->price = 899.99;

if ($product->save()) {
    echo "Updated successfully";
}
```

#### Method 2: Update via `update()` Method

```php
$product = Model::find(1);

// Update specific fields
$product->update([
    'name' => 'Updated Laptop',
    'price' => 899.99
]);

// Update all attributes
$product->update(); // Uses current $product->attributes
```

#### Method 3: Bulk Update (via Query Builder)

```php
// Note: PHP Palm doesn't have a built-in bulk update method
// You can use the database directly for bulk operations if needed
```

### Delete

```php
$product = Model::find(1);

if ($product->delete()) {
    echo "Deleted successfully";
}
```

---

## Query Building

PHP Palm provides a fluent query builder for complex queries. All query methods return a `QueryBuilder` instance that can be chained.

### Basic Where Clauses

#### Simple Where

```php
// WHERE status = 'active'
$products = Model::where('status', 'active')->all();

// WHERE price > 100
$products = Model::where('price', '>', 100)->all();

// WHERE id = 1 (single record)
$product = Model::where('id', 1)->one();
```

#### Multiple Conditions (AND)

```php
$products = Model::where('status', 'active')
    ->where('price', '>', 50)
    ->where('featured', 1)
    ->all();
```

#### OR Conditions

```php
$products = Model::where('status', 'active')
    ->orWhere('status', 'pending')
    ->all();
```

#### Array Where (Multiple AND)

```php
$products = Model::where([
    'status' => 'active',
    'featured' => 1,
    'price' => '>50' // Note: This won't work as expected
])->all();

// For operators, use separate where() calls
$products = Model::where('status', 'active')
    ->where('featured', 1)
    ->where('price', '>', 50)
    ->all();
```

#### Filter Method (Alias for where)

```php
// filter() is an alias for where() - provides better readability
$products = Model::filter('status', 'active')
    ->filter('price', '>', 50)
    ->all();

// Array format
$products = Model::filter([
    'status' => 'active',
    'featured' => 1
])->all();
```

### WHERE IN / WHERE NOT IN

```php
// WHERE id IN (1, 2, 3, 4, 5)
$products = Model::whereIn('id', [1, 2, 3, 4, 5])->all();

// WHERE status NOT IN ('deleted', 'archived')
$products = Model::whereNotIn('status', ['deleted', 'archived']->all();
```

### Ordering

```php
// Single order by
$products = Model::where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->all();

// Multiple order by
$products = Model::where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->orderBy('name', 'ASC')
    ->all();
```

### Limiting and Pagination

```php
// Limit results
$products = Model::where('status', 'active')
    ->limit(10)
    ->all();

// Offset (skip records)
$products = Model::where('status', 'active')
    ->offset(20)
    ->limit(10)
    ->all();

// Pagination aliases
$products = Model::where('status', 'active')
    ->skip(20)  // Same as offset(20)
    ->take(10)  // Same as limit(10)
    ->all();

// From-To range (for pagination)
$products = Model::where('status', 'active')
    ->fromTo(0, 10)   // Records 0-10 (first page)
    ->all();

$products = Model::where('status', 'active')
    ->fromTo(10, 20)  // Records 10-20 (second page)
    ->all();
```

### Selecting Specific Columns

```php
// Select specific columns
$products = Model::select(['id', 'name', 'price'])
    ->where('status', 'active')
    ->all();

// Single column
$products = Model::select('name')
    ->where('status', 'active')
    ->all();
```

### Search (LIKE Queries)

```php
// Search across multiple columns
$products = Model::search('laptop', ['name', 'description'])->all();

// Search in single column
$products = Model::search('laptop', 'name')->all();
```

### Count and Exists

```php
// Count records
$count = Model::where('status', 'active')->count();

// Static count method
$count = Model::count(['status' => 'active']);

// Check if exists
$exists = Model::where('id', 1)->exists();

// Static exists method
$exists = Model::exists(['status' => 'active']);
```

---

## Advanced Queries

### Complex WHERE Conditions

```php
// Combine AND and OR
$products = Model::where('status', 'active')
    ->where(function($query) {
        $query->where('price', '>', 100)
              ->orWhere('featured', 1);
    })
    ->all();

// Note: The above closure syntax may not be fully supported
// Use explicit chaining instead:
$products = Model::where('status', 'active')
    ->where('price', '>', 100)
    ->orWhere('featured', 1)
    ->all();
```

### NULL Checks

```php
// WHERE deleted_at IS NULL
$products = Model::where('deleted_at', null)->all();

// WHERE deleted_at IS NOT NULL
$products = Model::where('deleted_at', '!=', null)->all();
```

### Subqueries and Aggregates

```php
// Count with conditions
$count = Model::where('status', 'active')->count();

// Using select with aggregates
$result = Model::select(['COUNT(*) as total'])
    ->where('status', 'active')
    ->asArrays()
    ->one();

echo $result['total'];
```

### Return Formats

By default, queries return Model instances. You can change the return format:

```php
// Return Model instances (default)
$products = Model::where('status', 'active')->all(); // ModelCollection

// Return stdClass objects
$products = Model::where('status', 'active')
    ->asObjects()
    ->all(); // Array of stdClass

// Return raw arrays
$products = Model::where('status', 'active')
    ->asArrays()
    ->all(); // Array of arrays
```

---

## Relationships

PHP Palm supports three types of relationships: `hasOne`, `hasMany`, and `belongsTo`.

### Defining Relationships

```php
<?php

namespace App\Modules\User;

use App\Core\Model as BaseModel;
use App\Modules\Post\Model as PostModel;
use App\Modules\Profile\Model as ProfileModel;
use App\Modules\Company\Model as CompanyModel;

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

### Relationship Types

#### hasOne

One-to-one relationship where the related model has a foreign key.

```php
// In User model
public function profile()
{
    return $this->hasOne(ProfileModel::class, 'user_id');
    //                                  ↑
    //                    Foreign key in profiles table
}
```

#### hasMany

One-to-many relationship where the related model has a foreign key.

```php
// In User model
public function posts()
{
    return $this->hasMany(PostModel::class, 'user_id');
    //                                  ↑
    //                    Foreign key in posts table
}
```

#### belongsTo

Many-to-one relationship where this model has a foreign key.

```php
// In Post model
public function user()
{
    return $this->belongsTo(UserModel::class, 'user_id');
    //                                  ↑
    //                    Foreign key in posts table
}
```

### Accessing Relationships

#### Lazy Loading

Relationships are loaded automatically when accessed:

```php
$user = UserModel::find(1);

// Lazy load - query executed when accessed
$posts = $user->posts;        // Array of PostModel instances
$profile = $user->profile;     // ProfileModel or null
$company = $user->company;     // CompanyModel or null

// Access related data
foreach ($user->posts as $post) {
    echo $post->title;
}
```

#### Eager Loading (Performance Optimization)

Load relationships in advance to avoid N+1 query problems:

```php
// Load single relationship
$users = UserModel::with('posts')->all();

// Load multiple relationships
$users = UserModel::with(['posts', 'profile', 'company'])->all();

// Access relationships (already loaded, no additional queries)
foreach ($users as $user) {
    echo $user->name;
    foreach ($user->posts as $post) {
        echo $post->title; // No additional query!
    }
}
```

### Relationship with Conditions

You can add conditions to relationships by accessing them via query builder:

```php
// Get user's published posts only
$user = UserModel::find(1);
$publishedPosts = PostModel::where('user_id', $user->id)
    ->where('status', 'published')
    ->all();
```

---

## Model Collections

The `ModelCollection` class wraps arrays of models and provides helpful methods.

### Collection Methods

```php
$products = Model::where('status', 'active')->all();

// Count items
$total = $products->count();

// Get first item
$first = $products->first();

// Get all items as array
$array = $products->all();

// Convert to array (recursively converts nested models)
$array = $products->toArray();

// Map transformation
$names = $products->map(function($product) {
    return $product->name;
});

// Array access
$firstProduct = $products[0];

// Iterate
foreach ($products as $product) {
    echo $product->name;
}
```

### JSON Serialization

Collections implement `JsonSerializable`, so they can be returned directly from controllers:

```php
// In Controller
public function index(): array
{
    $products = Model::all();
    return $this->success($products, 'Products retrieved'); 
    // Collection automatically converts to JSON
}
```

---

## Performance Optimization

### Connection Pooling

PHP Palm automatically reuses database connections for better performance. The framework maintains a static DB connection that's shared across all model queries.

### Query Caching

The database layer includes automatic query caching:

- **APCu Cache**: Used when available (shared memory cache)
- **Local Cache**: Fallback when APCu is not available
- **Automatic Invalidation**: Cache is cleared when tables are modified

### Reflection Caching

Model property reflection is cached to avoid repeated `ReflectionClass` instantiation.

### Eager Loading

Always use eager loading to avoid N+1 query problems:

```php
// ❌ Bad: N+1 queries
$users = UserModel::all();
foreach ($users as $user) {
    $posts = $user->posts; // Query for each user!
}

// ✅ Good: 2 queries total
$users = UserModel::with('posts')->all();
foreach ($users as $user) {
    $posts = $user->posts; // Already loaded!
}
```

### Select Specific Columns

Only select columns you need:

```php
// ❌ Bad: Selects all columns
$products = Model::all();

// ✅ Good: Selects only needed columns
$products = Model::select(['id', 'name', 'price'])->all();
```

---

## Best Practices

### 1. Always Use Models

```php
// ❌ Bad: Direct SQL
$db = new Db();
$result = $db->query("SELECT * FROM products WHERE status = 'active'");

// ✅ Good: Use ActiveRecord
$products = Model::where('status', 'active')->all();
```

### 2. Validate Before Saving

```php
// In Service layer
public function create(array $data): array
{
    $errors = [];
    
    if (empty($data['name'])) {
        $errors['name'] = 'Name is required';
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    $model = Model::create($data);
    return ['success' => true, 'data' => $model];
}
```

### 3. Use Transactions for Multiple Operations

```php
use App\Core\Database\Transaction;

Transaction::begin();
try {
    $user = UserModel::create($userData);
    $profile = ProfileModel::create(['user_id' => $user->id, ...]);
    Transaction::commit();
} catch (\Exception $e) {
    Transaction::rollback();
    throw $e;
}
```

### 4. Handle Errors Gracefully

```php
$product = Model::find(1);

if (!$product) {
    return $this->error('Product not found', [], 404);
}

// Continue with product operations
```

### 5. Use Type Hints

```php
// In Service methods
public function getById(int $id): ?array
{
    $model = Model::find($id);
    return $model ? $model->toArray() : null;
}
```

---

## API Reference

### Static Methods

#### `Model::all(): ModelCollection`
Get all records from the table.

#### `Model::find($condition): ?Model|QueryBuilder`
- If `$condition` is null: returns QueryBuilder
- If `$condition` is provided: returns single Model or null

#### `Model::findOne($condition): ?Model`
Find a single record by condition (ID, array, or QueryBuilder).

#### `Model::findAll($condition): ModelCollection`
Find all records matching condition.

#### `Model::findOrFail($condition): Model`
Find record or throw exception if not found.

#### `Model::where($column, $operator, $value): QueryBuilder`
Start a query with WHERE condition.

#### `Model::filter($column, $operator, $value): QueryBuilder`
Alias for `where()`.

#### `Model::whereIn($column, array $values): QueryBuilder`
Start query with WHERE IN condition.

#### `Model::create(array $attributes): ?Model`
Create a new record and return Model instance.

#### `Model::count($condition): int`
Count records matching condition.

#### `Model::exists($condition): bool`
Check if any record matches condition.

### Instance Methods

#### `$model->save(): bool`
Save the model (insert if new, update if exists).

#### `$model->update(array $attributes = []): bool`
Update the model with provided attributes.

#### `$model->delete(): bool`
Delete the model from database.

#### `$model->toArray(): array`
Convert model to array (includes relationships if loaded).

#### `$model->getAttribute(string $name)`
Get attribute value.

#### `$model->setAttribute(string $name, $value): void`
Set attribute value.

#### `$model->getAttributes(): array`
Get all attributes.

### QueryBuilder Methods

#### `->where($column, $operator, $value): self`
Add WHERE condition.

#### `->andWhere($column, $operator, $value): self`
Add AND WHERE condition.

#### `->orWhere($column, $operator, $value): self`
Add OR WHERE condition.

#### `->whereIn($column, array $values): self`
Add WHERE IN condition.

#### `->whereNotIn($column, array $values): self`
Add WHERE NOT IN condition.

#### `->orderBy($column, $direction): self`
Add ORDER BY clause.

#### `->limit(int $limit): self`
Add LIMIT clause.

#### `->offset(int $offset): self`
Add OFFSET clause.

#### `->skip(int $count): self`
Alias for `offset()`.

#### `->take(int $count): self`
Alias for `limit()`.

#### `->fromTo(int $from, int $to): self`
Set offset and limit for pagination range.

#### `->select(array|string $columns): self`
Set columns to select.

#### `->search(string $term, array|string $columns): self`
Search across columns using LIKE.

#### `->with(array|string $relations): self`
Eager load relationships.

#### `->asModels(): self`
Return Model instances (default).

#### `->asObjects(): self`
Return stdClass objects.

#### `->asArrays(): self`
Return raw arrays.

#### `->all()`
Execute query and return all results.

#### `->one()`
Execute query and return first result.

#### `->count(): int`
Execute query and return count.

#### `->exists(): bool`
Check if any records match query.

### ModelCollection Methods

#### `->count(): int`
Get number of items.

#### `->first()`
Get first item.

#### `->all(): array`
Get all items as array.

#### `->toArray(): array`
Convert collection to array (recursively converts models).

#### `->map(callable $callback): self`
Transform each item using callback.

---

## Examples

### Complete CRUD Example

```php
<?php

namespace App\Modules\Product;

use App\Core\Model as BaseModel;

class Model extends BaseModel
{
    protected string $table = 'products';
}

// Create
$product = Model::create([
    'name' => 'Laptop',
    'price' => 999.99,
    'status' => 'active'
]);

// Read
$product = Model::find(1);
$products = Model::where('status', 'active')->all();

// Update
$product = Model::find(1);
$product->name = 'Updated Laptop';
$product->save();

// Delete
$product = Model::find(1);
$product->delete();
```

### Complex Query Example

```php
// Get active products with price > 50, ordered by name, limit 10
$products = Model::where('status', 'active')
    ->where('price', '>', 50)
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->all();

// Search products
$products = Model::search('laptop', ['name', 'description'])
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->all();
```

### Relationship Example

```php
// Get users with their posts (eager loading)
$users = UserModel::with('posts')->all();

foreach ($users as $user) {
    echo "User: {$user->name}\n";
    foreach ($user->posts as $post) {
        echo "  Post: {$post->title}\n";
    }
}
```

---

**For more information, see the main [README.md](README.md) file.**


<?php

/**
 * API Documentation Generator
 */
class ApiDocGenerator
{
    /**
     * Generate API.md for a module
     */
    public static function generate(string $modulePath, string $moduleName, string $routePrefix): void
    {
        $content = <<<MD
# {$moduleName} Module API

Base URL: `{$routePrefix}`

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `{$routePrefix}` | Get all records (paginated) |
| `GET` | `{$routePrefix}/{id}` | Get single record by ID |
| `POST` | `{$routePrefix}` | Create new record |
| `PUT` | `{$routePrefix}/{id}` | Update record |
| `DELETE` | `{$routePrefix}/{id}` | Delete record |

---

## 1. Get All Records

**Endpoint:** `GET {$routePrefix}`

**Query Parameters:**
- `page` (int) - Page number (default: 1)
- `per_page` (int) - Items per page (default: 10)
- `search` (string) - Search term
- `sort` (string) - Sort column (default: id)
- `order` (string) - Sort order (asc/desc)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      // ... fields
    }
  ],
  "meta": {
    "total": 50,
    "page": 1,
    "per_page": 10,
    "last_page": 5
  }
}
```

## 2. Get Single Record

**Endpoint:** `GET {$routePrefix}/{id}`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    // ... fields
  }
}
```

## 3. Create Record

**Endpoint:** `POST {$routePrefix}`

**Body:**
```json
{
  // ... fields
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 2,
    // ... created fields
  }
}
```

## 4. Update Record

**Endpoint:** `PUT {$routePrefix}/{id}`

**Body:**
```json
{
  // ... fields to update
}
```

## 5. Delete Record

**Endpoint:** `DELETE {$routePrefix}/{id}`

**Response:** `200 OK`

---

## Internal Usage (HMVC)

You can call these routes internally without HTTP requests:

```php
use App\Modules\\{$moduleName}\Module;

// Get all
\$items = Module::get('{$routePrefix}');

// Create
\$newItem = Module::post('{$routePrefix}', \$data);
```
MD;

        file_put_contents($modulePath . '/API.md', $content);
        echo "   ✅ Created: API.md\n";
    }
}

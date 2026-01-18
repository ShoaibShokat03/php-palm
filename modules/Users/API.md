# Users Module API

Base URL: `/users`

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/users` | Get all records (paginated) |
| `GET` | `/users/{id}` | Get single record by ID |
| `POST` | `/users` | Create new record |
| `PUT` | `/users/{id}` | Update record |
| `DELETE` | `/users/{id}` | Delete record |

---

## 1. Get All Records

**Endpoint:** `GET /users`

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

**Endpoint:** `GET /users/{id}`

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

**Endpoint:** `POST /users`

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

**Endpoint:** `PUT /users/{id}`

**Body:**
```json
{
  // ... fields to update
}
```

## 5. Delete Record

**Endpoint:** `DELETE /users/{id}`

**Response:** `200 OK`

---

## Internal Usage (HMVC)

You can call these routes internally without HTTP requests:

```php
use App\Modules\Users\Module;

// Get all
$items = Module::get('/users');

// Create
$newItem = Module::post('/users', $data);
```
<?php

namespace App\Modules\Users;

use App\Core\Service as BaseService;
use App\Modules\Users\Model;
use PhpPalm\Core\Request;

class Service extends BaseService
{
    /**
     * Get all Users records with automatic pagination and filtering
     * 
     * Query Parameters (auto-read from $_GET):
     * - page: Current page number (default: 1)
     * - per_page: Items per page (default: 10, max: 100)
     * - sort: Column to sort by (default: id)
     * - order: Sort order (asc/desc, default: desc)
     * - search: Search term for searchable columns
     * - status, role, type: Filter by these columns
     */
    public function getAll(): array
    {
        $request = new Request();

        // Get pagination params from request
        $page = max(1, (int)($request->get('page') ?? 1));
        $perPage = min(100, max(1, (int)($request->get('per_page') ?? 10)));
        $search = $request->get('search') ?? null;

        // Build query with fluent API
        $query = Model::where()
            ->search($search, ['name', 'email'])  // Search columns
            ->autoFilter(['name', 'email', 'role']) // Auto-filter from $_GET
            ->sort();  // Auto-read sort/order from $_GET

        // Get total count before pagination
        $total = $query->count();

        // Apply pagination and get records
        $records = $query
            ->paginate($page, $perPage)
            ->all();

        // Calculate pagination metadata
        $lastPage = max(1, (int)ceil($total / $perPage));
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : null;
        $to = $total > 0 ? min($total, $page * $perPage) : null;

        return [
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
                'has_more' => $page < $lastPage,
            ],
            'data' => $records
        ];
    }

    /**
     * Get Users by ID
     * Uses ActiveRecord: Model::find()
     */
    public function getById(int $id): ?array
    {
        $model = Model::find($id);
        return $model ? $model->toArray() : null;
    }

    /**
     * Create Users
     * Uses ActiveRecord: Model::create()
     */
    /**
     * Create Users
     * Uses Model validation directly!
     */
    public function create(array $data): array
    {
        // Use the Model::create method directly
        // Validation should ideally happen before this or via Model attributes validation if implemented
        $model = Model::create($data);

        if ($model) {
            return [
                'success' => true,
                'data' => $model->toArray()
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create Users'
        ];
    }

    /**
     * Update Users
     * Uses ActiveRecord: $model->save()
     */
    public function update(int $id, array $data): array
    {
        $model = Model::find($id);

        if (!$model) {
            return [
                'success' => false,
                'message' => 'Users not found'
            ];
        }

        // Update attributes (only allowed fields)
        $allowedFields = ['name', 'email', 'role'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $model->$key = $value;
            }
        }

        if ($model->save()) {
            return [
                'success' => true,
                'data' => $model->toArray()
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to update Users'
        ];
    }

    /**
     * Delete Users
     * Uses ActiveRecord: $model->delete()
     */
    public function delete(int $id): array
    {
        $model = Model::find($id);

        if (!$model) {
            return [
                'success' => false,
                'message' => 'Users not found'
            ];
        }

        if ($model->delete()) {
            return ['success' => true];
        }

        return [
            'success' => false,
            'message' => 'Failed to delete Users'
        ];
    }
}

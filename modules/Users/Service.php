<?php

namespace App\Modules\Users;

use App\Core\Service as BaseService;
use App\Modules\Users\Model;

class Service extends BaseService
{
    /**
     * Get all Users records
     * Uses ActiveRecord: Model::all()
     */
    public function getAll(): array
    {
        $records = Model::all();
        return [
            'total' => $records->count(),
            'items' => $records
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
        // 1. Validate data against Model attributes
        // This throws ValidationException if invalid
        // Returns a populated Model instance
        $model = Model::validate($data);

        // 2. Save the validated model
        // Since validate() returns a populated (but unsaved) model, we check if it needs saving
        // Actually, create() in ActiveRecord creates a NEW instance. 
        // Let's use the standard create flow:

        // Convert validated model back to array for the static create() helper 
        // (or just save the instance returned by validate() if we update logic)

        // Better approach: Since validate() returns a populated Model instance, we can just save it!
        // But Model::validate() returns a generic instance essentially hydrated. 

        // $model is already hydrated with validated data.
        if ($model->save()) {
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

<?php

namespace App\Modules\Users;

use App\Core\Service as BaseService;
use App\Modules\Users\Model;
use App\Core\App;

class Service extends BaseService
{
    public function getAll(): array
    {
        $request = App::request();
        $page = max(1, (int)($request->get('page') ?? 1));
        $perPage = min(100, max(1, (int)($request->get('per_page') ?? 10)));
        $search = $request->get('search') ?? null;

        $query = Model::where()
            ->search($search, ['role', 'name', 'email', 'phone_number', 'address', 'note'])
            ->autoFilter(['role', 'name', 'email', 'phone_number', 'gender', 'created_at'])
            ->sort();

        $total = $query->count();
        $records = $query->paginate($page, $perPage)->all();
        
        $lastPage = max(1, (int)ceil($total / $perPage));
        
        return [
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
            ],
            'data' => $records
        ];
    }

    public function getById(int $id): ?array
    {
        $model = Model::find($id);
        return $model ? $model->toArray() : null;
    }

    public function create(array $data): array
    {
        $model = Model::create($data);
        if ($model) {
            return ['success' => true, 'data' => $model->toArray()];
        }
        return ['success' => false, 'message' => 'Failed to create'];
    }

    public function update(int $id, array $data): array
    {
        $model = Model::find($id);
        if (!$model) return ['success' => false, 'message' => 'Not found'];

        $allowed = ['role', 'name', 'email', 'phone_number', 'gender', 'address', 'note'];
        foreach ($data as $key => $val) {
            if (in_array($key, $allowed)) $model->$key = $val;
        }

        if ($model->save()) {
            return ['success' => true, 'data' => $model->toArray()];
        }
        return ['success' => false, 'message' => 'Failed to update'];
    }

    public function delete(int $id): array
    {
        $model = Model::find($id);
        if (!$model) return ['success' => false, 'message' => 'Not found'];
        
        if ($model->delete()) return ['success' => true];
        return ['success' => false, 'message' => 'Failed to delete'];
    }
}
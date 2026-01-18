<?php

namespace App\Modules\Users;

use App\Core\Controller as BaseController;
use App\Modules\Users\Service;
use App\Core\App;

class Controller extends BaseController
{
    protected Service $service;

    public function __construct()
    {
        $this->service = new Service();
    }

    public function index(): array
    {
        return $this->success($this->service->getAll());
    }

    public function show(string $id): array
    {
        $data = $this->service->getById((int)$id);
        return $data ? $this->success($data) : $this->error('Not found', [], 404);
    }

    public function store(): array
    {
        $result = $this->service->create(App::request()->all());
        return $result['success'] 
            ? $this->success($result['data'], 'Created', 201)
            : $this->error($result['message'], [], 400);
    }

    public function update(string $id): array
    {
        $result = $this->service->update((int)$id, App::request()->all());
        return $result['success']
            ? $this->success($result['data'], 'Updated')
            : $this->error($result['message'], [], 400);
    }

    public function destroy(string $id): array
    {
        $result = $this->service->delete((int)$id);
        return $result['success']
            ? $this->success([], 'Deleted')
            : $this->error($result['message'], [], 404);
    }
}
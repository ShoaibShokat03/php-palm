<?php

namespace App\Modules\Users;

use App\Core\Module as BaseModule;
use App\Modules\Users\Controller;
use PhpPalm\Core\Route;

class Module extends BaseModule
{
    public function __construct()
    {
        parent::__construct('Users', '/users');
    }

    public function registerRoutes(): void
    {
        $c = new Controller();
        Route::get('/users', [$c, 'index']);
        Route::get('/users/{id}', [$c, 'show']);
        Route::post('/users', [$c, 'store']);
        Route::put('/users/{id}', [$c, 'update']);
        Route::delete('/users/{id}', [$c, 'destroy']);
    }
}
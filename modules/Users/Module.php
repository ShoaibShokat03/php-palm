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
        Route::get('/', [$c, 'index']);
        Route::get('/{id}', [$c, 'show']);
        Route::post('/', [$c, 'store']);
        Route::put('/{id}', [$c, 'update']);
        Route::delete('/{id}', [$c, 'destroy']);
    }
}
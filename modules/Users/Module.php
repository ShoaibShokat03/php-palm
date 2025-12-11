<?php

namespace App\Modules\Users;

use App\Core\Module as BaseModule;
use App\Modules\Users\Controller;
use PhpPalm\Core\Route;

/**
 * Note: Routes can also be defined using PHP 8 attributes in Controller
 * Example: #[Get('/users')] on index() method
 */

/**
 * Users Module
 */
class Module extends BaseModule
{
    public function __construct()
    {
        parent::__construct('Users', '/users');
    }

    public function registerRoutes(): void
    {
        // Routes are registered via Controller attributes or manually here
        // Using Controller class reference (DI will handle instantiation)
        Route::get('/users', [Controller::class, 'index']);
        Route::get('/users/{id}', [Controller::class, 'show']);
        Route::post('/users', [Controller::class, 'store']);
        Route::put('/users/{id}', [Controller::class, 'update']);
        Route::delete('/users/{id}', [Controller::class, 'destroy']);
    }
}
<?php

namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Auth;

/**
 * Authentication Middleware
 * 
 * Requires user to be authenticated (valid bearer token)
 * 
 * Usage:
 * Route::get('/path', MiddlewareHelper::use('AuthMiddleware', [$controller, 'method']));
 * 
 * Or use Auth::guard() directly:
 * Route::get('/path', Auth::guard([$controller, 'method']));
 */
class AuthMiddleware extends Middleware
{
    /**
     * Handle the request - require authentication
     */
    public function handle(callable $handler, ...$args)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return $this->error('Authentication required', 401);
        }

        // User is authenticated, proceed with handler
        return $this->wrap($handler, ...$args);
    }
}


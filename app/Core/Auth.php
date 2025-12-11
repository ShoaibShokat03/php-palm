<?php

namespace App\Core;

use PhpPalm\Core\Request;
use App\Modules\User\Service as UserService;

/**
 * Authentication Facade
 * Easy-to-use authentication system for php-palm framework
 * 
 * Usage:
 * - Auth::user() - Get current authenticated user
 * - Auth::check() - Check if user is authenticated
 * - Auth::id() - Get current user ID
 * - Auth::guard($handler) - Protect a route handler
 */
class Auth
{
    protected static ?UserService $userService = null;
    protected static ?array $currentUser = null;
    protected static bool $userChecked = false;

    /**
     * Get UserService instance
     */
    protected static function getUserService(): UserService
    {
        if (self::$userService === null) {
            self::$userService = new UserService();
        }
        return self::$userService;
    }

    /**
     * Get current authenticated user
     * Returns null if not authenticated
     */
    public static function user(): ?array
    {
        if (!self::$userChecked) {
            self::loadUser();
        }
        return self::$currentUser;
    }

    /**
     * Check if user is authenticated
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Get current user ID
     * Returns null if not authenticated
     */
    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int)($user['id'] ?? null) : null;
    }

    /**
     * Check if user has specific role
     */
    public static function hasRole(string $role): bool
    {
        $user = self::user();
        return $user && ($user['role'] ?? null) === $role;
    }

    /**
     * Check if user has any of the specified roles
     */
    public static function hasAnyRole(array $roles): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        $userRole = $user['role'] ?? null;
        return in_array($userRole, $roles, true);
    }

    /**
     * Require authentication - throws exception if not authenticated
     */
    public static function requireAuth(): array
    {
        if (!self::check()) {
            http_response_code(401);
            return [
                'status' => 'error',
                'message' => 'Authentication required',
                'code' => 'UNAUTHENTICATED'
            ];
        }
        return [];
    }

    /**
     * Require specific role - throws exception if user doesn't have role
     */
    public static function requireRole(string $role): array
    {
        $authError = self::requireAuth();
        if (!empty($authError)) {
            return $authError;
        }

        if (!self::hasRole($role)) {
            http_response_code(403);
            return [
                'status' => 'error',
                'message' => 'Insufficient permissions',
                'code' => 'FORBIDDEN'
            ];
        }
        return [];
    }

    /**
     * Require any of the specified roles
     */
    public static function requireAnyRole(array $roles): array
    {
        $authError = self::requireAuth();
        if (!empty($authError)) {
            return $authError;
        }

        if (!self::hasAnyRole($roles)) {
            http_response_code(403);
            return [
                'status' => 'error',
                'message' => 'Insufficient permissions',
                'code' => 'FORBIDDEN'
            ];
        }
        return [];
    }

    /**
     * Protect a route handler with authentication
     * Usage: Route::get('/path', Auth::guard([$controller, 'method']));
     */
    public static function guard(callable $handler): callable
    {
        return function (...$args) use ($handler) {
            $error = self::requireAuth();
            if (!empty($error)) {
                return $error;
            }
            return call_user_func_array($handler, $args);
        };
    }

    /**
     * Protect a route handler with role requirement
     * Usage: Route::get('/path', Auth::guardRole('admin', [$controller, 'method']));
     */
    public static function guardRole(string $role, callable $handler): callable
    {
        return function (...$args) use ($role, $handler) {
            $error = self::requireRole($role);
            if (!empty($error)) {
                return $error;
            }
            return call_user_func_array($handler, $args);
        };
    }

    /**
     * Protect a route handler with any role requirement
     * Usage: Route::get('/path', Auth::guardAnyRole(['admin', 'doctor'], [$controller, 'method']));
     */
    public static function guardAnyRole(array $roles, callable $handler): callable
    {
        return function (...$args) use ($roles, $handler) {
            $error = self::requireAnyRole($roles);
            if (!empty($error)) {
                return $error;
            }
            return call_user_func_array($handler, $args);
        };
    }

    /**
     * Load user from bearer token
     */
    protected static function loadUser(): void
    {
        self::$userChecked = true;
        self::$currentUser = null;

        $token = Request::bearerToken();
        if (!$token) {
            return;
        }

        $userService = self::getUserService();
        $user = $userService->verifyToken($token);
        
        if ($user) {
            self::$currentUser = $user;
        }
    }

    /**
     * Clear current user (for testing/logout)
     */
    public static function clear(): void
    {
        self::$currentUser = null;
        self::$userChecked = false;
    }
}


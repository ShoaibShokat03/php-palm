<?php

namespace App\Core;

use App\Core\Security\Session;
use PhpPalm\Core\Request;
use App\Modules\Users\Module as UserModule;

/**
 * App Facade
 * 
 * Provides easy access to core application components.
 * 
 * Usage:
 * - App::session()->set('key', 'value');
 * - App::request()->get('param');
 * - App::config('app_name');
 * - App::user()->isLoggedIn(); (Conceptual, depends on UserModule imp)
 */
class App
{
    protected static ?Request $requestInstance = null;
    protected static array $config = [];
    protected static bool $configLoaded = false;

    /**
     * Get Session instance (Proxy)
     */
    public static function session()
    {
        return new class {
            public function set(string $key, $value): void
            {
                Session::set($key, $value);
            }

            public function get(string $key, $default = null)
            {
                return Session::get($key, $default);
            }

            public function remove(string $key): void
            {
                Session::remove($key);
            }

            public function destroy(): void
            {
                Session::destroy();
            }

            public function has(string $key): bool
            {
                return Session::has($key);
            }

            public function regenerate(): void
            {
                Session::regenerateId();
            }
        };
    }

    /**
     * Get Request instance
     */
    public static function request(): Request
    {
        if (self::$requestInstance === null) {
            self::$requestInstance = new Request();
        }
        return self::$requestInstance;
    }

    /**
     * Get User Access
     * Returns an object giving access to User related functionality
     */
    public static function user()
    {
        return new class {
            /**
             * Get current logged in user
             * Usage: App::user()->get()
             */
            public function get()
            {
                // Get user ID from session
                $userId = Session::get('user_id');

                if (!$userId) {
                    // Fallback to 'user' key if 'user_id' not found (legacy support)
                    $user = Session::get('user');
                    if ($user && is_object($user)) {
                        return $user;
                    }
                    // If user is array and looks like a record, return as object/array
                    return $user;
                }

                // Get User Model Class from config
                $modelClass = App::config('user_model', \App\Modules\Users\Model::class);

                if (class_exists($modelClass) && method_exists($modelClass, 'find')) {
                    // Find user by ID using Model
                    return $modelClass::find($userId);
                }

                return null;
            }

            /**
             * Set current logged in user
             * Usage: App::user()->set($userModel)
             */
            public function set($user)
            {
                if (is_object($user) && isset($user->id)) {
                    Session::set('user_id', $user->id);
                    // Also store basic info for quick access if needed
                    Session::set('user', $user->toArray());
                } elseif (is_array($user) && isset($user['id'])) {
                    Session::set('user_id', $user['id']);
                    Session::set('user', $user);
                } else {
                    // Just store what we got (legacy)
                    Session::set('user', $user);
                }
            }

            /**
             * Check if user is logged in
             */
            public function isLoggedIn(): bool
            {
                return Session::has('user_id') || Session::has('user');
            }

            /**
             * Logout user
             */
            public function logout(): void
            {
                Session::remove('user');
                Session::remove('user_id');
                Session::regenerateId();
            }
        };
    }

    /**
     * Get Config value
     * Usage: App::config('name', 'Default Name')
     */
    public static function config(string $key, $default = null)
    {
        self::loadConfig();

        // Support dot notation for nested keys (e.g. 'app.name')
        $array = self::$config;

        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (isset($array[$segment]) && is_array($array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Generate URL with application base path included
     * 
     * Usage: 
     *   App::route('/dashboard') -> '/subfolder/dashboard'
     *   App::route('/users', ['page' => 2, 'search' => 'john']) -> '/subfolder/users?page=2&search=john'
     */
    public static function route(string $path, array $params = []): string
    {
        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

        // Ensure path starts with /
        if (!empty($path) && $path[0] !== '/') {
            $path = '/' . $path;
        }

        $url = $basePath . $path;

        // Add query parameters if provided
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Redirect to a URL with optional query parameters
     * 
     * Usage:
     *   App::redirect('https://example.com')
     *   App::redirect('/dashboard', ['message' => 'Success'])
     */
    public static function redirect(string $url, array $params = []): never
    {
        // Add query parameters if provided
        if (!empty($params)) {
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $url .= $separator . http_build_query($params);
        }

        header('Location: ' . $url);
        exit;
    }

    /**
     * Redirect to a route with base path and optional query parameters
     * 
     * Usage:
     *   App::redirectToRoute('/dashboard')
     *   App::redirectToRoute('/users', ['page' => 2, 'search' => 'john'])
     */
    public static function redirectToRoute(string $path, array $params = []): never
    {
        $url = self::route($path, $params);
        header('Location: ' . $url);
        exit;
    }
    protected static function loadConfig(): void
    {
        if (self::$configLoaded) {
            return;
        }

        $configPath = dirname(__DIR__, 2) . '/config';

        if (is_dir($configPath)) {
            $files = glob($configPath . '/*.config.php');
            foreach ($files as $file) {
                $config = require $file;
                if (is_array($config)) {
                    // Use filename as top-level key if not app.config.php
                    // or merge into main config? 
                    // Strategy: Merge 'app' config at top level, others scoped by filename

                    $filename = basename($file, '.config.php');
                    if ($filename === 'app') {
                        self::$config = array_merge(self::$config, $config);
                    } else {
                        self::$config[$filename] = $config;
                    }
                }
            }
        }

        self::$configLoaded = true;
    }
}

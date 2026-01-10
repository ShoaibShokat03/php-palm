<?php

namespace Frontend\Palm {
    require_once __DIR__ . '/ErrorHandler.php';

    // Initialize error handler
    ErrorHandler::init();

    if (!function_exists(__NAMESPACE__ . '\When')) {
        /**
         * Conditional rendering helper - React-like
         * Usage: When($condition, fn() => echo 'content', fn() => echo 'else content')
         */
        function When(mixed $condition, callable $then, ?callable $else = null): void
        {
            $result = is_callable($condition) ? $condition() : (bool)$condition;
            if ($result) {
                $then();
            } elseif ($else !== null) {
                $else();
            }
        }
    }

    if (!function_exists(__NAMESPACE__ . '\Each')) {
        /**
         * Loop helper - React-like
         * Usage: Each($items, fn($item, $index) => echo $item)
         */
        function Each(iterable $items, callable $callback): void
        {
            $index = 0;
            foreach ($items as $key => $item) {
                $callback($item, $index, $key);
                $index++;
            }
        }
    }

    if (!function_exists(__NAMESPACE__ . '\Show')) {
        /**
         * Show helper - conditional rendering with cleaner syntax
         * Usage: Show($condition, fn() => echo 'content')
         */
        function Show(mixed $condition, callable $render): void
        {
            $result = is_callable($condition) ? $condition() : (bool)$condition;
            if ($result) {
                $render();
            }
        }
    }

    if (!function_exists(__NAMESPACE__ . '\validate')) {
        /**
         * Form validation helper
         * Usage: $validator = validate($_POST); $validator->rule('email', 'required|email');
         */
        function validate(array $data = []): FormValidator
        {
            require_once __DIR__ . '/FormValidator.php';
            return new FormValidator($data ?: ($_POST ?? []));
        }
    }

    // ============================================
    // URL & Route Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\url')) {
        /**
         * Generate URL from path
         * Usage: url('/about') or url('/user/{id}', ['id' => 5])
         */
        function url(string $path = '', array $params = []): string
        {
            // Replace placeholders like {id} with values from $params
            foreach ($params as $key => $value) {
                $path = str_replace('{' . $key . '}', (string)$value, $path);
            }

            // Ensure path starts with /
            if ($path !== '' && !str_starts_with($path, '/')) {
                $path = '/' . $path;
            }

            // Get base URL from server
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = $protocol . '://' . $host;

            return $base . ($path ?: '/');
        }
    }

    if (!function_exists(__NAMESPACE__ . '\route')) {
        /**
         * Generate URL from named route
         * Usage: route('about') or route('user.show', ['id' => 5])
         */
        function route(string $name, array $params = []): string
        {
            $path = Route::named($name, $params);
            if ($path === null) {
                // Fallback to name as path if route not found
                return url('/' . $name, $params);
            }
            return url($path);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\current_url')) {
        /**
         * Get current full URL
         */
        function current_url(): string
        {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';

            return $protocol . '://' . $host . $uri;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\current_path')) {
        /**
         * Get current path (without query string)
         */
        function current_path(): string
        {
            return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        }
    }

    if (!function_exists(__NAMESPACE__ . '\previous_url')) {
        /**
         * Get previous page URL from Referer header
         */
        function previous_url(?string $default = '/'): string
        {
            return $_SERVER['HTTP_REFERER'] ?? $default;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\back')) {
        /**
         * Redirect back to previous page
         */
        function back(?string $default = '/'): void
        {
            $url = previous_url($default);
            header('Location: ' . $url);
            exit;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\url_with')) {
        /**
         * Add query parameters to current URL
         */
        function url_with(array $params, ?string $url = null): string
        {
            $targetUrl = $url ?? current_url();
            $parsed = parse_url($targetUrl);
            $query = [];

            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $query);
            }

            $query = array_merge($query, $params);

            $scheme = $parsed['scheme'] ?? 'http';
            $host = $parsed['host'] ?? 'localhost';
            $path = $parsed['path'] ?? '/';
            $queryString = http_build_query($query);

            return $scheme . '://' . $host . $path . ($queryString ? '?' . $queryString : '');
        }
    }

    if (!function_exists(__NAMESPACE__ . '\url_without')) {
        /**
         * Remove query parameters from current URL
         */
        function url_without(array $keys, ?string $url = null): string
        {
            $targetUrl = $url ?? current_url();
            $parsed = parse_url($targetUrl);
            $query = [];

            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $query);
            }

            foreach ($keys as $key) {
                unset($query[$key]);
            }

            $scheme = $parsed['scheme'] ?? 'http';
            $host = $parsed['host'] ?? 'localhost';
            $path = $parsed['path'] ?? '/';
            $queryString = http_build_query($query);

            return $scheme . '://' . $host . $path . ($queryString ? '?' . $queryString : '');
        }
    }

    // ============================================
    // Asset Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\asset')) {
        /**
         * Get asset URL with version hash for cache busting
         * Usage: asset('css/app.css') -> /css/app.css?v=abc123
         */
        function asset(string $path, bool $version = true): string
        {
            // Remove leading slash if present
            $path = ltrim($path, '/');

            // Get base path from PALM_ROOT or default public directory
            $publicPath = defined('PALM_ROOT') ? PALM_ROOT . '/public' : __DIR__ . '/../../public';
            $fullPath = $publicPath . '/' . $path;

            // Add version hash for cache busting
            if ($version && file_exists($fullPath)) {
                $hash = substr(md5_file($fullPath), 0, 8);
                return '/' . $path . '?v=' . $hash;
            }

            return '/' . $path;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\css')) {
        /**
         * Generate CSS link tag(s) with progressive loading support
         * Usage: 
         *   css('app.css') - critical, loads immediately
         *   css('theme.css', ['defer' => true]) - deferred, loads after page
         *   css('vendor.css', ['preload' => true]) - preloaded, high priority
         */
        function css(string|array $files, array $attributes = []): string
        {
            require_once __DIR__ . '/ProgressiveResourceLoader.php';

            $files = is_array($files) ? $files : [$files];
            $html = '';
            $defer = $attributes['defer'] ?? false;
            $preload = $attributes['preload'] ?? false;

            // Remove progressive loading flags from attributes
            unset($attributes['defer'], $attributes['preload']);

            foreach ($files as $file) {
                // Ensure .css extension
                if (!str_ends_with($file, '.css')) {
                    $file .= '.css';
                }

                // Prepend /css/ if no directory in path
                if (!str_contains($file, '/')) {
                    $file = 'css/' . $file;
                }

                $url = asset($file);

                if ($preload) {
                    // Preload for high priority
                    ProgressiveResourceLoader::preload($url, 'style', 'text/css', $attributes);
                } elseif ($defer) {
                    // Defer for lazy loading
                    ProgressiveResourceLoader::defer($url, 'stylesheet', $attributes);
                } else {
                    // Critical - load immediately
                    $attrs = array_merge([
                        'rel' => 'stylesheet',
                        'href' => $url,
                    ], $attributes);

                    $attrString = '';
                    foreach ($attrs as $key => $value) {
                        $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                    }

                    $html .= '<link' . $attrString . '>' . "\n    ";
                }
            }

            return rtrim($html);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\js')) {
        /**
         * Generate JavaScript script tag(s) with progressive loading support
         * Usage:
         *   js('app.js') - critical, loads immediately
         *   js('vendor.js', ['defer' => true]) - deferred, loads after page
         *   js('analytics.js', ['async' => true, 'defer' => true]) - async + deferred
         */
        function js(string|array $files, array $attributes = []): string
        {
            require_once __DIR__ . '/ProgressiveResourceLoader.php';

            $files = is_array($files) ? $files : [$files];
            $html = '';
            $defer = $attributes['defer'] ?? false;
            $preload = $attributes['preload'] ?? false;

            // Remove progressive loading flags from attributes
            unset($attributes['defer'], $attributes['preload']);

            foreach ($files as $file) {
                // Ensure .js extension
                if (!str_ends_with($file, '.js')) {
                    $file .= '.js';
                }

                // Prepend /js/ if no directory in path
                if (!str_contains($file, '/')) {
                    $file = 'js/' . $file;
                }

                $url = asset($file);

                if ($preload) {
                    // Preload for high priority
                    ProgressiveResourceLoader::preload($url, 'script', 'application/javascript', $attributes);
                } elseif ($defer) {
                    // Defer for lazy loading
                    ProgressiveResourceLoader::defer($url, 'script', $attributes);
                } else {
                    // Critical - load immediately (but can still use async/defer)
                    $attrs = array_merge([
                        'src' => $url,
                    ], $attributes);

                    $attrString = '';
                    foreach ($attrs as $key => $value) {
                        if (is_bool($value) && $value) {
                            $attrString .= ' ' . htmlspecialchars($key);
                        } elseif (!is_bool($value)) {
                            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                        }
                    }

                    $html .= '<script' . $attrString . '></script>' . "\n    ";
                }
            }

            return rtrim($html);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\image')) {
        /**
         * Generate image tag with lazy loading support
         * Usage: image('logo.png') or image('photo.jpg', ['width' => 200, 'lazy' => true])
         */
        function image(string $path, array $attributes = []): string
        {
            // Extract lazy loading option
            $lazy = $attributes['lazy'] ?? false;
            unset($attributes['lazy']);

            // Prepend /images/ if no directory in path
            if (!str_contains($path, '/')) {
                $path = 'images/' . $path;
            }

            $url = asset($path);

            $attrs = array_merge([
                'src' => $url,
                'alt' => $attributes['alt'] ?? '',
            ], $attributes);

            if ($lazy) {
                // Use data-src for true lazy loading (loads when visible)
                $attrs['data-src'] = $attrs['src'];
                unset($attrs['src']);
                // Add placeholder or low-quality placeholder
                if (!isset($attrs['src'])) {
                    $attrs['src'] = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1 1\'%3E%3C/svg%3E';
                }
            }

            $attrString = '';
            foreach ($attrs as $key => $value) {
                if ($key === 'src' || $key === 'alt' || is_numeric($value)) {
                    $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                } elseif (!is_bool($value)) {
                    $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                } elseif ($value) {
                    $attrString .= ' ' . htmlspecialchars($key);
                }
            }

            return '<img' . $attrString . '>';
        }
    }

    if (!function_exists(__NAMESPACE__ . '\favicon')) {
        /**
         * Generate favicon link tag
         * Usage: favicon('favicon.ico')
         */
        function favicon(string $path = 'favicon.ico'): string
        {
            // Prepend / if no directory in path
            if (!str_starts_with($path, '/') && !str_contains($path, '/')) {
                $path = '/' . $path;
            } elseif (!str_starts_with($path, '/')) {
                $path = '/' . $path;
            }

            $url = asset(ltrim($path, '/'));

            return '<link rel="icon" type="image/x-icon" href="' . htmlspecialchars($url) . '">';
        }
    }

    // ============================================
    // Form Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\csrf_token')) {
        /**
         * Get CSRF token (uses backend CSRF if available, otherwise session-based)
         */
        function csrf_token(): string
        {
            // Try to use backend CSRF if available
            if (class_exists('\App\Core\Security\CSRF')) {
                return \App\Core\Security\CSRF::token();
            }

            // Fallback to session-based CSRF
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

            return $_SESSION['csrf_token'];
        }
    }

    if (!function_exists(__NAMESPACE__ . '\csrf_field')) {
        /**
         * Generate CSRF hidden input field
         */
        function csrf_field(string $name = 'csrf_token'): string
        {
            return '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars(csrf_token()) . '">';
        }
    }

    if (!function_exists(__NAMESPACE__ . '\csrf_meta')) {
        /**
         * Generate CSRF meta tag for JavaScript
         */
        function csrf_meta(string $name = 'csrf-token'): string
        {
            return '<meta name="' . htmlspecialchars($name) . '" content="' . htmlspecialchars(csrf_token()) . '">';
        }
    }

    if (!function_exists(__NAMESPACE__ . '\form_open')) {
        /**
         * Generate form opening tag with CSRF token
         */
        function form_open(string $action = '', string $method = 'POST', array $attributes = []): string
        {
            $method = strtoupper($method);
            $attrs = array_merge([
                'method' => $method === 'GET' ? 'GET' : 'POST',
                'action' => $action ?: current_url(),
            ], $attributes);

            $attrString = '';
            foreach ($attrs as $key => $value) {
                $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
            }

            $html = '<form' . $attrString . '>';

            // Add CSRF token for non-GET methods
            if ($method !== 'GET') {
                $html .= "\n    " . csrf_field();
            }

            return $html;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\form_close')) {
        /**
         * Generate form closing tag
         */
        function form_close(): string
        {
            return '</form>';
        }
    }

    if (!function_exists(__NAMESPACE__ . '\old')) {
        /**
         * Get old input value (from previous request after validation error)
         */
        function old(string $key, mixed $default = null): mixed
        {
            return $_SESSION['_old_input'][$key] ?? $default ?? null;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\input')) {
        /**
         * Generate input field
         */
        function input(string $name, mixed $value = null, array $attributes = []): string
        {
            $value = $value ?? old($name, '');
            $attrs = array_merge([
                'type' => 'text',
                'name' => $name,
                'value' => $value,
            ], $attributes);

            $attrString = '';
            foreach ($attrs as $key => $val) {
                if (!is_null($val) && $val !== '') {
                    $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
                }
            }

            return '<input' . $attrString . '>';
        }
    }

    if (!function_exists(__NAMESPACE__ . '\text')) {
        function text(string $name, mixed $value = null, array $attributes = []): string
        {
            return input($name, $value, array_merge(['type' => 'text'], $attributes));
        }
    }

    if (!function_exists(__NAMESPACE__ . '\email')) {
        function email(string $name, mixed $value = null, array $attributes = []): string
        {
            return input($name, $value, array_merge(['type' => 'email'], $attributes));
        }
    }

    if (!function_exists(__NAMESPACE__ . '\password')) {
        function password(string $name, array $attributes = []): string
        {
            return input($name, '', array_merge(['type' => 'password'], $attributes));
        }
    }

    if (!function_exists(__NAMESPACE__ . '\textarea')) {
        function textarea(string $name, mixed $value = null, array $attributes = []): string
        {
            $value = htmlspecialchars($value ?? old($name, ''));
            $attrs = array_merge([
                'name' => $name,
            ], $attributes);

            $attrString = '';
            foreach ($attrs as $key => $val) {
                if ($key !== 'value') {
                    $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
                }
            }

            return '<textarea' . $attrString . '>' . $value . '</textarea>';
        }
    }

    if (!function_exists(__NAMESPACE__ . '\select')) {
        function select(string $name, array $options, mixed $selected = null, array $attributes = []): string
        {
            $selected = $selected ?? old($name);
            $attrs = array_merge([
                'name' => $name,
            ], $attributes);

            $attrString = '';
            foreach ($attrs as $key => $val) {
                $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
            }

            $html = '<select' . $attrString . '>';
            foreach ($options as $value => $label) {
                $isSelected = ($value == $selected) ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars($value) . '"' . $isSelected . '>' . htmlspecialchars($label) . '</option>';
            }
            $html .= '</select>';

            return $html;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\checkbox')) {
        function checkbox(string $name, mixed $value = '1', bool $checked = false, array $attributes = []): string
        {
            $checked = $checked || old($name) == $value;
            $attrs = array_merge([
                'type' => 'checkbox',
                'name' => $name,
                'value' => $value,
            ], $attributes);

            if ($checked) {
                $attrs['checked'] = 'checked';
            }

            $attrString = '';
            foreach ($attrs as $key => $val) {
                $attrString .= ' ' . htmlspecialchars($key);
                if ($key !== 'checked') {
                    $attrString .= '="' . htmlspecialchars($val) . '"';
                }
            }

            return '<input' . $attrString . '>';
        }
    }

    if (!function_exists(__NAMESPACE__ . '\radio')) {
        function radio(string $name, mixed $value, bool $checked = false, array $attributes = []): string
        {
            $checked = $checked || old($name) == $value;
            $attrs = array_merge([
                'type' => 'radio',
                'name' => $name,
                'value' => $value,
            ], $attributes);

            if ($checked) {
                $attrs['checked'] = 'checked';
            }

            $attrString = '';
            foreach ($attrs as $key => $val) {
                $attrString .= ' ' . htmlspecialchars($key);
                if ($key !== 'checked') {
                    $attrString .= '="' . htmlspecialchars($val) . '"';
                }
            }

            return '<input' . $attrString . '>';
        }
    }

    // ============================================
    // HTML Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\link_to')) {
        /**
         * Generate link tag
         */
        function link_to(string $url, string $text, array $attributes = []): string
        {
            $attrs = array_merge([
                'href' => $url,
            ], $attributes);

            $attrString = '';
            foreach ($attrs as $key => $value) {
                $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
            }

            return '<a' . $attrString . '>' . htmlspecialchars($text) . '</a>';
        }
    }

    if (!function_exists(__NAMESPACE__ . '\mailto')) {
        /**
         * Generate mailto link
         */
        function mailto(string $email, ?string $text = null, array $attributes = []): string
        {
            return link_to('mailto:' . $email, $text ?? $email, $attributes);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\meta')) {
        /**
         * Generate meta tag
         */
        function meta(string $name, string $content): string
        {
            return '<meta name="' . htmlspecialchars($name) . '" content="' . htmlspecialchars($content) . '">';
        }
    }

    // ============================================
    // Component Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\component')) {
        /**
         * Render a component
         * Usage: component('Button', ['text' => 'Click', 'type' => 'primary'])
         */
        function component(string $name, array $props = [], array $slots = []): string
        {
            require_once __DIR__ . '/ComponentRenderer.php';

            // Initialize built-in components on first use
            static $initialized = false;
            if (!$initialized) {
                ComponentRenderer::init();
                $initialized = true;
            }

            return ComponentRenderer::render($name, $props, $slots);
        }
    }

    // ============================================
    // Validation Error Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\has_error')) {
        /**
         * Check if field has validation error
         */
        function has_error(string $field): bool
        {
            $errors = $_SESSION['_validation_errors'] ?? [];
            return isset($errors[$field]);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\error')) {
        /**
         * Get validation error for field
         */
        function error(string $field, ?string $default = null): ?string
        {
            $errors = $_SESSION['_validation_errors'] ?? [];
            return $errors[$field] ?? $default;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\error_class')) {
        /**
         * Get error CSS class if field has error
         */
        function error_class(string $field, string $class = 'error'): string
        {
            return has_error($field) ? $class : '';
        }
    }

    // ============================================
    // View/Layout Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\layout')) {
        /**
         * Render layout with data
         */
        function layout(string $name, array $data = []): void
        {
            extract($data);
            $layoutPath = defined('PALM_ROOT')
                ? PALM_ROOT . '/src/layouts/' . $name . '.php'
                : __DIR__ . '/../../src/layouts/' . $name . '.php';

            if (file_exists($layoutPath)) {
                require $layoutPath;
            } else {
                throw new \RuntimeException("Layout '{$name}' not found at: {$layoutPath}");
            }
        }
    }

    if (!function_exists(__NAMESPACE__ . '\partial')) {
        /**
         * Render partial view
         */
        function partial(string $name, array $data = []): string
        {
            extract($data);
            $partialPath = defined('PALM_ROOT')
                ? PALM_ROOT . '/src/views/partials/' . $name . '.php'
                : __DIR__ . '/../../src/views/partials/' . $name . '.php';

            if (file_exists($partialPath)) {
                ob_start();
                require $partialPath;
                return ob_get_clean();
            }

            return ''; // Return empty if partial not found
        }
    }

    // ============================================
    // SEO Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\seo_meta')) {
        /**
         * Generate SEO meta tags
         */
        function seo_meta(array $data): string
        {
            $html = '';

            // Title
            if (isset($data['title'])) {
                $html .= '<title>' . htmlspecialchars($data['title']) . '</title>' . "\n    ";
            }

            // Description
            if (isset($data['description'])) {
                $html .= meta('description', $data['description']) . "\n    ";
            }

            // Keywords
            if (isset($data['keywords'])) {
                $keywords = is_array($data['keywords']) ? implode(', ', $data['keywords']) : $data['keywords'];
                $html .= meta('keywords', $keywords) . "\n    ";
            }

            // Open Graph
            if (isset($data['og'])) {
                foreach ($data['og'] as $key => $value) {
                    $html .= '<meta property="og:' . htmlspecialchars($key) . '" content="' . htmlspecialchars($value) . '">' . "\n    ";
                }
            }

            // Twitter Card
            if (isset($data['twitter'])) {
                foreach ($data['twitter'] as $key => $value) {
                    $html .= '<meta name="twitter:' . htmlspecialchars($key) . '" content="' . htmlspecialchars($value) . '">' . "\n    ";
                }
            }

            // Canonical URL
            if (isset($data['canonical'])) {
                $html .= '<link rel="canonical" href="' . htmlspecialchars($data['canonical']) . '">' . "\n    ";
            }

            return rtrim($html);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\schema')) {
        /**
         * Generate JSON-LD structured data (Schema.org)
         */
        function schema(string $type, array $data): string
        {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => $type,
            ] + $data;

            return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
        }
    }

    if (!function_exists(__NAMESPACE__ . '\meta_tags')) {
        /**
         * Generate multiple meta tags at once
         */
        function meta_tags(array $tags): string
        {
            $html = '';
            foreach ($tags as $name => $content) {
                $html .= meta($name, $content) . "\n    ";
            }
            return rtrim($html);
        }
    }

    // ============================================
    // Form Builder Helper
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\Form')) {
        /**
         * Create form builder instance
         */
        function Form(string $action = '', string $method = 'POST', array $attributes = []): FormBuilder
        {
            require_once __DIR__ . '/FormBuilder.php';
            return new FormBuilder($action, $method, $attributes);
        }
    }

    // ============================================
    // Internationalization (i18n) Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\__')) {
        /**
         * Translate string
         * Usage: __('Hello') or __('Hello :name', ['name' => 'John'])
         */
        function __(string $key, array $replace = [], ?string $locale = null): string
        {
            require_once __DIR__ . '/Translator.php';
            return Translator::translate($key, $replace, $locale);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\trans_choice')) {
        /**
         * Translate with pluralization
         * Usage: trans_choice('{0} No items|{1} One item|[2,*] :count items', $count)
         */
        function trans_choice(string $key, int $number, array $replace = [], ?string $locale = null): string
        {
            require_once __DIR__ . '/Translator.php';
            return Translator::choice($key, $number, $replace, $locale);
        }
    }

    // ============================================
    // PWA Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\pwa_meta')) {
        /**
         * Generate PWA meta tags
         */
        function pwa_meta(array $config = []): string
        {
            require_once __DIR__ . '/PwaGenerator.php';
            PwaGenerator::init(defined('PALM_ROOT') ? PALM_ROOT : __DIR__ . '/../..');
            return PwaGenerator::generateMetaTags($config);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\pwa_sw_script')) {
        /**
         * Generate service worker registration script
         */
        function pwa_sw_script(): string
        {
            require_once __DIR__ . '/PwaGenerator.php';
            return PwaGenerator::getServiceWorkerScript();
        }
    }

    // ============================================
    // Dark Mode Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\dark_mode_script')) {
        /**
         * Generate dark mode toggle script
         */
        function dark_mode_script(): string
        {
            require_once __DIR__ . '/DarkMode.php';
            return DarkMode::getToggleScript();
        }
    }

    if (!function_exists(__NAMESPACE__ . '\dark_mode_button')) {
        /**
         * Generate dark mode toggle button
         */
        function dark_mode_button(string $label = 'Toggle Theme'): string
        {
            require_once __DIR__ . '/DarkMode.php';
            return DarkMode::getToggleButton($label);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\dark_mode_css')) {
        /**
         * Generate dark mode CSS variables
         */
        function dark_mode_css(): string
        {
            require_once __DIR__ . '/DarkMode.php';
            return DarkMode::getThemeCss();
        }
    }

    // ============================================
    // Image Optimization Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\responsive_image')) {
        /**
         * Generate responsive image with srcset
         */
        function responsive_image(string $imagePath, array $options = []): string
        {
            require_once __DIR__ . '/ImageOptimizer.php';
            $baseDir = defined('PALM_ROOT') ? PALM_ROOT : __DIR__ . '/../..';
            ImageOptimizer::init($baseDir);
            return ImageOptimizer::responsive($imagePath, $options);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\picture_element')) {
        /**
         * Generate picture element with multiple sources
         */
        function picture_element(string $imagePath, array $options = []): string
        {
            require_once __DIR__ . '/ImageOptimizer.php';
            $baseDir = defined('PALM_ROOT') ? PALM_ROOT : __DIR__ . '/../..';
            ImageOptimizer::init($baseDir);
            return ImageOptimizer::picture($imagePath, $options);
        }
    }

    // ============================================
    // Accessibility Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\skip_link')) {
        /**
         * Generate skip link for keyboard navigation
         */
        function skip_link(string $target = '#main', string $text = 'Skip to main content'): string
        {
            require_once __DIR__ . '/A11yHelper.php';
            return A11yHelper::skipLink($target, $text);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\sr_only')) {
        /**
         * Generate screen reader only text
         */
        function sr_only(string $text): string
        {
            require_once __DIR__ . '/A11yHelper.php';
            return A11yHelper::srOnly($text);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\a11y_form_field')) {
        /**
         * Generate accessible form field
         */
        function a11y_form_field(string $type, string $name, string $label, array $options = []): string
        {
            require_once __DIR__ . '/A11yHelper.php';
            return A11yHelper::formField($type, $name, $label, $options);
        }
    }

    // ============================================
    // Analytics Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\analytics')) {
        /**
         * Render analytics scripts
         */
        function analytics(): string
        {
            require_once __DIR__ . '/AnalyticsHelper.php';
            return AnalyticsHelper::render();
        }
    }

    // ============================================
    // Breadcrumb Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\breadcrumb')) {
        /**
         * Generate breadcrumb navigation
         */
        function breadcrumb(array $options = []): string
        {
            require_once __DIR__ . '/Breadcrumb.php';
            return Breadcrumb::render($options);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\breadcrumb_add')) {
        /**
         * Add breadcrumb item
         */
        function breadcrumb_add(string $label, ?string $url = null): void
        {
            require_once __DIR__ . '/Breadcrumb.php';
            Breadcrumb::add($label, $url);
        }
    }

    // ============================================
    // Pagination Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\pagination')) {
        /**
         * Generate pagination links
         */
        function pagination(int $currentPage, int $totalPages, array $options = []): string
        {
            require_once __DIR__ . '/Pagination.php';
            return Pagination::render($currentPage, $totalPages, $options);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\pagination_info')) {
        /**
         * Generate pagination info text
         */
        function pagination_info(int $currentPage, int $totalItems, int $perPage): string
        {
            require_once __DIR__ . '/Pagination.php';
            return Pagination::info($currentPage, $totalItems, $perPage);
        }
    }

    // ============================================
    // Email Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\email_link')) {
        /**
         * Generate email link
         */
        function email_link(string $email, ?string $text = null, array $options = []): string
        {
            require_once __DIR__ . '/EmailHelper.php';
            return EmailHelper::mailto($email, $text, $options);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\email_obfuscate')) {
        /**
         * Obfuscate email to prevent spam
         */
        function email_obfuscate(string $email): string
        {
            require_once __DIR__ . '/EmailHelper.php';
            return EmailHelper::obfuscate($email);
        }
    }

    // ============================================
    // Social Share Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\social_share')) {
        /**
         * Generate social share links
         */
        function social_share(string $url, string $title, ?string $description = null, array $options = []): string
        {
            require_once __DIR__ . '/SocialShare.php';
            return SocialShare::render($url, $title, $description, $options);
        }
    }

    // ============================================
    // Flash Message Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\flash')) {
        /**
         * Get or set flash message
         */
        function flash(?string $key = null, ?string $message = null, string $type = 'info'): mixed
        {
            require_once __DIR__ . '/FlashMessage.php';

            if ($key === null) {
                // Get all flash messages
                return $_SESSION['_flash'] ?? [];
            }

            if ($message === null) {
                // Get flash message
                return FlashMessage::get($key);
            }

            // Set flash message
            FlashMessage::set($key, $message, $type);
            return null;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\flash_render')) {
        /**
         * Render flash message as HTML
         */
        function flash_render(string $key, array $options = []): string
        {
            require_once __DIR__ . '/FlashMessage.php';
            return FlashMessage::render($key, $options);
        }
    }

    // ============================================
    // Time Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\time_ago')) {
        /**
         * Human-readable relative time
         */
        function time_ago(string|int|\DateTime $date): string
        {
            require_once __DIR__ . '/TimeHelper.php';
            return TimeHelper::ago($date);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\time_format')) {
        /**
         * Format date/time
         */
        function time_format(string|int|\DateTime $date, ?string $format = null): string
        {
            require_once __DIR__ . '/TimeHelper.php';
            return TimeHelper::format($date, $format);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\time_date')) {
        /**
         * Format date only
         */
        function time_date(string|int|\DateTime $date, string $format = 'Y-m-d'): string
        {
            require_once __DIR__ . '/TimeHelper.php';
            return TimeHelper::date($date, $format);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\time_iso')) {
        /**
         * Format as ISO 8601
         */
        function time_iso(string|int|\DateTime $date): string
        {
            require_once __DIR__ . '/TimeHelper.php';
            return TimeHelper::iso($date);
        }
    }

    // ============================================
    // Mail Helpers (Backend Integration)
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\mail_to')) {
        /**
         * Send email (backend)
         */
        function mail_to(string $email, ?string $name = null): \App\Core\Mail\MailMessage
        {
            require_once __DIR__ . '/../Core/Mail/Mail.php';
            return \App\Core\Mail\Mail::to($email, $name);
        }
    }

    // ============================================
    // API Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\api')) {
        /**
         * Call backend API from view
         * Usage: api('products.index', ['cache' => 3600])
         */
        function api(string $endpoint, array $options = []): mixed
        {
            require_once __DIR__ . '/ApiHelper.php';
            return ApiHelper::call($endpoint, $options);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\api_get')) {
        /**
         * GET API request
         */
        function api_get(string $endpoint, array $options = []): mixed
        {
            require_once __DIR__ . '/ApiHelper.php';
            return ApiHelper::get($endpoint, $options);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\api_post')) {
        /**
         * POST API request
         */
        function api_post(string $endpoint, array $data = [], array $options = []): mixed
        {
            require_once __DIR__ . '/ApiHelper.php';
            return ApiHelper::post($endpoint, $data, $options);
        }
    }

    // ============================================
    // File Upload Helpers
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\file_input')) {
        /**
         * Generate file input with upload preview
         */
        function file_input(string $name, array $options = []): string
        {
            require_once __DIR__ . '/FileUploadHelper.php';
            return FileUploadHelper::input($name, $options);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\image_upload')) {
        /**
         * Generate image upload input with preview
         */
        function image_upload(string $name, array $options = []): string
        {
            require_once __DIR__ . '/FileUploadHelper.php';
            return FileUploadHelper::image($name, $options);
        }
    }

    // ============================================
    // Security Helpers (XSS, CSP, Headers)
    // ============================================

    if (!function_exists(__NAMESPACE__ . '\e')) {
        /**
         * Escape output (XSS protection)
         */
        function e(mixed $value, bool $doubleEncode = true): string
        {
            require_once __DIR__ . '/XssProtection.php';
            return XssProtection::escape($value, $doubleEncode);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\e_attr')) {
        /**
         * Escape HTML attribute
         */
        function e_attr(mixed $value): string
        {
            require_once __DIR__ . '/XssProtection.php';
            return XssProtection::escapeAttr($value);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\e_js')) {
        /**
         * Escape JavaScript string
         */
        function e_js(mixed $value): string
        {
            require_once __DIR__ . '/XssProtection.php';
            return XssProtection::escapeJs($value);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\clean_html')) {
        /**
         * Clean HTML (strip dangerous content)
         */
        function clean_html(string $html, array $allowedTags = []): string
        {
            require_once __DIR__ . '/XssProtection.php';
            return XssProtection::clean($html, $allowedTags);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\csp')) {
        /**
         * Generate Content Security Policy
         */
        function csp(string $preset = 'default'): string
        {
            require_once __DIR__ . '/CspGenerator.php';
            return match ($preset) {
                'strict' => CspGenerator::strict(),
                'development' => CspGenerator::development(),
                default => CspGenerator::default(),
            };
        }
    }

    if (!function_exists(__NAMESPACE__ . '\csp_meta')) {
        /**
         * Generate CSP meta tag
         */
        function csp_meta(string $policy): string
        {
            require_once __DIR__ . '/CspGenerator.php';
            return CspGenerator::metaTag($policy);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\security_headers')) {
        /**
         * Set security headers
         */
        function security_headers(array $options = []): void
        {
            require_once __DIR__ . '/SecurityHeaders.php';
            SecurityHeaders::set($options);
        }
    }
}

namespace {
    if (!function_exists('When')) {
        function When(mixed $condition, callable $then, ?callable $else = null): void
        {
            \Frontend\Palm\When($condition, $then, $else);
        }
    }

    if (!function_exists('Each')) {
        function Each(iterable $items, callable $callback): void
        {
            \Frontend\Palm\Each($items, $callback);
        }
    }

    if (!function_exists('Show')) {
        function Show(mixed $condition, callable $render): void
        {
            \Frontend\Palm\Show($condition, $render);
        }
    }

    if (!function_exists('validate')) {
        function validate(array $data = []): \Frontend\Palm\FormValidator
        {
            return \Frontend\Palm\validate($data);
        }
    }

    // URL & Route Helpers (global namespace)
    if (!function_exists('url')) {
        function url(string $path = '', array $params = []): string
        {
            return \Frontend\Palm\url($path, $params);
        }
    }

    if (!function_exists('route')) {
        function route(string $name, array $params = []): string
        {
            return \Frontend\Palm\route($name, $params);
        }
    }

    if (!function_exists('current_url')) {
        function current_url(): string
        {
            return \Frontend\Palm\current_url();
        }
    }

    if (!function_exists('current_path')) {
        function current_path(): string
        {
            return \Frontend\Palm\current_path();
        }
    }

    if (!function_exists('previous_url')) {
        function previous_url(?string $default = '/'): string
        {
            return \Frontend\Palm\previous_url($default);
        }
    }

    if (!function_exists('back')) {
        function back(?string $default = '/'): void
        {
            \Frontend\Palm\back($default);
        }
    }

    if (!function_exists('url_with')) {
        function url_with(array $params, ?string $url = null): string
        {
            return \Frontend\Palm\url_with($params, $url);
        }
    }

    if (!function_exists('url_without')) {
        function url_without(array $keys, ?string $url = null): string
        {
            return \Frontend\Palm\url_without($keys, $url);
        }
    }

    // Asset Helpers (global namespace)
    if (!function_exists('asset')) {
        function asset(string $path, bool $version = true): string
        {
            return \Frontend\Palm\asset($path, $version);
        }
    }

    if (!function_exists('css')) {
        function css(string|array $files, array $attributes = []): string
        {
            return \Frontend\Palm\css($files, $attributes);
        }
    }

    if (!function_exists('js')) {
        function js(string|array $files, array $attributes = []): string
        {
            return \Frontend\Palm\js($files, $attributes);
        }
    }

    if (!function_exists('image')) {
        function image(string $path, array $attributes = []): string
        {
            return \Frontend\Palm\image($path, $attributes);
        }
    }

    if (!function_exists('favicon')) {
        function favicon(string $path = 'favicon.ico'): string
        {
            return \Frontend\Palm\favicon($path);
        }
    }

    // Form Helpers (global namespace)
    if (!function_exists('csrf_token')) {
        function csrf_token(): string
        {
            return \Frontend\Palm\csrf_token();
        }
    }

    if (!function_exists('csrf_field')) {
        function csrf_field(string $name = 'csrf_token'): string
        {
            return \Frontend\Palm\csrf_field($name);
        }
    }

    if (!function_exists('csrf_meta')) {
        function csrf_meta(string $name = 'csrf-token'): string
        {
            return \Frontend\Palm\csrf_meta($name);
        }
    }

    if (!function_exists('form_open')) {
        function form_open(string $action = '', string $method = 'POST', array $attributes = []): string
        {
            return \Frontend\Palm\form_open($action, $method, $attributes);
        }
    }

    if (!function_exists('form_close')) {
        function form_close(): string
        {
            return \Frontend\Palm\form_close();
        }
    }

    if (!function_exists('old')) {
        function old(string $key, mixed $default = null): mixed
        {
            return \Frontend\Palm\old($key, $default);
        }
    }

    if (!function_exists('input')) {
        function input(string $name, mixed $value = null, array $attributes = []): string
        {
            return \Frontend\Palm\input($name, $value, $attributes);
        }
    }

    if (!function_exists('text')) {
        function text(string $name, mixed $value = null, array $attributes = []): string
        {
            return \Frontend\Palm\text($name, $value, $attributes);
        }
    }

    if (!function_exists('email')) {
        function email(string $name, mixed $value = null, array $attributes = []): string
        {
            return \Frontend\Palm\email($name, $value, $attributes);
        }
    }

    if (!function_exists('password')) {
        function password(string $name, array $attributes = []): string
        {
            return \Frontend\Palm\password($name, $attributes);
        }
    }

    if (!function_exists('textarea')) {
        function textarea(string $name, mixed $value = null, array $attributes = []): string
        {
            return \Frontend\Palm\textarea($name, $value, $attributes);
        }
    }

    if (!function_exists('select')) {
        function select(string $name, array $options, mixed $selected = null, array $attributes = []): string
        {
            return \Frontend\Palm\select($name, $options, $selected, $attributes);
        }
    }

    if (!function_exists('checkbox')) {
        function checkbox(string $name, mixed $value = '1', bool $checked = false, array $attributes = []): string
        {
            return \Frontend\Palm\checkbox($name, $value, $checked, $attributes);
        }
    }

    if (!function_exists('radio')) {
        function radio(string $name, mixed $value, bool $checked = false, array $attributes = []): string
        {
            return \Frontend\Palm\radio($name, $value, $checked, $attributes);
        }
    }

    // HTML Helpers (global namespace)
    if (!function_exists('link_to')) {
        function link_to(string $url, string $text, array $attributes = []): string
        {
            return \Frontend\Palm\link_to($url, $text, $attributes);
        }
    }

    if (!function_exists('mailto')) {
        function mailto(string $email, ?string $text = null, array $attributes = []): string
        {
            return \Frontend\Palm\mailto($email, $text, $attributes);
        }
    }

    if (!function_exists('meta')) {
        function meta(string $name, string $content): string
        {
            return \Frontend\Palm\meta($name, $content);
        }
    }

    // Component Helpers (global namespace)
    if (!function_exists('component')) {
        function component(string $name, array $props = [], array $slots = []): string
        {
            return \Frontend\Palm\component($name, $props, $slots);
        }
    }

    // Validation Error Helpers (global namespace)
    if (!function_exists('has_error')) {
        function has_error(string $field): bool
        {
            return \Frontend\Palm\has_error($field);
        }
    }

    if (!function_exists('error')) {
        function error(string $field, ?string $default = null): ?string
        {
            return \Frontend\Palm\error($field, $default);
        }
    }

    if (!function_exists('error_class')) {
        function error_class(string $field, string $class = 'error'): string
        {
            return \Frontend\Palm\error_class($field, $class);
        }
    }

    // View/Layout Helpers (global namespace)
    if (!function_exists('layout')) {
        function layout(string $name, array $data = []): void
        {
            \Frontend\Palm\layout($name, $data);
        }
    }

    if (!function_exists('partial')) {
        function partial(string $name, array $data = []): string
        {
            return \Frontend\Palm\partial($name, $data);
        }
    }

    // SEO Helpers (global namespace)
    if (!function_exists('seo_meta')) {
        function seo_meta(array $data): string
        {
            return \Frontend\Palm\seo_meta($data);
        }
    }

    if (!function_exists('schema')) {
        function schema(string $type, array $data): string
        {
            return \Frontend\Palm\schema($type, $data);
        }
    }

    if (!function_exists('meta_tags')) {
        function meta_tags(array $tags): string
        {
            return \Frontend\Palm\meta_tags($tags);
        }
    }

    // Form Builder Helper (global namespace)
    if (!function_exists('Form')) {
        function Form(string $action = '', string $method = 'POST', array $attributes = []): \Frontend\Palm\FormBuilder
        {
            return \Frontend\Palm\Form($action, $method, $attributes);
        }
    }

    // i18n Helpers (global namespace)
    if (!function_exists('__')) {
        function __(string $key, array $replace = [], ?string $locale = null): string
        {
            return \Frontend\Palm\__($key, $replace, $locale);
        }
    }

    if (!function_exists('trans_choice')) {
        function trans_choice(string $key, int $number, array $replace = [], ?string $locale = null): string
        {
            return \Frontend\Palm\trans_choice($key, $number, $replace, $locale);
        }
    }

    // PWA Helpers (global namespace)
    if (!function_exists('pwa_meta')) {
        function pwa_meta(array $config = []): string
        {
            return \Frontend\Palm\pwa_meta($config);
        }
    }

    if (!function_exists('pwa_sw_script')) {
        function pwa_sw_script(): string
        {
            return \Frontend\Palm\pwa_sw_script();
        }
    }

    // Dark Mode Helpers (global namespace)
    if (!function_exists('dark_mode_script')) {
        function dark_mode_script(): string
        {
            return \Frontend\Palm\dark_mode_script();
        }
    }

    if (!function_exists('dark_mode_button')) {
        function dark_mode_button(string $label = 'Toggle Theme'): string
        {
            return \Frontend\Palm\dark_mode_button($label);
        }
    }

    if (!function_exists('dark_mode_css')) {
        function dark_mode_css(): string
        {
            return \Frontend\Palm\dark_mode_css();
        }
    }

    // Image Optimization Helpers (global namespace)
    if (!function_exists('responsive_image')) {
        function responsive_image(string $imagePath, array $options = []): string
        {
            return \Frontend\Palm\responsive_image($imagePath, $options);
        }
    }

    if (!function_exists('picture_element')) {
        function picture_element(string $imagePath, array $options = []): string
        {
            return \Frontend\Palm\picture_element($imagePath, $options);
        }
    }

    // Accessibility Helpers (global namespace)
    if (!function_exists('skip_link')) {
        function skip_link(string $target = '#main', string $text = 'Skip to main content'): string
        {
            return \Frontend\Palm\skip_link($target, $text);
        }
    }

    if (!function_exists('sr_only')) {
        function sr_only(string $text): string
        {
            return \Frontend\Palm\sr_only($text);
        }
    }

    if (!function_exists('a11y_form_field')) {
        function a11y_form_field(string $type, string $name, string $label, array $options = []): string
        {
            return \Frontend\Palm\a11y_form_field($type, $name, $label, $options);
        }
    }

    // Analytics Helpers (global namespace)
    if (!function_exists('analytics')) {
        function analytics(): string
        {
            return \Frontend\Palm\analytics();
        }
    }

    // Breadcrumb Helpers (global namespace)
    if (!function_exists('breadcrumb')) {
        function breadcrumb(array $options = []): string
        {
            return \Frontend\Palm\breadcrumb($options);
        }
    }

    if (!function_exists('breadcrumb_add')) {
        function breadcrumb_add(string $label, ?string $url = null): void
        {
            \Frontend\Palm\breadcrumb_add($label, $url);
        }
    }

    // Pagination Helpers (global namespace)
    if (!function_exists('pagination')) {
        function pagination(int $currentPage, int $totalPages, array $options = []): string
        {
            return \Frontend\Palm\pagination($currentPage, $totalPages, $options);
        }
    }

    if (!function_exists('pagination_info')) {
        function pagination_info(int $currentPage, int $totalItems, int $perPage): string
        {
            return \Frontend\Palm\pagination_info($currentPage, $totalItems, $perPage);
        }
    }

    // Email Helpers (global namespace)
    if (!function_exists('email_link')) {
        function email_link(string $email, ?string $text = null, array $options = []): string
        {
            return \Frontend\Palm\email_link($email, $text, $options);
        }
    }

    if (!function_exists('email_obfuscate')) {
        function email_obfuscate(string $email): string
        {
            return \Frontend\Palm\email_obfuscate($email);
        }
    }

    // Social Share Helpers (global namespace)
    if (!function_exists('social_share')) {
        function social_share(string $url, string $title, ?string $description = null, array $options = []): string
        {
            return \Frontend\Palm\social_share($url, $title, $description, $options);
        }
    }

    // Flash Message Helpers (global namespace)
    if (!function_exists('flash')) {
        function flash(?string $key = null, ?string $message = null, string $type = 'info'): mixed
        {
            return \Frontend\Palm\flash($key, $message, $type);
        }
    }

    if (!function_exists('flash_render')) {
        function flash_render(string $key, array $options = []): string
        {
            return \Frontend\Palm\flash_render($key, $options);
        }
    }

    // Time Helpers (global namespace)
    if (!function_exists('time_ago')) {
        function time_ago(string|int|\DateTime $date): string
        {
            return \Frontend\Palm\time_ago($date);
        }
    }

    if (!function_exists('time_format')) {
        function time_format(string|int|\DateTime $date, ?string $format = null): string
        {
            return \Frontend\Palm\time_format($date, $format);
        }
    }

    if (!function_exists('time_date')) {
        function time_date(string|int|\DateTime $date, string $format = 'Y-m-d'): string
        {
            return \Frontend\Palm\time_date($date, $format);
        }
    }

    if (!function_exists('time_iso')) {
        function time_iso(string|int|\DateTime $date): string
        {
            return \Frontend\Palm\time_iso($date);
        }
    }

    // Mail Helper (global namespace) - Backend email sending
    if (!function_exists('mail_to')) {
        function mail_to(string $email, ?string $name = null): \App\Core\Mail\MailMessage
        {
            return \Frontend\Palm\mail_to($email, $name);
        }
    }

    // API Helpers (global namespace)
    if (!function_exists('api')) {
        function api(string $endpoint, array $options = []): mixed
        {
            return \Frontend\Palm\api($endpoint, $options);
        }
    }

    if (!function_exists('api_get')) {
        function api_get(string $endpoint, array $options = []): mixed
        {
            return \Frontend\Palm\api_get($endpoint, $options);
        }
    }

    if (!function_exists('api_post')) {
        function api_post(string $endpoint, array $data = [], array $options = []): mixed
        {
            return \Frontend\Palm\api_post($endpoint, $data, $options);
        }
    }

    // File Upload Helpers (global namespace)
    if (!function_exists('file_input')) {
        function file_input(string $name, array $options = []): string
        {
            return \Frontend\Palm\file_input($name, $options);
        }
    }

    if (!function_exists('image_upload')) {
        function image_upload(string $name, array $options = []): string
        {
            return \Frontend\Palm\image_upload($name, $options);
        }
    }

    // Security Helpers (global namespace)
    if (!function_exists('e')) {
        function e(mixed $value, bool $doubleEncode = true): string
        {
            return \Frontend\Palm\e($value, $doubleEncode);
        }
    }

    if (!function_exists('e_attr')) {
        function e_attr(mixed $value): string
        {
            return \Frontend\Palm\e_attr($value);
        }
    }

    if (!function_exists('e_js')) {
        function e_js(mixed $value): string
        {
            return \Frontend\Palm\e_js($value);
        }
    }

    if (!function_exists('clean_html')) {
        function clean_html(string $html, array $allowedTags = []): string
        {
            return \Frontend\Palm\clean_html($html, $allowedTags);
        }
    }

    if (!function_exists('csp')) {
        function csp(string $preset = 'default'): string
        {
            return \Frontend\Palm\csp($preset);
        }
    }

    if (!function_exists('csp_meta')) {
        function csp_meta(string $policy): string
        {
            return \Frontend\Palm\csp_meta($policy);
        }
    }

    if (!function_exists('security_headers')) {
        function security_headers(array $options = []): void
        {
            \Frontend\Palm\security_headers($options);
        }
    }

    // ==================== Google Authentication Helpers ====================

    if (!function_exists('google_auth_url')) {
        /**
         * Get Google OAuth authorization URL
         * 
         * @param array|null $scopes Optional scopes (default: ['openid', 'email', 'profile'])
         * @return string Authorization URL
         */
        function google_auth_url(?array $scopes = null): string
        {
            return \Frontend\Palm\GoogleAuth::getAuthUrl($scopes);
        }
    }

    if (!function_exists('google_auth_redirect')) {
        /**
         * Redirect to Google login
         */
        function google_auth_redirect(): void
        {
            \Frontend\Palm\GoogleAuth::redirect();
        }
    }

    if (!function_exists('google_auth_check')) {
        /**
         * Check if user is authenticated with Google
         * 
         * @return bool
         */
        function google_auth_check(): bool
        {
            return \Frontend\Palm\GoogleAuth::check();
        }
    }

    if (!function_exists('google_auth_user')) {
        /**
         * Get authenticated Google user
         * 
         * @return array|null User data or null if not authenticated
         */
        function google_auth_user(): ?array
        {
            return \Frontend\Palm\GoogleAuth::user();
        }
    }

    if (!function_exists('google_auth_id')) {
        /**
         * Get authenticated Google user ID
         * 
         * @return string|null User ID or null if not authenticated
         */
        function google_auth_id(): ?string
        {
            return \Frontend\Palm\GoogleAuth::id();
        }
    }

    if (!function_exists('google_auth_email')) {
        /**
         * Get authenticated Google user email
         * 
         * @return string|null User email or null if not authenticated
         */
        function google_auth_email(): ?string
        {
            return \Frontend\Palm\GoogleAuth::email();
        }
    }

    if (!function_exists('google_auth_name')) {
        /**
         * Get authenticated Google user name
         * 
         * @return string|null User name or null if not authenticated
         */
        function google_auth_name(): ?string
        {
            return \Frontend\Palm\GoogleAuth::name();
        }
    }

    if (!function_exists('google_auth_picture')) {
        /**
         * Get authenticated Google user picture URL
         * 
         * @return string|null User picture URL or null if not authenticated
         */
        function google_auth_picture(): ?string
        {
            return \Frontend\Palm\GoogleAuth::picture();
        }
    }

    if (!function_exists('google_auth_logout')) {
        /**
         * Logout Google authenticated user
         */
        function google_auth_logout(): void
        {
            \Frontend\Palm\GoogleAuth::logout();
        }
    }

    if (!function_exists(__NAMESPACE__ . '\upload_file')) {
        /**
         * Easy file upload helper
         * 
         * @param string $fieldName Form field name
         * @param array $options Configuration options
         * @return array Result of the upload
         */
        function upload_file(string $fieldName, array $options = []): array
        {
            require_once dirname(__DIR__) . '/Core/Security/FileUpload.php';
            require_once dirname(__DIR__) . '/Core/Events/Event.php';

            // Default path: root /uploads
            $root = defined('PALM_ROOT') ? PALM_ROOT : dirname(__DIR__, 2);
            $uploadPath = $options['path'] ?? ($root . '/uploads');

            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $uploader = new \App\Core\Security\FileUpload($uploadPath);

            if (isset($options['allowed_types'])) {
                $uploader->setAllowedMimeTypes($options['allowed_types']);
            }
            if (isset($options['allowed_extensions'])) {
                $uploader->setAllowedExtensions($options['allowed_extensions']);
            }
            if (isset($options['max_size'])) {
                $uploader->setMaxFileSize($options['max_size']);
            }

            $result = $uploader->upload($fieldName);

            if ($result['success']) {
                \App\Core\Events\Event::fire('file.uploaded', $result['file']);
            }

            return $result;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\event')) {
        /**
         * Dispatch an event
         */
        function event(string $name, $payload = null): array
        {
            require_once dirname(__DIR__) . '/Core/Events/Event.php';
            return \App\Core\Events\Event::fire($name, $payload);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\on')) {
        /**
         * Listen to an event
         */
        function on(string $event, callable $listener, int $priority = 0): void
        {
            require_once dirname(__DIR__) . '/Core/Events/Event.php';
            \App\Core\Events\Event::listen($event, $listener, $priority);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\app')) {
        /**
         * Get the container instance or resolve a class
         * 
         * @param string|null $abstract
         * @return mixed
         */
        function app(?string $abstract = null)
        {
            $container = \App\Core\Container::getInstance();
            if ($abstract === null) {
                return $container;
            }
            return $container->make($abstract);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\resolve')) {
        /**
         * Resolve a class from the container
         */
        function resolve(string $abstract)
        {
            return \App\Core\Container::getInstance()->make($abstract);
        }
    }
}

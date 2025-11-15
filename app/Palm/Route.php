<?php

namespace Frontend;

use Frontend\Palm\ComponentManager;

class Route
{
    protected static array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    protected static array $viewRegistry = [];
    protected static array $pathToSlug = [];

    protected static string $basePath = '';
    protected static string $currentPath = '/';
    protected static bool $spaRequest = false;

    public static function init(string $basePath): void
    {
        self::$basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    public static function get(string $path, callable $handler): void
    {
        self::register('GET', $path, $handler);
    }

    public static function post(string $path, callable $handler): void
    {
        self::register('POST', $path, $handler);
    }

    protected static function register(string $method, string $path, callable $handler): void
    {
        $normalized = self::normalizePath($path);

        if ($handler instanceof ViewHandler) {
            self::$pathToSlug[$normalized] = $handler->getSlug();
            self::$viewRegistry[$handler->getSlug()] = $handler->getData();
        }

        self::$routes[$method][$normalized] = $handler;
    }

    public static function dispatch(string $method, string $uri): void
    {
        self::$spaRequest = isset($_SERVER['HTTP_X_PALM_REQUEST']) && $_SERVER['HTTP_X_PALM_REQUEST'] === '1';

        $path = self::normalizePath(parse_url($uri, PHP_URL_PATH) ?? '/');
        self::$currentPath = $path;

        $handler = self::$routes[$method][$path] ?? null;

        if ($handler === null) {
            http_response_code(404);
            self::render('home.home', [
                'title' => '404 - Page Not Found',
                'message' => 'No route defined for ' . htmlspecialchars($path),
            ]);
            return;
        }

        $handler();
    }

    public static function view(string $slug, array $data = [], ?string $layout = null): ViewHandler
    {
        return new ViewHandler($slug, $data, $layout);
    }

    public static function render(string $slug, array $data = [], ?string $layout = null): void
    {
        $layoutIdentifier = $layout ?? ($data['layout'] ?? null);
        if (array_key_exists('layout', $data)) {
            unset($data['layout']);
        }

        self::$pathToSlug[self::$currentPath] = $slug;
        self::$viewRegistry[$slug] = $data + (self::$viewRegistry[$slug] ?? []);

        $base = self::$basePath;
        $viewPath = $base . '/views/' . str_replace('.', '/', $slug) . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(404);
            echo "<h1>View not found</h1><p>" . htmlspecialchars($slug) . "</p>";
            return;
        }

        $result = ComponentManager::render(function () use ($viewPath, $data) {
            extract($data);
            require $viewPath;
        });

        $content = $result['html'];
        $currentComponent = $result['component'];
        $currentScripts = $result['scripts'] ?? [];
        $title = $data['title'] ?? self::humanizeSlug($slug);
        $meta = $data['meta'] ?? [];
        $currentPath = self::$currentPath;
        $currentSlug = $slug;
        $clientViews = self::exportClientViews($slug, $data, $content, $currentComponent, $currentScripts);
        $routeMap = self::$pathToSlug;

        if (self::$spaRequest) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'slug' => $slug,
                'payload' => $clientViews[$slug],
                'routeMap' => $routeMap,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        extract($data);

        $initialScripts = $clientViews[$currentSlug]['scripts'] ?? [];
        $layoutPath = self::resolveLayoutPath($layoutIdentifier);
        require $layoutPath;
    }

    public static function currentPath(): string
    {
        return self::$currentPath;
    }

    protected static function exportClientViews(string $currentSlug, array $currentData, ?string $currentHtml = null, ?array $currentComponent = null, array $currentScripts = []): array
    {
        $views = [];
        foreach (self::$viewRegistry as $slug => $data) {
            if ($slug === $currentSlug && $currentHtml !== null) {
                $views[$slug] = self::buildPayloadFromHtml($slug, $currentData, $currentHtml, $currentComponent, $currentScripts);
                continue;
            }

            $views[$slug] = self::renderFragmentPayload($slug, $data);
        }
        return $views;
    }

    protected static function buildPayloadFromHtml(string $slug, array $data, string $html, ?array $component = null, array $scripts = []): array
    {
        return [
            'title' => $data['title'] ?? self::humanizeSlug($slug),
            'meta' => $data['meta'] ?? [],
            'html' => $html,
            'state' => $data['state'] ?? [],
            'component' => $component,
            'scripts' => $scripts,
        ];
    }

    protected static function renderFragmentPayload(string $slug, array $data): array
    {
        $base = self::$basePath;
        $viewPath = $base . '/views/' . str_replace('.', '/', $slug) . '.php';

        if (!file_exists($viewPath)) {
            return [
                'title' => self::humanizeSlug($slug),
                'meta' => [],
                'html' => "<div class=\"card\"><h2>Missing view</h2><p>" . htmlspecialchars($slug) . "</p></div>",
                'state' => [],
                'component' => null,
                'scripts' => [],
            ];
        }

        $title = $data['title'] ?? self::humanizeSlug($slug);
        $meta = $data['meta'] ?? [];

        $result = ComponentManager::render(function () use ($viewPath, $data) {
            extract($data);
            require $viewPath;
        });

        return [
            'title' => $title,
            'meta' => $meta,
            'html' => $result['html'],
            'state' => $data['state'] ?? [],
            'component' => $result['component'],
            'scripts' => $result['scripts'] ?? [],
        ];
    }

    protected static function normalizePath(?string $path): string
    {
        if ($path === null || $path === '' || $path === false) {
            return '/';
        }

        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        return $path;
    }

    protected static function humanizeSlug(string $slug): string
    {
        $parts = explode('.', $slug);
        $parts = array_map(fn($part) => ucfirst($part), $parts);
        return implode(' · ', $parts);
    }

    protected static function resolveLayoutPath(?string $layoutIdentifier): string
    {
        $base = self::$basePath . '/layouts';
        $fallback = $base . '/main.php';

        if ($layoutIdentifier === null) {
            return $fallback;
        }

        $normalized = trim($layoutIdentifier);
        if ($normalized === '') {
            return $fallback;
        }

        if (str_starts_with($normalized, 'layout.')) {
            $normalized = substr($normalized, 7);
        } elseif (str_starts_with($normalized, 'layouts.')) {
            $normalized = substr($normalized, 8);
        }

        $normalized = str_replace('\\', '/', $normalized);
        $normalized = str_replace('..', '', $normalized);
        $normalized = trim($normalized, '/');

        $path = $base . '/' . str_replace('.', '/', $normalized);
        if (!str_ends_with($path, '.php')) {
            $path .= '.php';
        }

        if (!file_exists($path)) {
            return $fallback;
        }

        return $path;
    }
}

class ViewHandler
{
    public function __construct(
        protected string $slug,
        protected array $data = [],
        protected ?string $layout = null
    ) {
    }

    public function __invoke(): void
    {
        Route::render($this->slug, $this->data, $this->layout);
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function layout(string $identifier): self
    {
        $this->layout = $identifier;
        return $this;
    }

    public function getLayout(): ?string
    {
        return $this->layout;
    }
}


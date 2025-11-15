<?php

$baseDir = dirname(__DIR__, 2);

if ($argc < 2) {
    showHelp($baseDir);
    exit(0);
}

$command = strtolower($argv[1] ?? '');

switch ($command) {
    case 'make':
        $makeTarget = strtolower($argv[2] ?? '');
        $makeArgs = array_slice($argv, 3);
        handleMakeCommand($makeTarget, $baseDir, $makeArgs);
        break;
    case 'serve':
        $portArg = $argv[2] ?? '';
        handleServeCommand($baseDir, $portArg);
        break;
    default:
        echo "Unknown command: {$command}\n\n";
        showHelp($baseDir);
        exit(1);
}

function handleMakeCommand(string $target, string $baseDir, array $args): void
{
    if (empty($target)) {
        showMakeUsage();
        exit(1);
    }

    switch ($target) {
        case 'frontend':
            scaffoldFrontend($baseDir);
            break;
        case 'module':
            $moduleName = $args[0] ?? null;
            if (!$moduleName) {
                echo "Usage: palm make module <ModuleName> [route-prefix]\n";
                exit(1);
            }
            $params = [$moduleName];
            if (isset($args[1])) {
                $params[] = $args[1];
            }
            runPhpScript('make-module.php', $params);
            break;
        case 'controller':
            $moduleName = $args[0] ?? null;
            $controllerName = $args[1] ?? null;
            if (!$moduleName || !$controllerName) {
                echo "Usage: palm make controller <ModuleName> <ControllerName>\n";
                exit(1);
            }
            runPhpScript('make-controller.php', [$moduleName, $controllerName]);
            break;
        case 'model':
            $moduleName = $args[0] ?? null;
            $modelName = $args[1] ?? null;
            if (!$moduleName || !$modelName) {
                echo "Usage: palm make model <ModuleName> <ModelName> [table-name]\n";
                exit(1);
            }
            $params = [$moduleName, $modelName];
            if (isset($args[2])) {
                $params[] = $args[2];
            }
            runPhpScript('make-model.php', $params);
            break;
        case 'service':
            $moduleName = $args[0] ?? null;
            $serviceName = $args[1] ?? null;
            if (!$moduleName || !$serviceName) {
                echo "Usage: palm make service <ModuleName> <ServiceName>\n";
                exit(1);
            }
            runPhpScript('make-service.php', [$moduleName, $serviceName]);
            break;
        case 'usetable':
            $table = $args[0] ?? 'all';
            runPhpScript('usetable.php', [$table]);
            break;
        default:
            echo "Unknown make target: {$target}\n";
            showMakeUsage();
            exit(1);
    }
}

function showMakeUsage(): void
{
    echo "Usage: palm make <target> [arguments]\n";
    echo "Targets:\n";
    echo "  frontend                       Scaffold src/ directory structure\n";
    echo "  module <Name> [route]          Generate full module (Module/Controller/Service/Model)\n";
    echo "  controller <Module> <Name>     Generate controller for existing module\n";
    echo "  model <Module> <Name> [table]  Generate model file (optional table override)\n";
    echo "  service <Module> <Name>        Generate service file\n";
    echo "  usetable all                   Generate modules from DB tables\n";
}

function scaffoldFrontend(string $baseDir): void
{
    $frontendDir = $baseDir . '/src';
    $layoutDir = $frontendDir . '/layouts';
    $viewsDir = $frontendDir . '/views/home';
    $publicDir = $baseDir . '/public';
    $publicAssetsDir = $publicDir . '/palm-assets';

    recursiveCreate($frontendDir);
    recursiveCreate($layoutDir);
    recursiveCreate($viewsDir);
    recursiveCreate($publicDir);
    recursiveCreate($publicAssetsDir);

    $files = [
        $frontendDir . '/main.php' => getMainTemplate(),
        $layoutDir . '/main.php' => getLayoutTemplate(),
        $viewsDir . '/home.php' => getHomeTemplate(),
        $viewsDir . '/about.php' => getAboutTemplate(),
        $viewsDir . '/contact.php' => getContactTemplate(),
        $publicAssetsDir . '/palm-spa.js' => getSpaScriptTemplate(),
    ];

    foreach ($files as $path => $content) {
        if (!file_exists($path)) {
            file_put_contents($path, $content);
            echo "Created: {$path}\n";
        } else {
            echo "Skipped (already exists): {$path}\n";
        }
    }

    echo "\nFrontend scaffold ready inside ./src (non-/api requests still routed via index.php).\n";
}

function recursiveCreate(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
        echo "Created directory: {$path}\n";
    }
}

function getMainTemplate(): string
{
    return <<<'MAIN'
<?php

use Frontend\Route;

require_once dirname(__DIR__) . '/app/Palm/helpers.php';
require_once dirname(__DIR__) . '/app/Palm/Route.php';

Route::init(__DIR__);

Route::get('/', Route::view('home.home', [
    'title' => 'PHP Palm Frontend',
    'message' => 'Welcome to your PHP Palm powered frontend!',
]));

Route::get('/about', Route::view('home.about', [
    'title' => 'About PHP Palm',
    'meta' => ['description' => 'Learn how PHP Palm powers fast, clean PHP frontends'],
]));

Route::get('/contact', Route::view('home.contact', [
    'title' => 'Contact',
]));

Route::post('/contact', function () {
    $name = trim($_POST['name'] ?? '');
    $message = trim($_POST['message'] ?? '');

    Route::render('home.contact', [
        'title' => 'Contact',
        'flash' => 'Thanks for reaching out! We will reply soon.',
        'prefill' => ['name' => $name, 'message' => $message],
    ]);
});

Route::dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
MAIN;
}

function getLayoutTemplate(): string
{
    return <<<'LAYOUT'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'PHP Palm') ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta['description'] ?? 'PHP Palm Frontend') ?>">
    <style>
        :root {
            color-scheme: light dark;
        }
        body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin:0; padding:0; background:#f7f8fb; color:#1f2933; }
        header { background:linear-gradient(120deg, #0d6efd, #00b4d8); color:#fff; padding:1.5rem 2rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
        header h1 { margin:0; font-size:1.5rem; font-weight:600; letter-spacing:0.5px; }
        nav { display:flex; gap:1rem; align-items:center; }
        nav a { color:#fff; text-decoration:none; font-weight:500; padding:0.35rem 0.75rem; border-radius:999px; transition:background 0.2s ease, transform 0.2s ease; }
        nav a.is-active { background:rgba(255,255,255,0.25); }
        nav a:hover { background:rgba(255,255,255,0.18); transform:translateY(-1px); }
        main { padding:2rem clamp(1.25rem, 4vw, 3rem); min-height:60vh; }
        footer { padding:1rem 2rem; color:#6b7a89; font-size:0.9rem; text-align:center; border-top:1px solid rgba(15,23,42,0.08); }
        .card { background:#fff; border-radius:16px; padding:2rem; box-shadow:0 12px 40px rgba(15,23,42,0.08); border:1px solid rgba(15,23,42,0.06); transition:transform 0.2s ease, box-shadow 0.2s ease; }
        .card:hover { transform:translateY(-2px); box-shadow:0 20px 45px rgba(15,23,42,0.12); }
        .pill { display:inline-flex; align-items:center; gap:0.35rem; padding:0.35rem 0.9rem; border-radius:999px; font-size:0.85rem; background:rgba(13,110,253,0.08); color:#0d6efd; font-weight:600; }
        .state-demo { margin-top:2rem; padding:1.5rem; border-radius:16px; background:linear-gradient(135deg, rgba(13,110,253,0.08), rgba(0,180,216,0.08)); border:1px solid rgba(13,110,253,0.25); }
        .state-counter { display:inline-flex; align-items:center; gap:1rem; margin-bottom:1rem; font-size:1.5rem; }
        .state-counter button { width:44px; height:44px; border-radius:12px; border:none; background:#0d6efd; color:#fff; font-size:1.25rem; font-weight:600; cursor:pointer; transition:transform 0.15s ease, box-shadow 0.15s ease; }
        .state-counter button:hover { transform:translateY(-1px); box-shadow:0 10px 25px rgba(13,110,253,0.25); }
        .state-actions { display:flex; flex-wrap:wrap; gap:0.75rem; }
        .favorite-btn,
        .loading-btn { border:none; border-radius:999px; padding:0.6rem 1.2rem; font-size:0.95rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:0.5rem; transition:background 0.2s ease, transform 0.2s ease; }
        .favorite-btn { background:rgba(255,99,132,0.2); color:#be123c; }
        .favorite-btn.is-active { background:#be123c; color:#fff; }
        .loading-btn { background:rgba(14,165,233,0.2); color:#0369a1; }
        .loading-btn.is-busy { background:#0369a1; color:#fff; }
        .favorite-btn .when-active,
        .loading-btn .when-active { display:none; }
        .favorite-btn.is-active .when-active,
        .loading-btn.is-busy .when-active { display:inline; }
        .favorite-btn.is-active .when-idle,
        .loading-btn.is-busy .when-idle { display:none; }
        .loading-btn[disabled] { opacity:0.7; cursor:not-allowed; }
    </style>
</head>
<body>
    <header>
        <h1>PHP Palm Frontend</h1>
        <nav>
            <a href="/" palm-spa-link class="<?= ($currentPath ?? '/') === '/' ? 'is-active' : '' ?>">Home</a>
            <a href="/about" palm-spa-link class="<?= ($currentPath ?? '') === '/about' ? 'is-active' : '' ?>">About</a>
            <a href="/contact" palm-spa-link class="<?= ($currentPath ?? '') === '/contact' ? 'is-active' : '' ?>">Contact</a>
        </nav>
    </header>
    <main>
        <div id="spa-root" data-spa-current="<?= htmlspecialchars($currentSlug ?? '') ?>">
            <?= $content ?? '' ?>
        </div>
    </main>
    <footer>
        Built with ❤️ using PHP Palm · Zero-JS build frontend
    </footer>
    <?php
        $currentViewKey = isset($currentSlug) ? $currentSlug : null;
        $bootComponent = ($currentViewKey && isset($clientViews[$currentViewKey]['component']))
            ? $clientViews[$currentViewKey]['component']
            : null;
        $bootComponents = $bootComponent ? [$bootComponent] : [];
    ?>
    <script>
        window.__PALM_VIEWS__ = <?= json_encode($clientViews ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.__PALM_ROUTE_MAP__ = <?= json_encode($routeMap ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.__PALM_COMPONENTS__ = <?= json_encode($bootComponents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="/palm-assets/palm-spa.js" defer></script>
</body>
</html>
LAYOUT;
}

function getHomeTemplate(): string
{
    return <<<'HOME'
<?php

/** @phpstan-ignore-file */

require_once dirname(__DIR__, 3) . '/app/Palm/helpers.php';

use function Frontend\Palm\Action;
use function Frontend\Palm\PalmState;
use function Frontend\Palm\State;

$count = State(0);
$favorite = State(false);
$loading = State(false);
$globalClicks = PalmState('demo.globalClicks', 0);

Action('increment', function () use ($count, $globalClicks) {
    $count++;
    ++$globalClicks;
});

Action('decrement', function () use ($count) {
    $count--;
});

Action('toggleFavorite', function () use ($favorite) {
    $favorite = !$favorite;
});

Action('simulateLoading', function () use ($loading) {
    $loading = !$loading;
});

$pageTitle = 'Home';
?>

<div class="card">
    <span class="pill"><?= $pageTitle ?></span>
    <h2><?= htmlspecialchars($title ?? 'Hello!') ?></h2>
    <p><?= htmlspecialchars($message ?? 'Start building your frontend experience here.') ?></p>
    <p>
        Each navigation link maps to a clean PHP route (no query strings, no AJAX), so
        you keep SEO-friendly URLs while still enjoying a streamlined developer
        workflow. Update this card to suit your project’s welcome message.
    </p>
    <p>Customize this section at <code>src/views/home/home.php</code>.</p>

    <div class="state-demo">
        <div class="state-demo__heading">
            <h3>Stateful UI (pure PHP)</h3>
            <p>Use <code>State()</code> for local state and <code>PalmState()</code> for global, persistent values.</p>
        </div>

        <div class="state-counter">
            <button type="button" onclick="decrement()">−</button>
            <strong>Count <?= $count ?></strong>
            <button type="button" onclick="increment()">+</button>
        </div>

        <p>Total clicks (global): <?= $globalClicks ?></p>

        <div class="state-actions">
            <button type="button"
                class="favorite-btn <?= $favorite->get() ? 'is-active' : '' ?>"
                onclick="toggleFavorite()">
                <span class="when-idle">Add to favourites</span>
                <span class="when-active">Saved ✓</span>
            </button>

            <button type="button"
                class="loading-btn <?= $loading->get() ? 'is-busy' : '' ?>"
                onclick="simulateLoading()"
                <?= $loading->get() ? 'disabled' : '' ?>>
                <span class=""><?= $loading->get() ? 'Loading…' : 'Simulate loading' ?></span>
            </button>
        </div>
    </div>
</div>
HOME;
}

function getAboutTemplate(): string
{
    return <<<'ABOUT'
<?php

/** @phpstan-ignore-file */

require_once dirname(__DIR__, 3) . '/app/Palm/helpers.php';

use function Frontend\Palm\Action;
use function Frontend\Palm\State;

$name = State('John Doe');

Action('updateName', function ($newName) use ($name) {
    $name = $newName;
});
?>

<div class="card">
    <span class="pill">About</span>
    <h2>Built for PHP Developers</h2>
    <p>
        PHP Palm lets you craft SPA-like experiences using familiar PHP views.
        Your pages remain fully server-rendered for the first load, then upgrade
        to lightning-fast DOM swaps powered by the Palm runtime—no JavaScript
        build step required.
    </p>
    <ul>
        <li>SEO-first rendering with automatic hydration.</li>
        <li>Component-level state syncing handled automatically.</li>
        <li>Link to clean PHP routes like <code>/about</code> and Palm resolves the rest.</li>
    </ul>

    <div class="state-demo" style="margin-top:1.5rem;">
        <p><strong>Name:</strong> <?= $name ?></p>
        <div style="display:flex;gap:0.5rem;">
            <button type="button" onclick="updateName('Palm Dev')">Use preset</button>
            <button type="button" onclick="updateName(prompt('New name', 'Palm Dev') ?? 'Palm Dev')">
                Custom name
            </button>
        </div>
    </div>
</div>
ABOUT;
}

function getContactTemplate(): string
{
    return <<<'CONTACT'
<?php
$prefill = $prefill ?? ['name' => '', 'message' => ''];
?>
<div class="card">
    <span class="pill">Contact</span>
    <h2>Stay in Touch</h2>
    <p>Questions, feature ideas, or bug reports? Drop us a note.</p>
    <?php if (!empty($flash)): ?>
        <div style="background:#dcfce7;border:1px solid #16a34a;color:#166534;padding:0.75rem 1rem;border-radius:12px;margin-bottom:1rem;">
            <?= htmlspecialchars($flash) ?>
        </div>
    <?php endif; ?>
    <form action="/contact" method="post">
        <label style="display:block;margin-bottom:0.5rem;">
            Name
            <input type="text" name="name" value="<?= htmlspecialchars($prefill['name'] ?? '') ?>" required style="width:100%;padding:0.5rem;border-radius:8px;border:1px solid #d0d7e2;margin-top:0.25rem;">
        </label>
        <label style="display:block;margin-bottom:0.5rem;">
            Message
            <textarea name="message" rows="4" required style="width:100%;padding:0.5rem;border-radius:8px;border:1px solid #d0d7e2;margin-top:0.25rem;"><?= htmlspecialchars($prefill['message'] ?? '') ?></textarea>
        </label>
        <button type="submit" style="background:#0d6efd;color:#fff;border:none;padding:0.75rem 1.5rem;border-radius:12px;font-size:1rem;font-weight:600;">
            Send
        </button>
    </form>
</div>
CONTACT;
}

function getRouteClassTemplate(): string
{
    return <<<'CLASS'
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

    public static function view(string $slug, array $data = []): callable
    {
        return new ViewHandler($slug, $data);
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

    public static function render(string $slug, array $data = []): void
    {
        self::$pathToSlug[self::$currentPath] = $slug;
        self::$viewRegistry[$slug] = $data + (self::$viewRegistry[$slug] ?? []);

        $base = self::$basePath;
        $viewPath = $base . '/views/' . str_replace('.', '/', $slug) . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(404);
            echo "<h1>View not found</h1><p>" . htmlspecialchars($slug) . "</p>";
            return;
        }

        $title = $data['title'] ?? self::humanizeSlug($slug);
        $meta = $data['meta'] ?? [];
        $currentPath = self::$currentPath;
        $currentSlug = $slug;
        $clientViews = self::exportClientViews($slug, $data);
        $routeMap = self::$pathToSlug;

        $result = ComponentManager::render(function () use ($viewPath, $data) {
            extract($data);
            require $viewPath;
        });

        $content = $result['html'];
        $currentComponent = $result['component'];
        if ($currentComponent) {
            $clientViews[$slug]['component'] = $currentComponent;
        }

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

        $layout = $base . '/layouts/main.php';
        require $layout;
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

    protected static function exportClientViews(string $currentSlug, array $currentData): array
    {
        $views = [];
        foreach (self::$viewRegistry as $slug => $data) {
            $payloadData = $slug === $currentSlug ? $currentData : $data;
            $views[$slug] = self::renderFragmentPayload($slug, $payloadData);
        }
        return $views;
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
}

class ViewHandler
{
    public function __construct(
        protected string $slug,
        protected array $data = []
    ) {
    }

    public function __invoke(): void
    {
        Route::render($this->slug, $this->data);
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
CLASS;
}

function getSpaScriptTemplate(): string
{
    return <<<'SCRIPT'
(() => {
    const root = document.getElementById('spa-root');
    const views = window.__PALM_VIEWS__ || {};
    const routeMap = window.__PALM_ROUTE_MAP__ || {};
    const initialComponents = window.__PALM_COMPONENTS__ || [];
    const components = {};

    if (!root || Object.keys(views).length === 0) {
        return;
    }

    const parseArgs = (raw) => {
        if (!raw) {
            return [];
        }

        try {
            const fn = new Function(`"use strict"; return [${raw}];`);
            return fn();
        } catch (error) {
            console.warn('[Palm SPA] Failed to parse action arguments:', raw, error);
            return [];
        }
    };

    const decodePath = (href) => {
        const url = new URL(href, window.location.origin);
        let path = url.pathname;
        if (path.length > 1) {
            path = path.replace(/\/+$/, '');
            if (path === '') path = '/';
        }
        return path;
    };

    const updateMeta = (meta = {}) => {
        if (meta.description) {
            let tag = document.querySelector('meta[name="description"]');
            if (!tag) {
                tag = document.createElement('meta');
                tag.setAttribute('name', 'description');
                document.head.appendChild(tag);
            }
            tag.setAttribute('content', meta.description);
        }
    };

    const updateActiveLinks = (path) => {
        document.querySelectorAll('[palm-spa-link]').forEach((link) => {
            const linkPath = decodePath(link.href);
            link.classList.toggle('is-active', linkPath === path);
        });
    };

    const registerComponent = (meta, container) => {
        if (!meta || !container) {
            return;
        }

        const state = {};
        (meta.states || []).forEach(({ id, value }) => {
            state[id] = value;
        });

        const bindings = {};
        container.querySelectorAll('[data-palm-bind]').forEach((node) => {
            const [componentId, slotId] = (node.dataset.palmBind || '').split('::');
            if (componentId !== meta.id) {
                return;
            }
            if (!bindings[slotId]) {
                bindings[slotId] = [];
            }
            bindings[slotId].push(node);
        });

        container.querySelectorAll('[data-palm-action]').forEach((node) => {
            node.dataset.palmComponent = meta.id;
        });

        components[meta.id] = {
            id: meta.id,
            state,
            bindings,
            actions: meta.actions || {},
        };
    };

    const hydrateInitialComponents = () => {
        initialComponents.forEach((meta) => {
            const container = document.querySelector(`[data-palm-component="${meta.id}"]`);
            registerComponent(meta, container);
        });
    };

    const updateBindings = (componentId, slotId) => {
        const component = components[componentId];
        if (!component) {
            return;
        }
        const nodes = component.bindings[slotId] || [];
        nodes.forEach((node) => {
            node.textContent = component.state[slotId] ?? '';
        });
    };

    const runAction = (componentId, actionName, args = []) => {
        const component = components[componentId];
        if (!component) {
            return;
        }

        const ops = component.actions[actionName] || [];
        if (!ops.length) {
            return;
        }

        const resolveValue = (value) => {
            if (value && typeof value === 'object' && value.type === 'arg') {
                return args[value.index];
            }
            return value;
        };

        ops.forEach((op) => {
            const slotId = op.slot;
            switch (op.type) {
                case 'increment':
                    component.state[slotId] = (Number(component.state[slotId]) || 0) + Number(op.value || 1);
                    break;
                case 'decrement':
                    component.state[slotId] = (Number(component.state[slotId]) || 0) - Number(op.value || 1);
                    break;
                case 'toggle':
                    component.state[slotId] = !component.state[slotId];
                    break;
                case 'set':
                    component.state[slotId] = resolveValue(op.value);
                    break;
                default:
                    break;
            }
            updateBindings(componentId, slotId);
        });
    };

    const renderSlug = (slug, path, pushState = false) => {
        const payload = views[slug];
        if (!payload) {
            window.location.href = path;
            return;
        }

        Object.keys(components).forEach((id) => {
            delete components[id];
        });

        root.innerHTML = payload.html;
        root.dataset.spaCurrent = slug;

        if (payload.title) {
            document.title = payload.title;
        }
        updateMeta(payload.meta);
        updateActiveLinks(path);

        if (payload.component) {
            const container = root.querySelector(`[data-palm-component="${payload.component.id}"]`);
            registerComponent(payload.component, container);
        }

        if (pushState) {
            history.pushState({ slug }, payload.title || document.title, path);
        } else {
            history.replaceState({ slug }, payload.title || document.title, path);
        }
    };

    document.addEventListener('click', (event) => {
        const anchor = event.target.closest('a');
        if (anchor && anchor.hasAttribute('palm-spa-link')) {
            if (
                anchor.target === '_blank' ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey
            ) {
                return;
            }

            const path = decodePath(anchor.href);
            const slug = routeMap[path];
            if (!slug) {
                return;
            }

            event.preventDefault();
            renderSlug(slug, path, true);
            return;
        }

        const trigger = event.target.closest('[data-palm-action]');
        if (!trigger) {
            return;
        }

        const actionName = trigger.dataset.palmAction;
        const componentId = trigger.dataset.palmComponent;
        if (!actionName || !componentId) {
            return;
        }

        event.preventDefault();
        const args = parseArgs(trigger.getAttribute('data-palm-args'));
        runAction(componentId, actionName, args);
    });

    window.addEventListener('popstate', (event) => {
        const slug = event.state?.slug || routeMap[window.location.pathname];
        if (!slug) {
            window.location.reload();
            return;
        }
        renderSlug(slug, window.location.pathname, false);
    });

    const submitSpaForm = async (form) => {
        const method = (form.getAttribute('method') || 'POST').toUpperCase();
        if (method !== 'POST') {
            form.submit();
            return;
        }

        const action = form.getAttribute('action') || window.location.pathname;
        const path = decodePath(action);
        const slug = routeMap[path];

        if (!slug) {
            form.submit();
            return;
        }

        try {
            const response = await fetch(action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Palm-Request': '1',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Form submission failed');
            }

            const data = await response.json();
            if (!data || !data.slug || !data.payload) {
                throw new Error('Invalid response');
            }

            views[data.slug] = data.payload;
            if (data.routeMap) {
                Object.assign(routeMap, data.routeMap);
            }
            renderSlug(data.slug, path, false);
        } catch (error) {
            console.error('[Palm SPA form]', error);
            form.submit();
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form');
        if (!form) {
            return;
        }

        if (form.dataset.spaForm === 'false') {
            return;
        }

        const method = (form.getAttribute('method') || 'POST').toUpperCase();
        if (method !== 'POST') {
            return;
        }

        event.preventDefault();
        submitSpaForm(form);
    });

    const initialSlug = root.dataset.spaCurrent;
    updateActiveLinks(window.location.pathname);
    history.replaceState({ slug: initialSlug }, document.title, window.location.pathname);
    hydrateInitialComponents();
})();
SCRIPT;
}

function getPalmHelpersTemplate(): string
{
    return <<<'PHP'
<?php

use Frontend\Palm\ComponentManager;

require_once __DIR__ . '/StateSlot.php';
require_once __DIR__ . '/ComponentContext.php';
require_once __DIR__ . '/ComponentManager.php';

if (!function_exists('State')) {
    function State(mixed $initial = null)
    {
        $context = ComponentManager::current();
        if (!$context) {
            return $initial;
        }

        return $context->createState($initial);
    }
}

if (!function_exists('Action')) {
    function Action(string $name, callable $callback): void
    {
        $context = ComponentManager::current();
        if (!$context) {
            return;
        }

        $context->registerAction($name, $callback);
    }
}
PHP;
}

function getPalmComponentManagerTemplate(): string
{
    return <<<'PHP'
<?php

namespace Frontend\Palm;

class ComponentManager
{
    protected static ?ComponentContext $current = null;
    protected static int $counter = 0;

    public static function hasContext(): bool
    {
        return self::$current !== null;
    }

    public static function current(): ?ComponentContext
    {
        return self::$current;
    }

    /**
     * @param callable $renderer
     * @return array{html:string, component:?array}
     */
    public static function render(callable $renderer): array
    {
        $context = new ComponentContext('cmp_' . (++self::$counter));
        self::$current = $context;

        ob_start();
        $renderer();
        $html = ob_get_clean() ?: '';

        $componentPayload = $context->buildPayload();
        if ($componentPayload !== null) {
            $html = $context->finalizeHtml($html);
        }

        self::$current = null;

        return [
            'html' => $html,
            'component' => $componentPayload,
        ];
    }
}
PHP;
}

function getPalmComponentContextTemplate(): string
{
    return <<<'PHP'
<?php

namespace Frontend\Palm;

class ComponentContext
{
    protected string $id;
    /** @var StateSlot[] */
    protected array $states = [];
    protected array $actions = [];
    protected ?string $recordingAction = null;
    protected array $currentOperations = [];

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function createState(mixed $initial = null): StateSlot
    {
        $slotId = 's' . count($this->states);
        $slot = new StateSlot($this, $slotId, $initial);
        $this->states[$slotId] = $slot;
        return $slot;
    }

    public function hasInteractiveState(): bool
    {
        return !empty($this->states);
    }

    public function isRecording(): bool
    {
        return $this->recordingAction !== null;
    }

    public function recordOperation(array $operation): void
    {
        if ($this->recordingAction === null) {
            return;
        }

        $this->currentOperations[] = $operation;
    }

    public function registerAction(string $name, callable $callback): void
    {
        if (isset($this->actions[$name])) {
            return;
        }

        if (\is_array($callback)) {
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
            $argCount = $reflection->getNumberOfParameters();
            $args = [];
            for ($i = 0; $i < $argCount; $i++) {
                $args[] = new ActionArgument($i);
            }
            $this->recordingAction = $name;
            $this->currentOperations = [];
            $reflection->invokeArgs($callback[0], $args);
            $this->actions[$name] = $this->currentOperations;
            $this->recordingAction = null;
            $this->currentOperations = [];
            return;
        }

        $reflection = new \ReflectionFunction($callback);
        $argCount = $reflection->getNumberOfParameters();
        $args = [];
        for ($i = 0; $i < $argCount; $i++) {
            $args[] = new ActionArgument($i);
        }

        $this->recordingAction = $name;
        $this->currentOperations = [];
        $reflection->invokeArgs($args);
        $this->actions[$name] = $this->currentOperations;
        $this->recordingAction = null;
        $this->currentOperations = [];
    }

    public function finalizeHtml(string $html): string
    {
        if (!$this->hasInteractiveState()) {
            return $html;
        }

        $html = preg_replace_callback('/onclick="([^"]+)"/i', function ($matches) {
            $expression = trim($matches[1]);
            $action = $expression;
            $args = '';

            if (preg_match('/^([a-zA-Z0-9_]+)\s*\((.*)\)\s*$/', $expression, $parts)) {
                $action = $parts[1];
                $args = trim($parts[2]);
            } elseif (substr($expression, -2) === '()') {
                $action = substr($expression, 0, -2);
            }

            if (!isset($this->actions[$action])) {
                return $matches[0];
            }

            $safe = htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $attributes = 'data-palm-action="' . $safe . '" data-palm-component="' . $this->id . '"';
            if ($args !== '') {
                $attributes .= ' data-palm-args="' . htmlspecialchars($args, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
            return $attributes;
        }, $html);

        return '<div data-palm-component="' . $this->id . '">' . $html . '</div>';
    }

    public function buildPayload(): ?array
    {
        if (!$this->hasInteractiveState()) {
            return null;
        }

        $statePayload = [];
        foreach ($this->states as $slot) {
            $statePayload[] = [
                'id' => $slot->getSlotId(),
                'value' => $slot->getValue(),
            ];
        }

        return [
            'id' => $this->id,
            'states' => $statePayload,
            'actions' => $this->actions,
        ];
    }
}
PHP;
}

function getPalmStateSlotTemplate(): string
{
    return <<<'PHP'
<?php

namespace Frontend\Palm;

class StateSlot
{
    protected ComponentContext $context;
    protected string $slotId;
    protected mixed $value;

    public function __construct(ComponentContext $context, string $slotId, mixed $initial = null)
    {
        $this->context = $context;
        $this->slotId = $slotId;
        $this->value = $initial;
    }

    public function getSlotId(): string
    {
        return $this->slotId;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function __invoke(mixed $value = null): mixed
    {
        if (func_num_args() === 0) {
            return $this->get();
        }

        $this->set($value);
        return $this->value;
    }

    protected function normalizeRecordedValue(mixed $value): mixed
    {
        if ($value instanceof self) {
            $value = $value->get();
        }

        if ($value instanceof ActionArgument) {
            return [
                'type' => 'arg',
                'index' => $value->getIndex(),
            ];
        }

        return $value;
    }

    public function set(mixed $value): void
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'set',
                'slot' => $this->slotId,
                'value' => $this->normalizeRecordedValue($value),
            ]);
            return;
        }

        if ($value instanceof self) {
            $value = $value->get();
        }

        $this->value = $value;
    }

    public function increment(int|float $step = 1): void
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'increment',
                'slot' => $this->slotId,
                'value' => $step,
            ]);
            return;
        }

        $this->value = ($this->value ?? 0) + $step;
    }

    public function decrement(int|float $step = 1): void
    {
        $this->increment($step * -1);
    }

    public function toggle(): void
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'toggle',
                'slot' => $this->slotId,
            ]);
            return;
        }

        $this->value = !$this->value;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function __toString(): string
    {
        $escaped = htmlspecialchars((string)($this->value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<span data-palm-bind="' . $this->context->getId() . '::' . $this->slotId . '">' . $escaped . '</span>';
    }
}
PHP;
}

function getPalmActionArgumentTemplate(): string
{
    return <<<'PHP'
<?php

namespace Frontend\Palm;

class ActionArgument
{
    protected int $index;

    public function __construct(int $index)
    {
        $this->index = $index;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function __toString(): string
    {
        return '{{arg:' . $this->index . '}}';
    }
}
PHP;
}


function showHelp(?string $baseDir = null): void
{
    printPalmBanner("Developer Toolkit");
    $version = getProjectVersion($baseDir);
    $phpVersion = PHP_VERSION;

    echo colorText("PHP Palm Version: ", 'green') . "v{$version}\n";
    echo colorText("PHP Runtime: ", 'green') . "{$phpVersion}\n\n";

    echo colorText("Available Commands:\n", 'yellow');
    echo colorText("  palm make frontend", 'cyan') . "           → Scaffold src/ directory + boilerplate\n";
    echo colorText("  palm make module <Name> [route]", 'cyan') . " → Generate full module\n";
    echo colorText("  palm make controller <Module> <Name>", 'cyan') . " → Generate controller\n";
    echo colorText("  palm make model <Module> <Name> [table]", 'cyan') . " → Generate model\n";
    echo colorText("  palm make service <Module> <Name>", 'cyan') . " → Generate service\n";
    echo colorText("  palm make usetable all", 'cyan') . "        → Generate modules from DB tables\n";
    echo colorText("  palm serve [port:3000]", 'cyan') . "        → Start dev server (auto port unless specified)\n";
    echo colorText("  palm help", 'cyan') . "                     → Show this help screen\n";
    echo "\n";
}

function handleServeCommand(string $baseDir, string $portArg): void
{
    $requestedPort = parsePortArgument($portArg);

    if ($requestedPort !== null && !isPortAvailable($requestedPort)) {
        echo "Port {$requestedPort} is already in use. Choose another port or omit to auto-select.\n";
        exit(1);
    }

    if ($requestedPort === null) {
        $requestedPort = findAvailablePort(8000, 8999);
        if ($requestedPort === null) {
            echo "Unable to find an available port between 8000-8999.\n";
            exit(1);
        }
    }

    $localIp = getLocalIp();
    $hostName = gethostname() ?: 'localhost';
    $phpBinary = PHP_BINARY ?: 'php';
    $command = sprintf('"%s" -S 0.0.0.0:%d -t "%s" index.php', $phpBinary, $requestedPort, $baseDir);

    printPalmBanner("Dev Server");
    echo colorText("Project: ", 'green') . "{$baseDir}\n";
    echo colorText("Port: ", 'green') . "{$requestedPort}\n";
    echo colorText("Local URL: ", 'cyan') . "http://localhost:{$requestedPort}\n";
    if ($localIp) {
        echo colorText("Network URL: ", 'cyan') . "http://{$localIp}:{$requestedPort}\n";
    }
    echo colorText("Host URL: ", 'cyan') . "http://{$hostName}:{$requestedPort}\n";
    echo colorText("Press Ctrl+C to stop the server.\n\n", 'yellow');

    passthru($command);
}

function parsePortArgument(?string $arg): ?int
{
    $arg = trim((string)$arg);
    if ($arg === '') {
        return null;
    }

    if (stripos($arg, 'port:') === 0) {
        $arg = substr($arg, 5);
    }

    $arg = trim($arg);

    if ($arg === '') {
        return null;
    }

    if (!ctype_digit($arg)) {
        echo "Invalid port value: {$arg}\n";
        exit(1);
    }

    $port = (int)$arg;
    if ($port < 1 || $port > 65535) {
        echo "Port must be between 1 and 65535.\n";
        exit(1);
    }

    return $port;
}

function findAvailablePort(int $start, int $end): ?int
{
    for ($port = $start; $port <= $end; $port++) {
        if (isPortAvailable($port)) {
            return $port;
        }
    }
    return null;
}

function isPortAvailable(int $port): bool
{
    $socket = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
    if ($socket === false) {
        return false;
    }
    fclose($socket);
    return true;
}

function getLocalIp(): ?string
{
    if (function_exists('socket_create')) {
        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket !== false) {
            @socket_connect($socket, '8.8.8.8', 53);
            @socket_getsockname($socket, $address);
            socket_close($socket);
            if (!empty($address)) {
                return $address;
            }
        }
    }

    $hostname = gethostname();
    if ($hostname) {
        $ip = gethostbyname($hostname);
        if ($ip !== $hostname) {
            return $ip;
        }
    }

    return null;
}

function runPhpScript(string $scriptName, array $parameters = []): void
{
    $scriptPath = __DIR__ . '/' . $scriptName;
    if (!file_exists($scriptPath)) {
        echo "Generator script not found: {$scriptPath}\n";
        exit(1);
    }

    $parts = [escapeshellarg(PHP_BINARY), escapeshellarg($scriptPath)];
    foreach ($parameters as $param) {
        if ($param === null) {
            continue;
        }
        $parts[] = escapeshellarg($param);
    }

    $command = implode(' ', $parts);
    passthru($command, $status);
    if ($status !== 0) {
        exit($status);
    }
}

function supportsAnsi(): bool
{
    static $supports = null;
    if ($supports !== null) {
        return $supports;
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        $supports = false !== getenv('ANSICON')
            || getenv('ConEmuANSI') === 'ON'
            || getenv('TERM') === 'xterm'
            || function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT);
    } else {
        $supports = true;
    }

    return $supports;
}

function colorText(string $text, string $color = 'default'): string
{
    if (!supportsAnsi()) {
        return $text;
    }

    $map = [
        'default' => '0',
        'red' => '31',
        'green' => '32',
        'yellow' => '33',
        'blue' => '34',
        'magenta' => '35',
        'cyan' => '36',
        'white' => '97',
    ];

    $code = $map[$color] ?? $map['default'];
    return "\033[{$code}m{$text}\033[0m";
}

function printPalmBanner(string $subtitle = ''): void
{
    $lines = [
        "==============================================================",
        "██████╗ ██╗  ██╗██████╗    ██████╗  █████╗ ██╗     ███╗   ███╗",
        "██╔══██╗██║  ██║██╔══██╗   ██╔══██╗██╔══██╗██║     ████╗ ████║",
        "██████╔╝███████║██████╔╝   ██████╔╝███████║██║     ██╔████╔██║",
        "██╔═══╝ ██╔══██║██╔═══╝    ██╔═══╝ ██╔══██║██║     ██║╚██╔╝██║",
        "██║     ██║  ██║██║        ██║     ██║  ██║███████╗██║ ╚═╝ ██║",
        "╚═╝     ╚═╝  ╚═╝╚═╝        ╚═╝     ╚═╝  ╚═╝╚══════╝╚═╝     ╚═╝",
        "==============================================================",
    ];

    $colors = ['cyan', 'cyan', 'magenta', 'magenta', 'yellow', 'yellow'];

    echo "\n";
    foreach ($lines as $index => $line) {
        $color = $colors[$index] ?? 'cyan';
        echo colorText($line . "\n", $color);
    }

    if ($subtitle !== '') {
        $subtitleText = "» {$subtitle}";
        $padding = str_repeat(' ', max(0, 66 - strlen($subtitleText)));
        echo colorText($subtitleText . $padding . "\n\n", 'green');
    } else {
        echo "\n";
    }
}

function getProjectVersion(?string $baseDir): string
{
    if ($baseDir === null) {
        $baseDir = dirname(__DIR__, 2);
    }

    $composerPath = $baseDir . '/composer.json';
    if (file_exists($composerPath)) {
        $json = json_decode(file_get_contents($composerPath), true);
        if (isset($json['version'])) {
            return (string)$json['version'];
        }
    }

    return '0.0.0';
}


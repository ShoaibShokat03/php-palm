<?php

$baseDir = dirname(__DIR__, 2);

// Load Composer autoloader if available
if (file_exists($baseDir . '/vendor/autoload.php')) {
    require $baseDir . '/vendor/autoload.php';
}

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
        $serveArgs = array_slice($argv, 2);
        handleServeCommand($baseDir, $serveArgs);
        break;
    case 'cache':
        $cacheAction = strtolower($argv[2] ?? '');
        handleCacheCommand($cacheAction, $baseDir);
        break;
    case 'route:list':
    case 'route:clear':
        handleRouteCommand($command, $baseDir);
        break;
    case 'view:clear':
        handleViewCacheCommand($baseDir);
        break;
    case 'sitemap:generate':
        handleSitemapCommand($baseDir);
        break;
    case 'optimize':
        handleOptimizeCommand($baseDir, array_slice($argv, 2));
        break;
    case 'i18n:extract':
    case 'i18n:generate':
    case 'i18n:check':
        handleI18nCommand($command, $baseDir, array_slice($argv, 2));
        break;
    case 'security:headers':
        handleSecurityHeadersCommand($baseDir, array_slice($argv, 2));
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
        case 'fronted': // Alias for typo
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
        case 'middleware':
            $middlewareName = $args[0] ?? null;
            if (!$middlewareName) {
                echo "Usage: palm make middleware <MiddlewareName> [--frontend|-f]\n";
                echo "Example: palm make middleware AuthMiddleware\n";
                echo "Example: palm make middleware AuthMiddleware --frontend\n";
                exit(1);
            }
            // Check if frontend middleware flag
            $frontend = ($args[1] ?? '') === '--frontend' || ($args[1] ?? '') === '-f';
            if ($frontend) {
                handleMakeFrontendMiddleware($baseDir, $middlewareName);
            } else {
                // Backend middleware (default)
                runPhpScript('make-middleware.php', [$middlewareName]);
            }
            break;
        case 'view':
            handleMakeView($baseDir, $args);
            break;
        case 'component':
            handleMakeComponent($baseDir, $args);
            break;
        case 'pwa':
            handleMakePwa($baseDir, $args);
            break;
        case 'security:policy':
            handleMakeSecurityPolicy($baseDir, $args);
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
    echo "  middleware <Name>              Generate middleware (backend in middlewares/, frontend in app/Palm/Middleware/)\n";
    echo "  view <view-name>               Generate view file (e.g., home.about)\n";
    echo "  component <ComponentName>      Generate component class (e.g., Button or Form.Input)\n";
    echo "  pwa [name] [short-name]       Generate PWA files (manifest.json, service worker)\n";
    echo "  security:policy [preset]      Generate security policy config (default|strict|development)\n";
    echo "  usetable all                   Generate modules from DB tables\n";
}

function scaffoldFrontend(string $baseDir): void
{
    $frontendDir = $baseDir . '/src';
    $routesDir = $frontendDir . '/routes';
    $layoutDir = $frontendDir . '/layouts';
    $viewsDir = $frontendDir . '/views/home';
    $assetsDir = $frontendDir . '/assets';

    recursiveCreate($frontendDir);
    recursiveCreate($routesDir);
    recursiveCreate($layoutDir);
    recursiveCreate($viewsDir);
    recursiveCreate($assetsDir);

    // Create live-reload.js file (WebSocket-based, no HTTP requests)
    $liveReloadJs = getLiveReloadScript();
    $liveReloadJsPath = $assetsDir . '/live-reload.js';

    $files = [
        $routesDir . '/main.php' => getMainTemplate(),
        $layoutDir . '/main.php' => getLayoutTemplate(),
        $viewsDir . '/index.palm.php' => getHomeTemplate(),
        $viewsDir . '/demo.palm.php' => getDemoTemplate(),
        $viewsDir . '/about.palm.php' => getAboutTemplate(),
        $viewsDir . '/contact.palm.php' => getContactTemplate(),
        $viewsDir . '/cache.palm.php' => getCacheTemplate(),
        $assetsDir . '/layout.css' => getLayoutCssTemplate(),
        $liveReloadJsPath => $liveReloadJs,
    ];

    foreach ($files as $path => $content) {
        if (!file_exists($path)) {
            file_put_contents($path, $content);
            echo "Created: {$path}\n";
        } else {
            echo "Skipped (already exists): {$path}\n";
        }
    }

    // Create .gitignore entries for hot reload files (if .gitignore exists)
    $gitignorePath = $baseDir . '/.gitignore';
    if (file_exists($gitignorePath)) {
        $gitignoreContent = file_get_contents($gitignorePath);
        $hotReloadEntries = [
            '.palm-reload-notify',
            '.palm-file-watcher.php',
            '.palm-websocket-server.php',
            '.palm-router.php',
            '.palm-ws-port',
        ];
        
        $entriesToAdd = [];
        foreach ($hotReloadEntries as $entry) {
            // Check if entry exists (as exact line or in a pattern)
            if (strpos($gitignoreContent, $entry) === false) {
                $entriesToAdd[] = $entry;
            }
        }
        
        if (!empty($entriesToAdd)) {
            // Check if we already have a Palm hot reload section
            if (strpos($gitignoreContent, '# Palm hot reload files') === false) {
                $gitignoreContent .= "\n# Palm hot reload files\n";
            }
            foreach ($entriesToAdd as $entry) {
                $gitignoreContent .= "{$entry}\n";
            }
            file_put_contents($gitignorePath, $gitignoreContent);
            echo "Updated: .gitignore (added hot reload files)\n";
        }
    }

    echo "\n✅ Frontend scaffold ready inside ./src (non-/api requests still routed via index.php).\n";
    echo "🧩 Structure:\n";
    echo "   - src/routes/main.php (frontend entry)\n";
    echo "   - src/layouts/main.php (clean HTML/PHP layout)\n";
    echo "   - src/views/home/*.palm.php (reactive component views)\n";
    echo "   - src/assets/ (assets: CSS, JS, live-reload script)\n";
    echo "\n🔥 Hot Reload System:\n";
    echo "   - WebSocket-based real-time communication (no HTTP requests!)\n";
    echo "   - Background file watcher monitors changes\n";
    echo "   - WebSocket server broadcasts changes instantly\n";
    echo "   - Automatic browser reload on file changes\n";
    echo "   - Zero HTTP polling - pure WebSocket connection\n";
    echo "\n🚀 Run 'palm serve' to start the development server!\n";
    echo "💡 Tip: Set HOT_RELOAD_ENABLED=true in config/.env to enable hot reload\n";
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

use Frontend\Palm\Route;
use Frontend\Palm\PalmCache;

Route::get('/cache', function () {
    $cache = new PalmCache();

    Route::render('home.cache', [
        'title' => 'Palm Cache Manager',
        'summary' => $cache->summary(),
        'recentFiles' => $cache->recentFiles(),
        'message' => $_GET['message'] ?? null,
    ]);
});

Route::get('/cache-clear', function () {
    $target = $_GET['target'] ?? 'all';
    $format = strtolower($_GET['format'] ?? '');
    $cache = new PalmCache();
    $result = $cache->clear($target);

    if ($format === 'html') {
        Route::render('home.cache', [
            'title' => 'Palm Cache Manager',
            'summary' => $result['summary'],
            'recentFiles' => $result['recent_files'],
            'message' => $result['message'],
        ]);
        return;
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

Route::post('/cache-clear', function () {
    $target = $_POST['target'] ?? 'all';
    $cache = new PalmCache();
    $result = $cache->clear($target);

    if (isset($_POST['redirect']) && $_POST['redirect'] === 'cache') {
        $query = http_build_query([
            'message' => $result['message'],
            'target' => $result['target'],
        ]);
        header('Location: /cache?' . $query);
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

// Home page
Route::get('/', Route::view('home.index', [
    'title' => 'PHP Palm Frontend',
    'message' => 'Welcome to your PHP Palm powered frontend!',
]), 'home');

// About page
Route::get('/about', Route::view('home.about', [
    'title' => 'About PHP Palm',
    'meta' => ['description' => 'Learn how PHP Palm powers fast, clean PHP frontends'],
]), 'about');

// Contact page
Route::get('/contact', Route::view('home.contact', [
    'title' => 'Contact',
]), 'contact');

// Contact form submission
Route::post('/contact', function () {
    require_once dirname(__DIR__, 2) . '/app/Palm/helpers.php';
    
    $name = trim($_POST['name'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($message)) {
        Route::render('home.contact', [
            'title' => 'Contact',
            'flash' => 'Please fill in all fields.',
            'prefill' => ['name' => $name, 'message' => $message],
        ]);
        return;
    }

    Route::render('home.contact', [
        'title' => 'Contact',
        'flash' => 'Thanks for reaching out! We will reply soon.',
        'prefill' => ['name' => '', 'message' => ''],
    ]);
}, 'contact.submit');

// Demo page
Route::get('/demo', Route::view('home.demo', [
    'title' => 'Demo Page',
    'message' => 'Playground for Palm experiments',
]), 'demo');

// Google Auth routes (automatically initialized in index.php)
// Google login - redirect to Google
Route::get('/auth/google', function () {
    try {
        google_auth_redirect();
    } catch (\Exception $e) {
        Route::render('home.error', [
            'title' => 'Authentication Error',
            'message' => 'Google authentication is not configured. Please set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in your .env file.',
        ]);
    }
}, 'auth.google');

// Google callback - handle OAuth response
Route::get('/auth/google/callback', function () {
    try {
        $user = \Frontend\Palm\GoogleAuth::handleCallback();
        
        // Redirect to dashboard or home
        $redirect = $_GET['redirect'] ?? '/';
        header('Location: ' . $redirect);
        exit;
    } catch (\Exception $e) {
        Route::render('home.error', [
            'title' => 'Authentication Error',
            'message' => 'Failed to authenticate with Google: ' . htmlspecialchars($e->getMessage()),
        ]);
    }
}, 'auth.google.callback');

// Google logout
Route::get('/auth/google/logout', function () {
    google_auth_logout();
    header('Location: /');
    exit;
}, 'auth.google.logout');
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

        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            background: #f7f8fb;
            color: #1f2933;
            overflow: scroll;
        }

        header {
            background: linear-gradient(120deg, #0d6efd, #00b4d8);
            color: #fff;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        nav {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        nav a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        nav a.is-active {
            background: rgba(255, 255, 255, 0.25);
        }

        nav a:hover {
            background: rgba(255, 255, 255, 0.18);
            transform: translateY(-1px);
        }

        main {
            padding: 3rem clamp(1.25rem, 4vw, 3rem);
            min-height: 60vh;
            max-width: 1200px;
            margin: 0 auto;
        }

        footer {
            padding: 1rem 2rem;
            color: #6b7a89;
            font-size: 0.9rem;
            text-align: center;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        /* Content sections - clean, no cards or shadows */
        .content-section {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Links */
        a {
            color: #0d6efd;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        a:hover {
            color: #0b5ed7;
            text-decoration: underline;
        }

        /* Lists */
        ul, ol {
            margin: 1rem 0;
            padding-left: 1.5rem;
        }

        li {
            margin: 0.5rem 0;
            line-height: 1.6;
        }

        /* Code */
        code {
            background: rgba(15, 23, 42, 0.08);
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        h1 {
            margin: 0 0 1rem;
            font-size: 2.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        h2 {
            margin: 1.5rem 0 1rem;
            font-size: 1.75rem;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
        }

        h3 {
            margin: 1.5rem 0 0.75rem;
            font-size: 1.5rem;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
        }

        h4 {
            margin: 1rem 0 0.5rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
        }

        .lead {
            font-size: 1.1rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .features-banner {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 2rem;
            padding: 1rem 0;
        }

        .feature-badge {
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 500;
            background: rgba(13, 110, 253, 0.08);
            color: #0d6efd;
        }

        .state-demo {
            margin-top: 2rem;
        }

        .state-demo__heading {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }

        .state-demo__heading h3 {
            margin: 0 0 0.5rem;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .state-demo__heading p {
            margin: 0;
            color: #64748b;
        }

        .demo-section {
            margin: 2rem 0;
            padding: 1.5rem 0;
            border-top: 1px solid rgba(15, 23, 42, 0.1);
        }
        
        .demo-section:first-of-type {
            border-top: none;
            padding-top: 0;
        }

        .demo-section h4 {
            margin: 0 0 1rem;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .state-counter {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin: 1rem 0;
        }

        .btn-counter {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 2px solid #0d6efd;
            background: #fff;
            color: #0d6efd;
            font-size: 1.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-counter:hover {
            background: #0d6efd;
            color: #fff;
            transform: scale(1.1);
        }

        .counter-value {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            min-width: 60px;
            text-align: center;
        }

        .counter-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .btn-action,
        .btn-action-secondary {
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .btn-action {
            background: #0d6efd;
            color: #fff;
        }

        .btn-action:hover {
            background: #0b5ed7;
        }

        .btn-action-secondary {
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }

        .btn-action-secondary:hover {
            background: rgba(13, 110, 253, 0.15);
        }

        .value-display {
            font-size: 1.1rem;
            margin: 1rem 0;
            padding: 0.75rem;
            background: rgba(247, 248, 251, 0.5);
            border-radius: 6px;
            border: 1px solid rgba(15, 23, 42, 0.1);
        }

        .value-display strong {
            color: #0d6efd;
            font-weight: 600;
        }

        .action-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .state-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .favorite-btn,
        .loading-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: 2px solid rgba(13, 110, 253, 0.2);
            background: #fff;
            color: #0d6efd;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .favorite-btn.is-active {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }

        .loading-btn.is-busy {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .code-example {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #1e293b;
            border-radius: 12px;
            color: #e2e8f0;
        }

        .code-example h3 {
            margin: 0 0 1rem;
            color: #f1f5f9;
        }

        .code-example pre {
            margin: 0;
            overflow-x: auto;
        }

        .code-example code {
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <header>
        <h1><a href="/" style="color:inherit;text-decoration:none;">PHP Palm</a></h1>
        <nav>
            <a href="/" class="<?= ($currentPath ?? '/') === '/' ? 'is-active' : '' ?>">Home</a>
            <a href="/about" class="<?= ($currentPath ?? '') === '/about' ? 'is-active' : '' ?>">About</a>
            <a href="/contact" class="<?= ($currentPath ?? '') === '/contact' ? 'is-active' : '' ?>">Contact</a>
            <a href="/demo" class="<?= ($currentPath ?? '') === '/demo' ? 'is-active' : '' ?>">Demo</a>
        </nav>
    </header>
    <main>
        <?= $content ?? '' ?>
    </main>
    <footer>
        <p>Built with ❤️ using <strong>PHP Palm</strong> · Modern PHP framework</p>
        <p style="font-size:0.85rem;margin-top:0.5rem;color:#94a3b8;">
            <a href="/" style="color:inherit;">Home</a> · 
            <a href="/about" style="color:inherit;">About</a> · 
            <a href="/contact" style="color:inherit;">Contact</a>
        </p>
    </footer>
</body>
</html>
LAYOUT;
}


function getHomeTemplate(): string
{
    return <<<'HOME'
<?php

/** @phpstan-ignore-file */

$pageTitle = 'Home';
?>

<div class="content-section">
    <h1><?= htmlspecialchars($title ?? 'Welcome to PHP Palm!') ?></h1>
    <p class="lead"><?= htmlspecialchars($message ?? 'Build PHP applications with clean, simple code.') ?></p>

    <div class="demo-section">
        <h3>Getting Started</h3>
        <p>This is your PHP Palm frontend. Edit this file at <code>src/views/home/index.palm.php</code> to customize it.</p>
        <p>Your routes are defined in <code>src/routes/main.php</code> and the layout is in <code>src/layouts/main.php</code>.</p>
    </div>

    <div class="demo-section">
        <h3>Quick Links</h3>
        <ul>
            <li><a href="/about">About</a> - Learn more about PHP Palm</li>
            <li><a href="/contact">Contact</a> - Get in touch</li>
            <li><a href="/demo">Demo</a> - See examples</li>
        </ul>
    </div>
</div>
HOME;
}

function getAboutTemplate(): string
{
    return <<<'ABOUT'
<?php

/** @phpstan-ignore-file */

?>

<div class="content-section">
    <h1>About PHP Palm</h1>
    <p class="lead">
        PHP Palm lets you build PHP applications using familiar PHP views.
        Your pages are fully server-rendered with clean routing—no JavaScript build step required.
    </p>
    
    <div class="demo-section">
        <h3>Key Features</h3>
        <ul>
            <li>✅ SEO-first server-side rendering</li>
            <li>✅ Clean PHP routes with full page navigation</li>
            <li>✅ Form validation helpers</li>
            <li>✅ Simple view rendering</li>
            <li>✅ Route groups, prefixes, and resource routes</li>
            <li>✅ Component system for reusable UI</li>
            <li>✅ Built-in security (CSRF, XSS protection, CSP)</li>
            <li>✅ Performance optimizations (caching, compression)</li>
        </ul>
    </div>

    <div class="demo-section">
        <h3>Why PHP Palm?</h3>
        <p>PHP Palm is designed for PHP developers who want a modern, fast, and secure framework without the complexity of JavaScript build tools. Everything is server-rendered, SEO-friendly, and easy to understand.</p>
    </div>
</div>
ABOUT;
}

function getContactTemplate(): string
{
    return <<<'CONTACT'
<?php

/** @phpstan-ignore-file */

require_once dirname(__DIR__, 3) . '/app/Palm/helpers.php';

$prefill = $prefill ?? ['name' => '', 'message' => ''];
?>
<div class="content-section">
    <h1>Contact Us</h1>
    <p class="lead">Questions, feature ideas, or bug reports? Drop us a note.</p>
    
    <?php if (!empty($flash)): ?>
        <div style="background:#dcfce7;border:1px solid #16a34a;color:#166534;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1.5rem;">
            ✅ <?= htmlspecialchars($flash) ?>
        </div>
    <?php endif; ?>
    
    <form action="/contact" method="post" style="max-width:600px;">
        <?= csrf_field() ?>
        
        <div style="margin-bottom:1.5rem;">
            <label for="name" style="display:block;margin-bottom:0.5rem;font-weight:500;">
                Name
            </label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($prefill['name'] ?? '') ?>" 
                   required
                   style="width:100%;padding:0.75rem;border-radius:6px;border:1px solid #d0d7e2;font-size:1rem;font-family:inherit;">
        </div>
        
        <div style="margin-bottom:1.5rem;">
            <label for="message" style="display:block;margin-bottom:0.5rem;font-weight:500;">
                Message
            </label>
            <textarea id="message" name="message" rows="5" required
                      style="width:100%;padding:0.75rem;border-radius:6px;border:1px solid #d0d7e2;font-size:1rem;font-family:inherit;resize:vertical;"><?= htmlspecialchars($prefill['message'] ?? '') ?></textarea>
        </div>
        
        <button type="submit" class="btn-action">
            Send Message
        </button>
    </form>
</div>
CONTACT;
}

function getDemoTemplate(): string
{
    return <<<'DEMO'
<?php

/** @phpstan-ignore-file */

?>
<div class="content-section">
    <h1>Demo Page</h1>
    <p class="lead">This is a demo page. Edit this file at <code>src/views/home/demo.palm.php</code> to customize it.</p>

    <div class="demo-section">
        <h3>Example Content</h3>
        <p>Add your content here. This page demonstrates the clean, card-free design of PHP Palm.</p>
    </div>

    <div class="demo-section">
        <h3>Navigation</h3>
        <p>Use the navigation links in the header to explore other pages:</p>
        <ul>
            <li><a href="/">Home</a></li>
            <li><a href="/about">About</a></li>
            <li><a href="/contact">Contact</a></li>
        </ul>
    </div>
</div>
DEMO;
}

function getCacheTemplate(): string
{
    return <<<'CACHE'
<?php

/** @phpstan-ignore-file */

$title = $title ?? 'Palm Cache Manager';
$summary = $summary ?? [];
$recentFiles = $recentFiles ?? [];
$message = $message ?? null;
?>
<div class="content-section">
    <h1>Palm Cache Manager</h1>
    <p class="lead">Manage cached assets and improve performance.</p>

    <?php if ($message): ?>
        <div style="background:#dcfce7;border:1px solid #16a34a;color:#166534;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1.5rem;">
            ✅ <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($summary)): ?>
        <div class="demo-section">
            <h3>Cache Summary</h3>
            <ul style="list-style:none;padding:0;">
                <?php foreach ($summary as $key => $value): ?>
                    <li style="padding:0.75rem 0;border-bottom:1px solid rgba(15,23,42,0.1);">
                        <strong><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars($value) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($recentFiles)): ?>
        <div class="demo-section">
            <h3>Recent Cached Files</h3>
            <ul style="list-style:none;padding:0;">
                <?php foreach ($recentFiles as $file): ?>
                    <li style="padding:0.5rem 0;border-bottom:1px solid rgba(15,23,42,0.1);font-family:monospace;font-size:0.9rem;">
                        <?= htmlspecialchars($file) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="demo-section">
        <h3>Cache Actions</h3>
        <form action="/cache-clear" method="post" style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="cache">
            <button type="submit" name="target" value="all" class="btn-action">Clear All Cache</button>
            <button type="submit" name="target" value="views" class="btn-action-secondary">Clear Views Only</button>
            <button type="submit" name="target" value="assets" class="btn-action-secondary">Clear Assets Only</button>
        </form>
    </div>
</div>
CACHE;
}

function getLayoutCssTemplate(): string
{
    return <<<'CSS'
/* Palm Layout Styles */
/* This file contains shared styles for the Palm frontend */

:root {
    color-scheme: light dark;
}

body {
    font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    margin: 0;
    padding: 0;
    background: #f7f8fb;
    color: #1f2933;
}

header {
    background: linear-gradient(120deg, #0d6efd, #00b4d8);
    color: #fff;
    padding: 1.5rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

header h1 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: 0.5px;
}

nav {
    display: flex;
    gap: 1rem;
    align-items: center;
}

nav a {
    color: #fff;
    text-decoration: none;
    font-weight: 500;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    transition: background 0.2s ease, transform 0.2s ease;
}

nav a.is-active {
    background: rgba(255, 255, 255, 0.25);
}

nav a:hover {
    background: rgba(255, 255, 255, 0.18);
    transform: translateY(-1px);
}

        main {
            padding: 3rem clamp(1.25rem, 4vw, 3rem);
            min-height: 60vh;
            max-width: 1200px;
            margin: 0 auto;
        }

footer {
    padding: 1rem 2rem;
    color: #6b7a89;
    font-size: 0.9rem;
    text-align: center;
    border-top: 1px solid rgba(15, 23, 42, 0.08);
}
CSS;
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
    echo colorText("  palm make middleware <Name>", 'cyan') . "      → Generate middleware (backend or frontend)\n";
    echo colorText("  palm make security:policy [preset]", 'cyan') . " → Generate security policy config\n";
    echo colorText("  palm make view <view-name>", 'cyan') . "       → Generate view file\n";
    echo colorText("  palm make component <Name>", 'cyan') . "       → Generate component class\n";
    echo colorText("  palm make pwa", 'cyan') . "                    → Generate PWA files\n";
    echo colorText("  palm make usetable all", 'cyan') . "        → Generate modules from DB tables\n";
    echo colorText("  palm serve [port]", 'cyan') . "              → Start dev server\n";
    echo colorText("  palm serve [port] --no-open", 'cyan') . "    → Start server without opening browser\n";
    echo colorText("  palm serve [port] --reload", 'cyan') . "     → Force enable hot reload (overrides .env)\n";
    echo colorText("  palm serve [port] --no-reload", 'cyan') . "  → Force disable hot reload (overrides .env)\n";
    echo colorText("", 'cyan') . "                                  Hot reload: Set HOT_RELOAD_ENABLED=true in config/.env\n";
    echo colorText("", 'cyan') . "                                  (Hot reload uses efficient file-based notifications)\n";
    echo colorText("  palm cache clear", 'cyan') . "               → Clear all Palm cache files\n";
    echo colorText("  palm sitemap:generate", 'cyan') . "          → Generate sitemap.xml and robots.txt\n";
    echo colorText("  palm optimize [css|js|all]", 'cyan') . "     → Minify CSS/JS assets\n";
    echo colorText("  palm i18n:extract", 'cyan') . "              → Extract translatable strings from views\n";
    echo colorText("  palm i18n:generate [locale]", 'cyan') . "    → Generate translation file\n";
    echo colorText("  palm i18n:check", 'cyan') . "                → Check for missing translations\n";
    echo colorText("  palm security:headers [show|test]", 'cyan') . " → Show/test security headers\n";
    echo colorText("  palm help", 'cyan') . "                     → Show this help screen\n";
    echo "\n";
}

function handleRouteCommand(string $command, string $baseDir): void
{
    require_once $baseDir . '/app/Palm/RouteCache.php';
    
    if ($command === 'route:list') {
        require_once $baseDir . '/app/Palm/Route.php';
        \Frontend\Palm\Route::init($baseDir . '/src');
        require $baseDir . '/src/routes/main.php';
        
        $routes = \Frontend\Palm\Route::all();
        $names = \Frontend\Palm\Route::names();
        
        echo colorText("Registered Routes:\n", 'cyan');
        echo str_repeat('=', 80) . "\n";
        
        foreach ($routes as $method => $methodRoutes) {
            echo colorText("\n{$method} Routes:\n", 'yellow');
            foreach ($methodRoutes as $path => $handler) {
                $name = array_search(['method' => $method, 'path' => $path], $names);
                $nameStr = $name ? colorText(" [{$name}]", 'green') : '';
                echo "  " . colorText($path, 'cyan') . $nameStr . "\n";
            }
        }
        
        echo "\n" . str_repeat('=', 80) . "\n";
        $total = (count($routes['GET'] ?? [])) + (count($routes['POST'] ?? []));
        echo "Total: {$total} routes\n";
    } elseif ($command === 'route:clear') {
        \Frontend\Palm\RouteCache::init($baseDir);
        if (\Frontend\Palm\RouteCache::clear()) {
            echo colorText("✓ Route cache cleared successfully\n", 'green');
        } else {
            echo colorText("✗ Failed to clear route cache\n", 'red');
        }
    }
}

function handleViewCacheCommand(string $baseDir): void
{
    require_once $baseDir . '/app/Palm/ViewCache.php';
    
    \Frontend\Palm\ViewCache::init($baseDir . '/src');
    if (\Frontend\Palm\ViewCache::clear()) {
        echo colorText("✓ View cache cleared successfully\n", 'green');
    } else {
        echo colorText("✗ Failed to clear view cache\n", 'red');
    }
}

function handleSitemapCommand(string $baseDir): void
{
    require_once $baseDir . '/app/Palm/SitemapGenerator.php';
    require_once $baseDir . '/app/Palm/Route.php';
    
    printPalmBanner("Generate Sitemap");
    
    // Initialize routes
    \Frontend\Palm\Route::init($baseDir . '/src');
    require $baseDir . '/src/routes/main.php';
    
    // Get all routes
    $routes = \Frontend\Palm\Route::all();
    
    // Initialize sitemap generator
    $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
    \Frontend\Palm\SitemapGenerator::init($baseDir, $baseUrl);
    \Frontend\Palm\SitemapGenerator::setRoutes($routes);
    
    // Generate sitemap
    if (\Frontend\Palm\SitemapGenerator::generate()) {
        echo colorText("✓ sitemap.xml generated successfully\n", 'green');
    } else {
        echo colorText("✗ Failed to generate sitemap.xml\n", 'red');
    }
    
    // Generate robots.txt
    if (\Frontend\Palm\SitemapGenerator::generateRobotsTxt()) {
        echo colorText("✓ robots.txt generated successfully\n", 'green');
    } else {
        echo colorText("✗ Failed to generate robots.txt\n", 'red');
    }
    
    echo "\n";
    echo colorText("Files created:\n", 'cyan');
    echo "  - {$baseDir}/public/sitemap.xml\n";
    echo "  - {$baseDir}/public/robots.txt\n";
}

function handleOptimizeCommand(string $baseDir, array $args): void
{
    require_once $baseDir . '/app/Palm/AssetMinifier.php';
    
    printPalmBanner("Optimize Assets");
    
    \Frontend\Palm\AssetMinifier::init($baseDir);
    
    $target = strtolower($args[0] ?? 'all');
    $publicPath = $baseDir . '/public';
    
    $cssFiles = [];
    $jsFiles = [];
    
    // Find CSS files
    if (is_dir($publicPath . '/css')) {
        $cssFiles = glob($publicPath . '/css/*.css');
    }
    if (empty($cssFiles)) {
        $cssFiles = glob($publicPath . '/*.css');
    }
    
    // Find JS files
    if (is_dir($publicPath . '/js')) {
        $jsFiles = glob($publicPath . '/js/*.js');
    }
    if (empty($jsFiles)) {
        $jsFiles = glob($publicPath . '/*.js');
    }
    
    $totalFiles = 0;
    $successCount = 0;
    
    if ($target === 'css' || $target === 'all') {
        foreach ($cssFiles as $file) {
            // Skip already minified files
            if (strpos(basename($file), '.min.') !== false) {
                continue;
            }
            
            $totalFiles++;
            if (\Frontend\Palm\AssetMinifier::minifyFile($file, 'css')) {
                $successCount++;
                $pathInfo = pathinfo($file);
                $minPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.min.' . $pathInfo['extension'];
                echo colorText("✓ Minified: ", 'green') . basename($file) . " → " . basename($minPath) . "\n";
            } else {
                echo colorText("✗ Failed: ", 'red') . basename($file) . "\n";
            }
        }
    }
    
    if ($target === 'js' || $target === 'all') {
        foreach ($jsFiles as $file) {
            // Skip already minified files
            if (strpos(basename($file), '.min.') !== false) {
                continue;
            }
            
            // Skip service worker and other special files
            if (strpos(basename($file), 'sw.js') !== false || strpos(basename($file), 'service-worker') !== false) {
                continue;
            }
            
            $totalFiles++;
            if (\Frontend\Palm\AssetMinifier::minifyFile($file, 'js')) {
                $successCount++;
                $pathInfo = pathinfo($file);
                $minPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.min.' . $pathInfo['extension'];
                echo colorText("✓ Minified: ", 'green') . basename($file) . " → " . basename($minPath) . "\n";
            } else {
                echo colorText("✗ Failed: ", 'red') . basename($file) . "\n";
            }
        }
    }
    
    if ($totalFiles === 0) {
        echo colorText("ℹ No files found to minify.\n", 'yellow');
        echo "   Looking in: {$publicPath}\n";
        echo "   Supported: CSS files (*.css) and JS files (*.js)\n";
        echo "   Usage: palm optimize [css|js|all]\n";
    } else {
        echo "\n";
        echo colorText("Summary: ", 'cyan') . "{$successCount}/{$totalFiles} files minified successfully\n";
    }
    
    echo "\n";
}

function handleI18nCommand(string $command, string $baseDir, array $args): void
{
    require_once $baseDir . '/app/Palm/Translator.php';
    
    printPalmBanner("i18n Tools");
    
    switch ($command) {
        case 'i18n:extract':
            echo colorText("ℹ i18n:extract is coming soon\n", 'yellow');
            echo "This will extract translatable strings from your views.\n";
            break;
            
        case 'i18n:generate':
            $locale = $args[0] ?? 'en';
            echo colorText("ℹ i18n:generate is coming soon\n", 'yellow');
            echo "This will generate translation files for locale: {$locale}\n";
            break;
            
        case 'i18n:check':
            echo colorText("ℹ i18n:check is coming soon\n", 'yellow');
            echo "This will check for missing translations.\n";
            break;
    }
    
    echo "\n";
}

function handleSecurityHeadersCommand(string $baseDir, array $args): void
{
    require_once $baseDir . '/app/Palm/SecurityHeaders.php';
    
    $action = strtolower($args[0] ?? 'show');
    
    printPalmBanner("Security Headers");
    
    if ($action === 'show' || $action === 'test') {
        $headers = \Frontend\Palm\SecurityHeaders::getHeaders();
        
        if (empty($headers)) {
            echo colorText("ℹ No security headers are currently set.\n", 'yellow');
            echo "\n";
            echo "To set default headers, add this to your index.php:\n";
            echo "  \\Frontend\\Palm\\SecurityHeaders::setDefaults();\n";
        } else {
            echo colorText("Current Security Headers:\n", 'cyan');
            echo "\n";
            foreach ($headers as $name => $value) {
                echo colorText("  {$name}:", 'green') . " {$value}\n";
            }
        }
        
        if ($action === 'test') {
            echo "\n";
            echo colorText("Test URLs:\n", 'cyan');
            echo "  - https://securityheaders.com/\n";
            echo "  - https://observatory.mozilla.org/\n";
        }
    } else {
        echo "Usage: palm security:headers [show|test]\n";
        echo "\n";
        echo "Actions:\n";
        echo "  show    Show current security headers (default)\n";
        echo "  test    Show headers with testing URLs\n";
    }
    
    echo "\n";
}

function handleMakePwa(string $baseDir, array $args): void
{
    require_once $baseDir . '/app/Palm/PwaGenerator.php';
    
    \Frontend\Palm\PwaGenerator::init($baseDir);
    
    printPalmBanner("Generate PWA");
    
    // Get app name from args or use default
    $appName = $args[0] ?? 'My App';
    $shortName = $args[1] ?? substr($appName, 0, 10);
    
    $config = [
        'name' => $appName,
        'short_name' => $shortName,
        'description' => 'Progressive Web App',
        'start_url' => '/',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#0d6efd',
    ];
    
    // Generate manifest
    if (\Frontend\Palm\PwaGenerator::generateManifest($config)) {
        echo colorText("✓ manifest.json created\n", 'green');
    } else {
        echo colorText("✗ Failed to create manifest.json\n", 'red');
    }
    
    // Generate service worker
    $swConfig = [
        'cache_name' => 'palm-cache-v1',
        'precache' => ['/', '/css/app.css', '/js/app.js'],
        'offline_page' => '/offline.html',
    ];
    
    if (\Frontend\Palm\PwaGenerator::generateServiceWorker($swConfig)) {
        echo colorText("✓ sw.js (service worker) created\n", 'green');
    } else {
        echo colorText("✗ Failed to create service worker\n", 'red');
    }
    
    echo "\n";
    echo colorText("Next steps:\n", 'yellow');
    echo "1. Add PWA meta tags to your layout:\n";
    echo "   <?= pwa_meta(['name' => '{$appName}']) ?>\n";
    echo "2. Add service worker script before </body>:\n";
    echo "   <?= pwa_sw_script() ?>\n";
    echo "3. Create app icons (192x192 and 512x512 PNG) in /public/\n";
    echo "4. Test PWA installation on mobile devices\n";
}

function handleCacheCommand(string $action, string $baseDir): void
{
    if ($action === 'clear') {
        clearCache($baseDir);
    } else {
        echo "Usage: palm cache clear\n";
        echo "Actions:\n";
        echo "  clear    Clear all Palm cache files\n";
        exit(1);
    }
}

function clearCache(string $baseDir): void
{
    printPalmBanner("Clear Cache");
    
    $cacheDirs = [
        $baseDir . '/storage/cache/palm',
        $baseDir . '/storage/cache/assets',
        $baseDir . '/src/assets/compiled',
    ];
    
    $totalFiles = 0;
    $totalDirs = 0;
    
    foreach ($cacheDirs as $cacheDir) {
        if (!is_dir($cacheDir)) {
            continue;
        }
        
        $files = glob($cacheDir . '/*');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if (@unlink($file)) {
                    $deleted++;
                }
            } elseif (is_dir($file)) {
                if (deleteDirectory($file)) {
                    $deleted++;
                }
            }
        }
        
        if ($deleted > 0) {
            echo colorText("Cleared: ", 'green') . "{$cacheDir}\n";
            echo colorText("  Deleted: ", 'cyan') . "{$deleted} file(s)\n";
            $totalFiles += $deleted;
        } else {
            echo colorText("Skipped: ", 'yellow') . "{$cacheDir} (already empty)\n";
        }
    }
    
    if ($totalFiles === 0) {
        echo colorText("\nCache is already empty.\n", 'yellow');
    } else {
        echo colorText("\n✓ Successfully cleared {$totalFiles} cache file(s).\n", 'green');
    }
}

function deleteDirectory(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            @unlink($path);
        }
    }
    
    return @rmdir($dir);
}

function handleServeCommand(string $baseDir, array $args): void
{
    // Load environment variables
    $envPath = $baseDir . '/config';
    $envFile = null;
    
    if (file_exists($envPath . '/.env')) {
        $envFile = $envPath . '/.env';
    } elseif (file_exists($baseDir . '/.env')) {
        $envFile = $baseDir . '/.env';
    }
    
    if ($envFile) {
        // Try to use Dotenv if available
        if (class_exists('\Dotenv\Dotenv')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(dirname($envFile));
            $dotenv->load();
        } else {
            // Fallback: manually parse .env file
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                // Parse KEY=VALUE
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    // Remove quotes if present
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    if (!empty($key)) {
                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
                }
            }
        }
    }
    
    // Check .env for hot reload setting (default: false/off)
    $hotReloadEnabled = strtolower($_ENV['HOT_RELOAD_ENABLED'] ?? 'false');
    $hotReloadEnabled = in_array($hotReloadEnabled, ['true', '1', 'yes', 'on'], true);
    
    // Default: enable auto-open browser, hot reload from .env (defaults to off)
    $openBrowser = true;
    $liveReload = $hotReloadEnabled; // Use .env setting as default
    $portArg = '';

    // Parse arguments
    foreach ($args as $arg) {
        if (in_array(strtolower($arg), ['--no-open', '--no-browser'])) {
            $openBrowser = false;
        } elseif (in_array(strtolower($arg), ['--open', '-o'])) {
            $openBrowser = true;
        } elseif (in_array(strtolower($arg), ['--no-reload', '--no-live-reload'])) {
            $liveReload = false;
        } elseif (in_array(strtolower($arg), ['--reload', '-r', '--live-reload'])) {
            $liveReload = true;
        } elseif (!str_starts_with($arg, '--') && !str_starts_with($arg, '-')) {
            $portArg = $arg;
        }
    }

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
    $url = "http://localhost:{$requestedPort}";

    // Set environment variable for live reload
    if ($liveReload) {
        putenv('PALM_LIVE_RELOAD=1');
        $_ENV['PALM_LIVE_RELOAD'] = '1';
        $_SERVER['PALM_LIVE_RELOAD'] = '1';
    }

    // Create custom router script for live reload support
    $routerScript = $baseDir . '/.palm-router.php';
    if ($liveReload) {
        createLiveReloadRouter($baseDir, $routerScript);
    } else {
        $routerScript = $baseDir . '/index.php';
    }

    $command = sprintf('"%s" -S 0.0.0.0:%d -t "%s" "%s"', $phpBinary, $requestedPort, $baseDir, $routerScript);

    printPalmBanner("Dev Server");
    
    // Format project path for display (shorten if too long)
    $displayPath = $baseDir;
    if (strlen($displayPath) > 55) {
        $displayPath = '...' . substr($displayPath, -52);
    }
    
    // Box formatting constants
    $boxWidth = 70;
    $labelWidth = 13;
    $valueWidth = $boxWidth - $labelWidth - 4; // -4 for borders and spacing
    
    // Server Information Section
    echo colorText("┌─ Server Information " . str_repeat('─', $boxWidth - 22) . "┐\n", 'cyan');
    
    $line = colorText("│ ", 'cyan') . colorText(str_pad("Project:", $labelWidth, ' '), 'green') . 
            colorText(str_pad($displayPath, $valueWidth, ' '), 'white') . colorText(" │\n", 'cyan');
    echo $line;
    
    $line = colorText("│ ", 'cyan') . colorText(str_pad("Port:", $labelWidth, ' '), 'green') . 
            colorText(str_pad((string)$requestedPort, $valueWidth, ' '), 'white') . colorText(" │\n", 'cyan');
    echo $line;
    
    $line = colorText("│ ", 'cyan') . colorText(str_pad("Local URL:", $labelWidth, ' '), 'green') . 
            colorText(str_pad($url, $valueWidth, ' '), 'cyan') . colorText(" │\n", 'cyan');
    echo $line;
    
    if ($localIp) {
        $networkUrl = "http://{$localIp}:{$requestedPort}";
        $line = colorText("│ ", 'cyan') . colorText(str_pad("Network URL:", $labelWidth, ' '), 'green') . 
                colorText(str_pad($networkUrl, $valueWidth, ' '), 'cyan') . colorText(" │\n", 'cyan');
        echo $line;
    }
    
    $hostUrl = "http://{$hostName}:{$requestedPort}";
    $line = colorText("│ ", 'cyan') . colorText(str_pad("Host URL:", $labelWidth, ' '), 'green') . 
            colorText(str_pad($hostUrl, $valueWidth, ' '), 'cyan') . colorText(" │\n", 'cyan');
    echo $line;
    
    echo colorText("└" . str_repeat('─', $boxWidth) . "┘\n", 'cyan');
    echo "\n";
    
    // Features Section
    echo colorText("┌─ Features " . str_repeat('─', $boxWidth - 13) . "┐\n", 'cyan');
    
    if ($liveReload) {
        $status = "Enabled (watching for file changes)";
        $line = colorText("│ ", 'cyan') . colorText(str_pad("🔥 Hot Reload:", $labelWidth, ' '), 'green') . 
                colorText(str_pad($status, $valueWidth, ' '), 'white') . colorText(" │\n", 'cyan');
        echo $line;
    } else {
        $status = "Disabled (set HOT_RELOAD_ENABLED=true)";
        $line = colorText("│ ", 'cyan') . colorText(str_pad("🔥 Hot Reload:", $labelWidth, ' '), 'yellow') . 
                colorText(str_pad($status, $valueWidth, ' '), 'white') . colorText(" │\n", 'cyan');
        echo $line;
    }
    
    if ($openBrowser) {
        $status = "Will open automatically";
        $line = colorText("│ ", 'cyan') . colorText(str_pad("🌐 Browser:", $labelWidth, ' '), 'green') . 
                colorText(str_pad($status, $valueWidth, ' '), 'white') . colorText(" │\n", 'cyan');
        echo $line;
    }
    
    echo colorText("└" . str_repeat('─', $boxWidth) . "┘\n", 'cyan');
    echo "\n";
    
    // Instructions
    echo colorText("💡 ", 'yellow') . colorText("Press ", 'white') . colorText("Ctrl+C", 'yellow') . colorText(" to stop the server\n", 'white');
    echo "\n";
    
    // Separator before server logs
    echo colorText(str_repeat('━', $boxWidth) . "\n", 'cyan');
    echo colorText("Server Logs:\n", 'green');
    echo "\n";
    flush(); // Ensure all styled output is sent before server starts

    // Open browser automatically (with small delay to ensure server is ready)
    if ($openBrowser) {
        // Wait 1.5 seconds for server to start, then open browser
        if (PHP_OS_FAMILY === 'Windows') {
            // Use PowerShell for better cross-version compatibility
            $psCommand = "Start-Sleep -Seconds 1.5; Start-Process '{$url}'";
            startBackgroundProcess("powershell -Command \"{$psCommand}\"");
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            startBackgroundProcess("sleep 1.5 && open \"{$url}\"");
        } else {
            startBackgroundProcess("sleep 1.5 && xdg-open \"{$url}\"");
        }
    }

    // Start server - output will appear after our styled banner
    passthru($command);

    // Cleanup router script, watcher, and WebSocket server if they were created
    if ($liveReload) {
        if (file_exists($routerScript)) {
        @unlink($routerScript);
        }
        $watcherScript = $baseDir . '/.palm-file-watcher.php';
        $wsServerScript = $baseDir . '/.palm-websocket-server.php';
        $notificationFile = $baseDir . '/.palm-reload-notify';
        $wsPortFile = $baseDir . '/.palm-ws-port';
        if (file_exists($watcherScript)) {
            @unlink($watcherScript);
        }
        if (file_exists($wsServerScript)) {
            @unlink($wsServerScript);
        }
        if (file_exists($notificationFile)) {
            @unlink($notificationFile);
        }
        if (file_exists($wsPortFile)) {
            @unlink($wsPortFile);
        }
    }
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
    // Try to set UTF-8 encoding for better character display (Windows)
    if (PHP_OS_FAMILY === 'Windows' && function_exists('mb_internal_encoding')) {
        @mb_internal_encoding('UTF-8');
    }

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

function startBackgroundProcess(string $command): void
{
    if (PHP_OS_FAMILY === 'Windows') {
        // Use cmd /c to run command in background without opening dialog
        pclose(popen('cmd /c "' . $command . '"', "r"));
    } else {
        exec($command . " > /dev/null 2>&1 &");
    }
}

function createWebSocketServer(string $baseDir, string $wsServerScript): void
{
    $baseDirEscaped = addslashes($baseDir);
    
    $wsServerContent = <<<'WSSERVER'
<?php
// Palm WebSocket Server for Hot Reload
$port = isset($argv[1]) ? (int)$argv[1] : 9001;
$baseDir = 'BASE_DIR_PLACEHOLDER';

// Check if socket extension is available
if (!extension_loaded('sockets')) {
    die("Error: PHP sockets extension is not loaded. Please enable it in php.ini\n");
}

// Suppress errors and use error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Suppress all output to prevent blocking
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

$socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    $error = socket_strerror(socket_last_error());
    error_log("Palm WebSocket: Could not create socket: $error");
    exit(1);
}

if (!@socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
    $error = socket_strerror(socket_last_error($socket));
    socket_close($socket);
    error_log("Palm WebSocket: Could not set socket option: $error");
    exit(1);
}

if (!@socket_bind($socket, '127.0.0.1', $port)) {
    $error = socket_strerror(socket_last_error($socket));
    socket_close($socket);
    error_log("Palm WebSocket: Could not bind to port $port: $error");
    exit(1);
}

if (!@socket_listen($socket, 5)) {
    $error = socket_strerror(socket_last_error($socket));
    socket_close($socket);
    error_log("Palm WebSocket: Could not listen on socket: $error");
    exit(1);
}

// Set non-blocking mode
socket_set_nonblock($socket);

// Suppress output to prevent blocking
ob_start();

$clients = [];

function sendToAllClients($clients, $message) {
    $data = json_encode($message);
    $frame = createWebSocketFrame($data);
    foreach ($clients as $client) {
        @socket_write($client, $frame);
    }
}

function createWebSocketFrame($data) {
    $length = strlen($data);
    $frame = chr(0x81); // FIN + text frame
    
    if ($length < 126) {
        $frame .= chr($length);
    } elseif ($length < 65536) {
        $frame .= chr(126) . pack('n', $length);
    } else {
        $frame .= chr(127) . pack('N', 0) . pack('N', $length);
    }
    
    return $frame . $data;
}

function handleWebSocketHandshake($request) {
    if (preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $request, $matches)) {
        $key = $matches[1];
        $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        
        return "HTTP/1.1 101 Switching Protocols\r\n" .
               "Upgrade: websocket\r\n" .
               "Connection: Upgrade\r\n" .
               "Sec-WebSocket-Accept: $accept\r\n\r\n";
    }
    return false;
}

// Listen for file change notifications from watcher
$notifyFile = $baseDir . '/.palm-reload-notify';
$lastNotifyTime = 0;

// Set socket to non-blocking for client connections too
while (true) {
    $read = [$socket];
    $write = null;
    $except = null;
    $changed = @socket_select($read, $write, $except, 0, 100000); // 100ms
    
    if ($changed > 0 && in_array($socket, $read)) {
        $newClient = @socket_accept($socket);
        if ($newClient !== false) {
            socket_set_nonblock($newClient);
            $request = @socket_read($newClient, 5000);
            if ($request !== false && !empty($request)) {
                $response = handleWebSocketHandshake($request);
                if ($response) {
                    @socket_write($newClient, $response);
                    $clients[] = $newClient;
                } else {
                    @socket_close($newClient);
                }
            } else {
                @socket_close($newClient);
            }
        }
    }
    
    // Check for file changes from watcher
    if (file_exists($notifyFile)) {
        $notifyTime = filemtime($notifyFile);
        if ($notifyTime > $lastNotifyTime) {
            $lastNotifyTime = $notifyTime;
            $content = @file_get_contents($notifyFile);
            if ($content) {
                $data = @json_decode($content, true);
                if ($data && isset($data['changed'])) {
                    sendToAllClients($clients, [
                        'type' => 'reload',
                        'files' => array_slice($data['changed'], 0, 10),
                        'timestamp' => $data['timestamp']
                    ]);
                }
            }
        }
    }
    
    // Remove disconnected clients (check if socket is still valid)
    foreach ($clients as $key => $client) {
        $readCheck = [$client];
        $writeCheck = null;
        $exceptCheck = null;
        $changed = @socket_select($readCheck, $writeCheck, $exceptCheck, 0);
        if ($changed === false) {
            // Socket is closed or invalid
            @socket_close($client);
            unset($clients[$key]);
        }
    }
    $clients = array_values($clients);
    
    usleep(50000); // 50ms
}

// Cleanup on exit
function shutdown() {
    global $socket, $clients;
    foreach ($clients as $client) {
        @socket_close($client);
    }
    if (isset($socket)) {
        @socket_close($socket);
    }
}
register_shutdown_function('shutdown');
WSSERVER;
    
    $wsServerContent = str_replace('BASE_DIR_PLACEHOLDER', $baseDirEscaped, $wsServerContent);
    file_put_contents($wsServerScript, $wsServerContent);
}

function startFileWatcher(string $baseDir, string $watcherScript, int $wsPort): void
{
    // Create the file watcher script
    $baseDirEscaped = addslashes($baseDir);
    
    $watcherContent = <<<'WATCHER'
<?php
// Palm File Watcher - Background process for efficient file change detection
$baseDir = 'BASE_DIR_PLACEHOLDER';
$wsPort = WS_PORT_PLACEHOLDER;
$notificationFile = $baseDir . '/.palm-reload-notify';
$checkInterval = 200000; // 200ms in microseconds

function getWatchedFiles(string $baseDir): array {
    static $cache = null;
    static $cacheTime = 0;
    
    // Cache file list for 1 second
    if ($cache !== null && (time() - $cacheTime) < 1) {
        return $cache;
    }
    
    $files = [];
    $dirs = [
        $baseDir . '/src',
        $baseDir . '/app',
        $baseDir . '/src/assets',
    ];
    
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $ext = strtolower($file->getExtension());
                        if (in_array($ext, ['php', 'js', 'css', 'html', 'palm'])) {
                            $files[] = $file->getPathname();
                        }
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
    }
    
    $cache = $files;
    $cacheTime = time();
    
    return $files;
}

// Initialize file timestamps
$fileTimestamps = [];
$files = getWatchedFiles($baseDir);
foreach ($files as $file) {
    if (file_exists($file)) {
        $fileTimestamps[$file] = filemtime($file);
    }
}

// Ensure notification file exists
if (!file_exists($notificationFile)) {
    touch($notificationFile);
}

// Watch for changes
while (true) {
    $currentFiles = getWatchedFiles($baseDir);
    $currentTimestamps = [];
    $hasChanges = false;
    $changedFiles = [];
    
    foreach ($currentFiles as $file) {
        if (file_exists($file)) {
            $mtime = filemtime($file);
            $currentTimestamps[$file] = $mtime;
            
            if (!isset($fileTimestamps[$file]) || $fileTimestamps[$file] !== $mtime) {
                $hasChanges = true;
                $changedFiles[] = $file;
            }
        }
    }
    
    // Check for deleted files
    foreach ($fileTimestamps as $file => $timestamp) {
        if (!isset($currentTimestamps[$file])) {
            $hasChanges = true;
            $changedFiles[] = $file;
        }
    }
    
    // Update notification file if changes detected (WebSocket server reads this)
    if ($hasChanges) {
        $fileTimestamps = $currentTimestamps;
        
        // Write notification with changed files (WebSocket server will broadcast)
        file_put_contents($notificationFile, json_encode([
            'changed' => array_slice($changedFiles, 0, 20),
            'timestamp' => time()
        ]));
        
        // Touch file to update modification time
        touch($notificationFile);
    }
    
    usleep($checkInterval);
}
WATCHER;
    
    $watcherContent = str_replace('BASE_DIR_PLACEHOLDER', $baseDirEscaped, $watcherContent);
    $watcherContent = str_replace('WS_PORT_PLACEHOLDER', (string)$wsPort, $watcherContent);
    
    file_put_contents($watcherScript, $watcherContent);
    
    // Start watcher in background
    $phpBinary = PHP_BINARY ?: 'php';
    $command = sprintf('"%s" "%s"', $phpBinary, $watcherScript);
    startBackgroundProcess($command);
}

function checkAndEnableSocketsExtension(): bool
{
    // Check if already loaded
    if (extension_loaded('sockets')) {
        return true;
    }
    
    // Try to load extension dynamically (if allowed and available)
    // Note: dl() is usually disabled in modern PHP for security
    if (function_exists('dl') && ini_get('enable_dl')) {
        // Try common extension names
        $extensionNames = ['sockets', 'php_sockets'];
        $extensions = [];
        
        // Determine OS-specific extension file
        if (PHP_OS_FAMILY === 'Windows') {
            $extensions = ['php_sockets.dll'];
        } else {
            $extensions = ['sockets.so', 'php_sockets.so'];
        }
        
        foreach ($extensions as $ext) {
            if (@dl($ext)) {
                if (extension_loaded('sockets')) {
                    return true;
                }
            }
        }
    }
    
    return false;
}

function getPhpIniPath(): ?string
{
    $iniPath = php_ini_loaded_file();
    if ($iniPath && file_exists($iniPath)) {
        return $iniPath;
    }
    
    // Try common locations
    $configPath = defined('PHP_CONFIG_FILE_PATH') ? PHP_CONFIG_FILE_PATH : '';
    $commonPaths = [];
    if ($configPath) {
        $commonPaths[] = $configPath . '/php.ini';
        $commonPaths[] = $configPath . '/php-cli.ini';
    }
    $commonPaths[] = dirname(PHP_BINARY) . '/php.ini';
    
    foreach ($commonPaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return null;
}

function createLiveReloadRouter(string $baseDir, string $routerScript): void
{
    // Double-check that hot reload is enabled via env variable
    $hotReloadEnabled = strtolower($_ENV['HOT_RELOAD_ENABLED'] ?? 'false');
    $hotReloadEnabled = in_array($hotReloadEnabled, ['true', '1', 'yes', 'on'], true);
    
    if (!$hotReloadEnabled) {
        // Hot reload is disabled, don't set up anything
        return;
    }
    
    $liveReloadJs = getLiveReloadScript();
    $assetsDir = $baseDir . '/src/assets';
    $liveReloadJsPath = $assetsDir . '/live-reload.js';
    
    // Ensure directory exists
    if (!is_dir($assetsDir)) {
        mkdir($assetsDir, 0777, true);
    }
    
    // Create live reload JavaScript file
    file_put_contents($liveReloadJsPath, $liveReloadJs);
    
    // Create WebSocket server script
    $wsServerScript = $baseDir . '/.palm-websocket-server.php';
    $watcherScript = $baseDir . '/.palm-file-watcher.php';
    
    // Create WebSocket server
    createWebSocketServer($baseDir, $wsServerScript);
    
    // Check and try to enable sockets extension
    $socketsAvailable = checkAndEnableSocketsExtension();
    
    if (!$socketsAvailable) {
        $iniPath = getPhpIniPath();
        echo colorText("⚠️  Warning: ", 'yellow') . "PHP sockets extension not available.\n";
        
        if ($iniPath) {
            echo colorText("   ", 'yellow') . "To enable WebSocket hot reload:\n";
            echo colorText("   1. Open: ", 'cyan') . $iniPath . "\n";
            echo colorText("   2. Find: ", 'cyan') . ";extension=sockets\n";
            echo colorText("   3. Change to: ", 'cyan') . "extension=sockets\n";
            echo colorText("   4. Restart PHP/server\n", 'cyan');
        } else {
            echo colorText("   ", 'yellow') . "To enable: Add 'extension=sockets' to your php.ini file\n";
            echo colorText("   ", 'yellow') . "Find php.ini: php --ini\n";
        }
        
        echo colorText("   ", 'yellow') . "Hot reload will use file-based fallback (HTTP polling).\n";
        
        // Fall back to file-based system
        $notificationFile = $baseDir . '/.palm-reload-notify';
        if (!file_exists($notificationFile)) {
            touch($notificationFile);
        }
        startFileWatcher($baseDir, $watcherScript, 0); // Port 0 = file-based fallback
        file_put_contents($baseDir . '/.palm-ws-port', '0'); // 0 = fallback mode
    } else {
        // Start WebSocket server in background
        $phpBinary = PHP_BINARY ?: 'php';
        $wsPort = findAvailablePort(9000, 9099) ?? 9001;
        
        // Start WebSocket server in background with error output
        $wsCommand = sprintf('"%s" "%s" %d 2>&1', $phpBinary, $wsServerScript, $wsPort);
        
        $logFile = $baseDir . '/.palm-ws-server.log';
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: Use START /B to run in background without blocking
            $wsCommand = sprintf('"%s" "%s" %d', $phpBinary, $wsServerScript, $wsPort);
            $fullCommand = sprintf('START /B "" %s > "%s" 2>&1', $wsCommand, $logFile);
            // Execute and immediately return (don't wait)
            exec($fullCommand);
        } else {
            // Unix: redirect to log file
            $wsCommand = sprintf('"%s" "%s" %d > "%s" 2>&1 &', $phpBinary, $wsServerScript, $wsPort, $logFile);
            exec($wsCommand);
        }
        
        // Store port immediately and continue (don't wait for server to start)
        echo colorText("🔄 WebSocket Server: ", 'cyan') . "Starting on port {$wsPort}\n";
        // Don't wait - server will start in background, client will connect when ready
        
        // Store WebSocket port for client
        file_put_contents($baseDir . '/.palm-ws-port', (string)$wsPort);
        
        // Start file watcher
        startFileWatcher($baseDir, $watcherScript, $wsPort);
    }
    
    // File watcher is started above based on socket availability
    
    // Create router script that handles live reload endpoint and injects script
    $baseDirEscaped = addslashes($baseDir);
    $routerContent = <<<ROUTER
<?php
// Palm Live Reload Router
\$requestUri = \$_SERVER['REQUEST_URI'] ?? '/';
\$requestPath = parse_url(\$requestUri, PHP_URL_PATH) ?? '/';
\$baseDir = '{$baseDirEscaped}';

// Handle live reload endpoint (file-based fallback when WebSocket unavailable)
if (\$requestPath === '/__palm_live_reload__') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    
    \$notificationFile = \$baseDir . '/.palm-reload-notify';
    \$lastCheck = isset(\$_GET['last']) ? (int)\$_GET['last'] : 0;
    
    // Check notification file (single file stat - very fast)
    if (file_exists(\$notificationFile)) {
        \$notifyTime = filemtime(\$notificationFile);
        
        // If notification file was modified after last check, changes detected
        if (\$notifyTime > \$lastCheck) {
            // Read changed files from notification file
            \$changedFiles = [];
            if (filesize(\$notificationFile) > 0) {
                \$content = @file_get_contents(\$notificationFile);
                if (\$content !== false) {
                    \$data = @json_decode(\$content, true);
                    if (is_array(\$data) && isset(\$data['changed'])) {
                        \$changedFiles = \$data['changed'];
                    }
        }
    }
    
    echo json_encode([
                'changed' => true,
                'timestamp' => \$notifyTime,
                'files' => array_slice(\$changedFiles, 0, 10)
            ]);
            exit;
        }
    }
    
    // No changes detected
    echo json_encode([
        'changed' => false,
        'timestamp' => time()
    ]);
    exit;
}

// Serve assets from src/assets
if (strpos(\$requestPath, '/src/assets/') === 0) {
    \$assetPath = \$baseDir . \$requestPath;
    if (file_exists(\$assetPath) && is_file(\$assetPath)) {
        \$ext = strtolower(pathinfo(\$assetPath, PATHINFO_EXTENSION));
        \$mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
        ];
        \$mimeType = \$mimeTypes[\$ext] ?? 'application/octet-stream';
        
        header('Content-Type: ' . \$mimeType);
        if (strpos(basename(\$assetPath), 'live-reload') !== false) {
            header('Cache-Control: no-cache');
        } else {
            header('Cache-Control: public, max-age=3600');
        }
        header('Content-Length: ' . filesize(\$assetPath));
        readfile(\$assetPath);
        exit;
    }
    http_response_code(404);
    exit;
}

// For all other requests, use index.php but inject live reload script and WebSocket port
// Start output buffering to intercept HTML output
ob_start(function(\$buffer) use (\$baseDir) {
    // Check if hot reload is enabled via env variable
    \$hotReloadEnabled = strtolower(\$_ENV['HOT_RELOAD_ENABLED'] ?? 'false');
    \$hotReloadEnabled = in_array(\$hotReloadEnabled, ['true', '1', 'yes', 'on'], true);
    
    if (!\$hotReloadEnabled) {
        // Hot reload is disabled, don't inject anything
        return \$buffer;
    }
    
    // Only inject if it's HTML
    if (stripos(\$buffer, '<html') !== false || stripos(\$buffer, '<!doctype') !== false) {
        // Read WebSocket port
        \$wsPortFile = \$baseDir . '/.palm-ws-port';
        \$wsPort = 9001; // default
        if (file_exists(\$wsPortFile)) {
            \$wsPort = trim(file_get_contents(\$wsPortFile)) ?: 9001;
        }
        
        // Inject WebSocket port meta tag in <head>
        \$metaTag = '<meta name="palm-ws-port" content="' . htmlspecialchars(\$wsPort) . '">';
        if (stripos(\$buffer, '<head>') !== false) {
            \$buffer = str_ireplace('<head>', '<head>' . "\\n    " . \$metaTag, \$buffer);
        } elseif (stripos(\$buffer, '</head>') !== false) {
            \$buffer = str_ireplace('</head>', "    " . \$metaTag . "\\n</head>", \$buffer);
        }
        
        // Inject live reload script before </body>
        \$script = '<script src="/src/assets/live-reload.js"></script>';
        if (stripos(\$buffer, '</body>') !== false) {
            \$buffer = str_ireplace('</body>', \$script . "\\n</body>", \$buffer);
        } else {
            // If no </body>, append before </html>
            if (stripos(\$buffer, '</html>') !== false) {
                \$buffer = str_ireplace('</html>', \$script . "\\n</html>", \$buffer);
            } else {
                // Last resort: append at end
                \$buffer .= \$script;
            }
        }
    }
    return \$buffer;
});

require \$baseDir . '/index.php';
ROUTER;
    
    file_put_contents($routerScript, $routerContent);
}

function getLiveReloadScript(): string
{
    return <<<'JS'
(function() {
    'use strict';
    
    let isReloading = false;
    let reloadTimeout = null;
    const DEBOUNCE_TIME = 200; // Wait 200ms after last change before reloading
    
    // Show connection status
    function showStatus(message, type = 'info') {
        const statusEl = document.getElementById('__palm_reload_status__');
        if (statusEl) {
            statusEl.textContent = message;
            statusEl.className = `__palm_status_${type}`;
        }
    }
    
    // Create status indicator
    function createStatusIndicator() {
        const style = document.createElement('style');
        style.textContent = `
            #__palm_reload_status__ {
                position: fixed;
                bottom: 10px;
                right: 10px;
                background: #0d6efd;
                color: white;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 11px;
                font-family: monospace;
                z-index: 99999;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                transition: all 0.3s ease;
            }
            .__palm_status_success { background: #16a34a !important; }
            .__palm_status_error { background: #dc2626 !important; }
            .__palm_status_reloading { background: #f59e0b !important; animation: pulse 1s infinite; }
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.7; }
            }
        `;
        document.head.appendChild(style);
        
        const indicator = document.createElement('div');
        indicator.id = '__palm_reload_status__';
        indicator.textContent = '🔄 Hot Reload Active';
        document.body.appendChild(indicator);
    }
    
    // WebSocket connection for hot reload (no HTTP requests!)
    let ws = null;
    let reconnectAttempts = 0;
    const MAX_RECONNECT_ATTEMPTS = 10;
    const RECONNECT_DELAY = 1000;
    
    function getWebSocketPort() {
        // Try to get port from meta tag or default to 9001
        const meta = document.querySelector('meta[name="palm-ws-port"]');
        return meta ? parseInt(meta.content) : 9001;
    }
    
    function connectWebSocket() {
        if (ws && ws.readyState === WebSocket.OPEN) {
            return; // Already connected
        }
        
        const port = getWebSocketPort();
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.hostname}:${port}`;
        
        try {
            ws = new WebSocket(wsUrl);
            
            ws.onopen = function() {
                reconnectAttempts = 0;
                showStatus('✅ Hot Reload Active', 'success');
            };
            
            ws.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    if (data.type === 'reload') {
        if (isReloading) return;
        
                        // Clear any pending reload
                        if (reloadTimeout) {
                            clearTimeout(reloadTimeout);
                        }
                        
                        // Show reloading status
                        showStatus('🔄 Reloading...', 'reloading');
                        
                    // Debounce: wait a bit before reloading
                        reloadTimeout = setTimeout(() => {
                        if (!isReloading) {
                            isReloading = true;
                                const changedFiles = data.files || [];
                                const fileList = changedFiles
                                    .map(f => f.split(/[/\\]/).pop())
                                    .slice(0, 3)
                                    .join(', ');
                                console.log(`[Palm Hot Reload] Changes detected in: ${fileList}${changedFiles.length > 3 ? '...' : ''}`);
                            window.location.reload();
                        }
                    }, DEBOUNCE_TIME);
                }
                } catch (e) {
                    console.error('[Palm Hot Reload] Error parsing message:', e);
                }
            };
            
            ws.onerror = function(error) {
                showStatus('⚠️ Connection Error', 'error');
            };
            
            ws.onclose = function() {
                showStatus('⚠️ Reconnecting...', 'error');
                
                // Attempt to reconnect
                if (reconnectAttempts < MAX_RECONNECT_ATTEMPTS) {
                    reconnectAttempts++;
                    setTimeout(() => {
                        connectWebSocket();
                    }, RECONNECT_DELAY * reconnectAttempts);
                } else {
                    showStatus('⚠️ Hot Reload Unavailable', 'error');
                }
            };
        } catch (error) {
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                showStatus('⚠️ Hot Reload Unavailable', 'error');
            }
        }
    }
    
    // Initialize status indicator
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createStatusIndicator);
    } else {
        createStatusIndicator();
    }
    
    // Connect WebSocket after page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(connectWebSocket, 500);
        });
    } else {
        setTimeout(connectWebSocket, 500);
    }
    
    // Reconnect when tab becomes visible
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && (!ws || ws.readyState !== WebSocket.OPEN)) {
            connectWebSocket();
        }
    });
    
    // Clean up on page unload
    window.addEventListener('beforeunload', () => {
        if (ws) {
            ws.close();
        }
    });
})();
JS;
}


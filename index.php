<?php

/**
 * PHP-Palm Framework Entry Point
 * 
 * Optimized with:
 * - Application bootstrap caching (loads once, reuses)
 * - Direct route lookup (hash map, O(1) for exact matches)
 * - Comprehensive security scanning
 * - Performance optimizations
 */

// Start execution time tracking
$executionStartTime = microtime(true);

// Enable OPcache optimizations
if (function_exists('opcache_reset') && extension_loaded('Zend OPcache')) {
    // OPcache is available - framework will benefit from bytecode caching
}

/**
 * Worker-aware exit helper
 */
if (!function_exists('palm_exit')) {
    function palm_exit($response = null)
    {
        if (defined('PALM_WORKER')) {
            if ($response !== null) echo $response;
            throw new \App\Core\PalmExitException();
        }
        if ($response !== null) echo $response;
        exit;
    }
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

use PhpPalm\Core\Route;
use App\Core\ApplicationBootstrap;
use App\Core\PublicFileServer;
use App\Core\Security\Session;
use App\Core\Security\CSRF;
use App\Core\Security\RateLimiter;
use App\Core\Security\InputValidator;
use App\Core\Security\FastSecurityScanner;
use Frontend\Palm\Route as FrontendRoute;
use Frontend\Palm\ErrorHandler;
use Frontend\Palm\CsrfInjector;
use Frontend\Palm\Translator;

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$publicFileServer = new PublicFileServer(__DIR__ . '/public');

// -------- APP DIRECTORY ACCESS CONTROL (Early Block) --------
// Block direct access to /app/ directory if configured
if (strpos($requestPath, '/app/') === 0 || preg_match('#(/|^)app/#', $requestPath)) {
    $appAccessConfig = require __DIR__ . '/config/app_access.php';

    if ($appAccessConfig['restrict_access']) {
        // Get client IP (handle forwarded headers)
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedIps = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $clientIp = trim($forwardedIps[0]);
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $clientIp = $_SERVER['HTTP_X_REAL_IP'];
        }

        $allowedIps = $appAccessConfig['allowed_ips'] ?? [];
        $allowInDev = $appAccessConfig['allow_in_dev'] ?? true;

        // Check if in development environment
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
        $isDev = $allowInDev && (strtolower($env) === 'development' || strtolower($env) === 'dev');

        // Check if IP is allowed
        $ipAllowed = in_array($clientIp, $allowedIps) || in_array('*', $allowedIps);

        // Block if not in dev and IP not allowed
        if (!$isDev && !$ipAllowed) {
            http_response_code($appAccessConfig['error_code'] ?? 403);
            header('Content-Type: application/json; charset=UTF-8');

            $errorMessage = $appAccessConfig['error_message'] ?? 'Access to /app/ directory is restricted to developers only.';

            echo json_encode([
                'status' => 'error',
                'message' => $errorMessage,
                'code' => $appAccessConfig['error_code'] ?? 403,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            palm_exit();
        }
    }
}

// -------- PUBLIC FILE SERVING (Early Exit) --------
if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    if ($publicFileServer->isPublicFileRequest($requestUri)) {
        $publicFileServer->serveFile($requestUri);
        palm_exit();
    }

    $extension = pathinfo($requestPath, PATHINFO_EXTENSION);
    if (!empty($extension)) {
        $publicPath = __DIR__ . '/public';
        $normalizedPath = ltrim($requestPath, '/');

        if (strpos($normalizedPath, 'api/') === 0) {
            $normalizedPath = substr($normalizedPath, 4);
        }

        $testFilePath = $publicPath . '/' . $normalizedPath;
        if (file_exists($testFilePath) && is_file($testFilePath)) {
            $publicFileServer->serveFile($requestUri);
            palm_exit();
        }
    }
}

// -------- REQUEST TYPE RESOLUTION --------
$isApiRequest = (bool)preg_match('#(^|/)api(/|$)#', $requestPath);

if (!$isApiRequest) {
    if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true) && $publicFileServer->isPublicFileRequest($requestUri)) {
        $publicFileServer->serveFile($requestUri);
        palm_exit();
    }

    // Initialize frontend routing
    ErrorHandler::init();

    // Initialize internationalization
    Translator::init(__DIR__);

    // Initialize progressive resource loader (lazy loading, preloading)
    require_once __DIR__ . '/app/Palm/ProgressiveResourceLoader.php';
    \Frontend\Palm\ProgressiveResourceLoader::init();

    // Initialize response optimization (compression, caching) - MUST be before CSRF injector
    require_once __DIR__ . '/app/Palm/ResponseOptimizer.php';
    \Frontend\Palm\ResponseOptimizer::init();

    // Initialize auto CSRF injection (uses output buffering)
    CsrfInjector::init();

    // Enable HTML minification and progressive loading in production
    $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
    if (strtolower($env) !== 'development' && strtolower($env) !== 'dev') {
        \Frontend\Palm\ResponseOptimizer::enableHtmlMinification();
    }

    // Set security headers
    require_once __DIR__ . '/app/Palm/SecurityHeaders.php';
    \Frontend\Palm\SecurityHeaders::setDefaults();

    // Initialize Google Auth (if configured)
    try {
        require_once __DIR__ . '/app/Palm/GoogleAuth.php';
        \Frontend\Palm\GoogleAuth::initFromEnv();
    } catch (\Exception $e) {
        // Google Auth not configured - silently ignore
    }

    // Serve assets from src/assets before routing
    if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        if (strpos($requestPath, '/src/assets/') === 0) {
            $assetFile = __DIR__ . $requestPath;
            if (file_exists($assetFile) && is_file($assetFile)) {
                $ext = strtolower(pathinfo($assetFile, PATHINFO_EXTENSION));
                $mimeTypes = [
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
                $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

                $fileSize = filesize($assetFile);
                $lastModified = filemtime($assetFile);
                $isLiveReload = strpos(basename($assetFile), 'live-reload') !== false;

                header('Content-Type: ' . $mimeType);
                if ($isLiveReload) {
                    header('Cache-Control: no-cache, must-revalidate');
                } else {
                    header('Cache-Control: public, max-age=3600');
                    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
                    header('ETag: "' . md5($assetFile . $lastModified . $fileSize) . '"');
                }
                header('Vary: Accept-Encoding');

                // Handle caching
                if (!$isLiveReload && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                    $ifModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
                    if ($ifModifiedSince >= $lastModified) {
                        http_response_code(304);
                        palm_exit();
                    }
                }

                // Enable compression for text-based assets
                $compressibleTypes = ['text/css', 'application/javascript', 'text/javascript', 'application/json'];
                $shouldCompress = in_array($mimeType, $compressibleTypes) && $fileSize > 1024;

                if ($shouldCompress) {
                    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
                    if (strpos($acceptEncoding, 'gzip') !== false && function_exists('gzencode')) {
                        $content = file_get_contents($assetFile);
                        $compressed = gzencode($content, 6);
                        if ($compressed !== false && strlen($compressed) < $fileSize) {
                            header('Content-Encoding: gzip');
                            header('Content-Length: ' . strlen($compressed));
                            echo $compressed;
                            palm_exit();
                        }
                    }
                }

                header('Content-Length: ' . $fileSize);
                readfile($assetFile);
                palm_exit();
            }
        }
    }

    FrontendRoute::init(__DIR__ . '/src');

    // Load route definitions
    $frontendEntry = __DIR__ . '/src/routes/web.php';
    if (file_exists($frontendEntry)) {
        require $frontendEntry;

        // Compile routes to cache if not already cached
        if (!FrontendRoute::isRoutesLoaded()) {
            FrontendRoute::compileCache();
        }
    } else {
        http_response_code(404);
        echo 'Frontend entry (src/routes/web.php) not found.';
        palm_exit();
    }

    // Dispatch the route
    FrontendRoute::dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
    palm_exit();
}

try {
    // -------- INITIALIZE BOOTSTRAP CACHE --------
    // Load application state from cache (routes, modules, middlewares)
    // This loads once and reuses on subsequent requests
    ApplicationBootstrap::init();
    $bootstrapState = ApplicationBootstrap::load();

    // Check for route conflicts (from cache or fresh build)
    if ($bootstrapState['has_conflicts'] ?? false) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        $executionTime = microtime(true) - $executionStartTime;

        echo json_encode([
            'execution_time' => round($executionTime * 1000, 2),
            'execution_time_unit' => 'ms',
            'status' => 'error',
            'message' => 'Route conflicts detected between api.php and module routes',
            'conflicts' => $bootstrapState['conflicts'] ?? [],
            'note' => 'Please remove duplicate routes from either api.php or the conflicting module(s)'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        palm_exit();
    }

    // Load environment variables (if not already loaded by bootstrap)
    if (!isset($_ENV['APP_ENV'])) {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/config/');
        $dotenv->load();
    }

    // -------- FAST SECURITY SCANNING (Optimized) --------
    // Quick scan with early exit - no loops, direct threat detection
    $securityScan = FastSecurityScanner::scanRequestFast();

    if (!$securityScan['safe']) {
        // Fast exit on threat detection
        http_response_code(403);
        $executionTime = microtime(true) - $executionStartTime;
        error_log('Security threat detected from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        exit(json_encode([
            'execution_time' => round($executionTime * 1000, 2),
            'execution_time_unit' => 'ms',
            'status' => 'error',
            'message' => 'Security threat detected'
        ]));
    }

    // Pre-compute route path early (used later for direct routing)
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    // Use $requestPath (without query string) instead of $requestUri
    $targetRoutePath = str_replace($basePath . "/api", '', $requestPath);
    $targetRoutePath = rtrim($targetRoutePath, '/');
    if (empty($targetRoutePath)) {
        $targetRoutePath = '/';
    }
    $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    // -------- CORS CONFIG ----------
    $corsConfig = require __DIR__ . '/config/cors.php';
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($corsConfig['allow_all_origins']) {
        header("Access-Control-Allow-Origin: *");
    } else {
        if (in_array($requestOrigin, $corsConfig['allowed_origins'])) {
            header("Access-Control-Allow-Origin: $requestOrigin");
        } else {
            $executionTime = microtime(true) - $executionStartTime;

            header('HTTP/1.1 403 Forbidden');
            exit(json_encode([
                'execution_time' => round($executionTime * 1000, 2),
                'execution_time_unit' => 'ms',
                'status' => 'error',
                'message' => 'Origin not allowed'
            ]));
        }
    }

    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
    header("Access-Control-Allow-Credentials: true");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // -------- ENHANCED SECURITY MIDDLEWARE ----------

    // 🛡 Initialize Secure Session
    Session::start();

    // 🛡 Comprehensive Security Headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; base-uri 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; frame-src 'self' https:;");

    // 🛡 HSTS (HTTP Strict Transport Security) - Only on HTTPS
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }

    // 🛡 Block dangerous HTTP methods
    $badMethods = ['TRACE', 'CONNECT', 'TRACK'];
    if (in_array($_SERVER['REQUEST_METHOD'], $badMethods)) {
        http_response_code(405);
        exit(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
    }

    // 🛡 Enhanced Rate Limiting
    $rateLimitCheck = RateLimiter::checkIp('default');
    if (!$rateLimitCheck['allowed']) {
        http_response_code(429);
        header("X-RateLimit-Limit: " . RateLimiter::getLimit('default'));
        header("X-RateLimit-Remaining: 0");
        header("X-RateLimit-Reset: " . $rateLimitCheck['reset']);
        $executionTime = microtime(true) - $executionStartTime;

        exit(json_encode([
            'execution_time' => round($executionTime * 1000, 2),
            'execution_time_unit' => 'ms',
            'status' => 'error',
            'message' => 'Too many requests, please slow down',
            'retry_after' => $rateLimitCheck['reset'] - time()
        ]));
    }

    // Set rate limit headers
    header("X-RateLimit-Limit: " . RateLimiter::getLimit('default'));
    header("X-RateLimit-Remaining: " . $rateLimitCheck['remaining']);
    header("X-RateLimit-Reset: " . $rateLimitCheck['reset']);

    // 🛡 CSRF Protection (for state-changing methods)
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
        CSRF::token();
    }

    // 🛡 Fast Input Sanitization (optimized, no unnecessary loops)
    // Only sanitize if data exists and is not empty
    if (!empty($_GET)) {
        $_GET = array_map(function ($v) {
            return is_string($v) ? InputValidator::sanitizeString($v) : $v;
        }, $_GET);
    }

    if (!empty($_POST)) {
        $_POST = array_map(function ($v) {
            if (is_string($v)) {
                return InputValidator::sanitizeString($v);
            } elseif (is_array($v)) {
                return InputValidator::sanitizeArray($v);
            }
            return $v;
        }, $_POST);
    }

    if (!empty($_COOKIE)) {
        $_COOKIE = array_map(function ($v) {
            return is_string($v) ? InputValidator::sanitizeString($v) : $v;
        }, $_COOKIE);
    }

    // 🛡 Quick suspicious header check (no loop, direct checks)
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        error_log("Suspicious header: X-Forwarded-For = " . $_SERVER['HTTP_X_FORWARDED_FOR']);
    }
    if (isset($_SERVER['HTTP_X_REAL_IP'])) {
        error_log("Suspicious header: X-Real-Ip = " . $_SERVER['HTTP_X_REAL_IP']);
    }

    // 🛡 Validate request size (prevent DoS via large payloads)
    $maxPostSize = (int)($_ENV['APP_MAX_POST_SIZE'] ?? 10485760); // 10MB default
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $maxPostSize) {
        http_response_code(413);
        exit(json_encode([
            'status' => 'error',
            'message' => 'Request entity too large',
            'max_size' => $maxPostSize,
            'received_size' => $contentLength
        ]));
    }

    // 🛡 Quick Content-Type validation (no loop, direct checks)
    if (in_array($requestMethod, ['POST', 'PUT', 'PATCH'])) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (
            !empty($contentType) &&
            strpos($contentType, 'application/json') !== 0 &&
            strpos($contentType, 'application/x-www-form-urlencoded') !== 0 &&
            strpos($contentType, 'multipart/form-data') !== 0
        ) {
            error_log("Unusual Content-Type: {$contentType} from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    }

    header('Content-Type: application/json; charset=UTF-8');

    // -------- FAST ROUTE DISPATCH (Optimized for Exact Matches) --------
    // Route path pre-computed above, using optimized array-based lookup
    // Router uses hash map (routeIndex) for O(1) exact match - no loops needed
    // Dynamic routes only checked if exact match fails
    try {
        // Get router instance for fast dispatch
        $router = Route::getRouter();
        if ($router !== null) {
            // Fast O(1) route lookup using array indexing
            // Exact matches: Direct array access, no iteration
            // Dynamic routes: Only checked if exact match fails
            $routeInfo = $router->findRoute($requestMethod, $targetRoutePath);

            if ($routeInfo !== null) {
                // Execute route immediately - no loops, instant execution
                $response = $router->executeRoute($routeInfo['route'], $routeInfo['params']);
            } else {
                // Route not found - return 404
                http_response_code(404);
                $response = [
                    'status' => 'error',
                    'message' => 'Route not found',
                    'uri' => $targetRoutePath,
                    'method' => $requestMethod
                ];
            }
        } else {
            // Fallback to standard dispatch if router not initialized
            $response = Route::dispatch();
        }
    } catch (\Throwable $e) {
        $executionTime = microtime(true) - $executionStartTime;

        http_response_code(500);
        echo json_encode([
            'execution_time' => round($executionTime * 1000, 2),
            'execution_time_unit' => 'ms',
            'status' => 'error',
            'message' => 'Route execution error',
            'error_detail' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? $e->getTraceAsString() : null
        ]);
        palm_exit();
    }

    if ($response !== null) {
        // Calculate execution time
        $executionTime = microtime(true) - $executionStartTime;

        // Add execution time to response
        if (is_array($response)) {
            $response = array_merge([
                'execution_time' => round($executionTime * 1000, 2),
                'execution_time_unit' => 'ms'
            ], $response);
        } else {
            $response = [
                'execution_time' => round($executionTime * 1000, 2),
                'execution_time_unit' => 'ms',
                'status' => 'success',
                'data' => $response
            ];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
} catch (\Throwable $th) {
    $executionTime = microtime(true) - $executionStartTime;

    http_response_code(500);
    echo json_encode([
        'execution_time' => round($executionTime * 1000, 2),
        'execution_time_unit' => 'ms',
        'status' => 'error',
        'message' => 'Internal Server Error',
        'error_detail' => $th->getMessage(),
        'file' => $th->getFile(),
        'line' => $th->getLine(),
        'trace' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? $th->getTraceAsString() : null
    ]);
}

<?php
/**
 * Middleware Generator
 * Creates a middleware in the root/middlewares/ directory
 */

if ($argc < 2) {
    echo "\n";
    echo "Error: Middleware name is required\n";
    echo "\n";
    echo "Usage: php make-middleware.php <MiddlewareName>\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php make-middleware.php RateLimitMiddleware\n";
    echo "  php make-middleware.php ApiKeyMiddleware\n";
    echo "  php make-middleware.php LogRequestMiddleware\n";
    echo "\n";
    exit(1);
}

$middlewareName = trim($argv[1]);

if (empty($middlewareName)) {
    echo "\n";
    echo "Error: Middleware name cannot be empty\n";
    echo "\n";
    exit(1);
}

// Validate name
if (!preg_match('/^[a-zA-Z0-9_]+$/', $middlewareName)) {
    echo "\n";
    echo "Error: Middleware name can only contain letters, numbers, and underscores\n";
    echo "\n";
    exit(1);
}

// Convert to PascalCase and ensure it ends with Middleware
$middlewareName = str_replace('_', ' ', $middlewareName);
$middlewareName = ucwords(strtolower($middlewareName));
$middlewareName = str_replace(' ', '', $middlewareName);
$middlewareName = ucfirst($middlewareName);

// Ensure it ends with "Middleware"
if (substr($middlewareName, -10) !== 'Middleware') {
    $middlewareName .= 'Middleware';
}

$middlewaresPath = __DIR__ . '/../../middlewares';

// Create middlewares directory if it doesn't exist
if (!is_dir($middlewaresPath)) {
    mkdir($middlewaresPath, 0755, true);
}

$middlewarePath = $middlewaresPath . '/' . $middlewareName . '.php';

if (file_exists($middlewarePath)) {
    echo "\n";
    echo "Error: Middleware already exists: {$middlewarePath}\n";
    echo "\n";
    exit(1);
}

$middlewareContent = <<<PHP
<?php

namespace App\Middlewares;

use App\Core\Middleware;

/**
 * {$middlewareName}
 * 
 * Custom middleware for handling requests
 * 
 * Usage in routes:
 * Route::get('/path', MiddlewareHelper::use('{$middlewareName}', [\$controller, 'method']));
 * 
 * With constructor parameters:
 * Route::get('/path', MiddlewareHelper::use('{$middlewareName}', \$param1, \$param2, [\$controller, 'method']));
 */
class {$middlewareName} extends Middleware
{
    /**
     * Handle the request
     * 
     * @param callable \$handler The route handler to wrap
     * @param mixed ...\$args Route parameters
     * @return mixed
     */
    public function handle(callable \$handler, ...\$args)
    {
        // Add your middleware logic here
        // 
        // Example: Check something before proceeding
        // if (!\$this->someCheck()) {
        //     return \$this->error('Access denied', 403);
        // }
        
        // Execute the handler
        return \$this->wrap(\$handler, ...\$args);
    }
    
    // Add your custom methods here
    // 
    // Example:
    // protected function someCheck(): bool
    // {
    //     // Your validation logic
    //     return true;
    // }
}
PHP;

file_put_contents($middlewarePath, $middlewareContent);

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "✅ MIDDLEWARE GENERATED SUCCESSFULLY!\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "📄 File: {$middlewareName}.php\n";
echo "📁 Location: {$middlewarePath}\n";
echo "📦 Namespace: App\\Middlewares\\{$middlewareName}\n";
echo "\n";
echo "💡 Usage:\n";
echo "   Route::get('/path', MiddlewareHelper::use('{$middlewareName}', [\$controller, 'method']));\n";
echo "\n";
echo "📚 See middlewares/README.md for examples and best practices\n";
echo "\n";


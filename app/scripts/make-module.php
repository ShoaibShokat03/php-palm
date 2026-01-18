<?php

/**
 * Module Generator (Improved)
 * Creates a complete module with ActiveRecord support
 * Files: Module.php, Controller.php, Service.php, Model.php, API.md
 */

require_once __DIR__ . '/ApiDocGenerator.php';
require_once __DIR__ . '/TableModuleGenerator.php';
require __DIR__ . '/../../vendor/autoload.php'; // Required for TableModuleGenerator DB usage

use App\Database\Db;

if ($argc < 2) {
    echo "\n";
    echo "Error: Module name is required\n";
    echo "\n";
    echo "Usage: php make-module.php <ModuleName> [route-prefix]\n";
    echo "       php make-module.php <ModuleName> usetable <table-name>\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php make-module.php Product\n";
    echo "  php make-module.php Product /products\n";
    echo "  php make-module.php Products usetable products\n";
    echo "\n";
    exit(1);
}

// Get module name
$moduleName = trim($argv[1]);

// Validation
if (empty($moduleName)) {
    echo "Error: Module name cannot be empty\n";
    exit(1);
}
if (!preg_match('/^[a-zA-Z0-9_]+$/', $moduleName)) {
    echo "Error: Module name can only contain letters, numbers, and underscores\n";
    exit(1);
}

// Normalize Module Name
$moduleName = str_replace('_', ' ', $moduleName);
$moduleName = ucwords(strtolower($moduleName));
$moduleName = str_replace(' ', '', $moduleName);
$moduleName = ucfirst($moduleName);

$arg2 = isset($argv[2]) ? trim($argv[2]) : null;

// Check for 'usetable' command
if ($arg2 === 'usetable' || $arg2 === '--usetable') {
    $tableName = $argv[3] ?? null;
    if (!$tableName) {
        echo "Error: Table name is required when using usetable.\n";
        echo "Usage: palm make module {$moduleName} usetable <table_name>\n";
        exit(1);
    }

    // Load environment (needed for Db)
    $envPath = __DIR__ . '/../../config';
    if (file_exists($envPath . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable($envPath);
        $dotenv->load();
    } elseif (file_exists(__DIR__ . '/../../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
        $dotenv->load();
    }

    $db = new Db();
    $db->connect();

    echo "🚀 Generating Module '{$moduleName}' from table '{$tableName}'...\n";
    TableModuleGenerator::generate($db, $tableName, $moduleName);
    exit(0);
}

$routePrefix = (!empty($arg2) && strpos($arg2, '/') === 0) ? $arg2 : '/' . strtolower($moduleName);
$modulePath = __DIR__ . '/../../modules/' . $moduleName;

// Ensure modules directory exists
$modulesDir = __DIR__ . '/../../modules';
if (!is_dir($modulesDir)) mkdir($modulesDir, 0777, true);

// Create module directory
if (!is_dir($modulePath)) {
    if (!mkdir($modulePath, 0777, true)) {
        echo "Error: Could not create module directory: $modulePath\n";
        exit(1);
    }
    echo "Created directory: $modulePath\n";
} else {
    echo "\nError: Module directory already exists: $modulePath\n\n";
    exit(1);
}

// Generate Module.php
$moduleContent = <<<PHP
<?php

namespace App\Modules\\{$moduleName};

use App\Core\Module as BaseModule;
use App\Modules\\{$moduleName}\\Controller;
use PhpPalm\Core\Route;

/**
 * {$moduleName} Module
 */
class Module extends BaseModule
{
    public function __construct()
    {
        parent::__construct('{$moduleName}', '{$routePrefix}');
    }

    public function registerRoutes(): void
    {
        \$controller = new Controller();

        // Register routes
        Route::get('{$routePrefix}', [\$controller, 'index']);
        Route::get('{$routePrefix}/{id}', [\$controller, 'show']);
        Route::post('{$routePrefix}', [\$controller, 'store']);
        Route::put('{$routePrefix}/{id}', [\$controller, 'update']);
        Route::delete('{$routePrefix}/{id}', [\$controller, 'destroy']);
    }

    // ============================================
    // CONVENIENCE METHODS FOR INTERNAL CALLS
    // ============================================

    public static function all(array \$filters = []): array
    {
        return static::get("/", \$filters);
    }

    public static function find(int \$id): ?array
    {
        return static::get("/{id}", ['id' => \$id]);
    }

    public static function createRecord(array \$data): array
    {
        return static::post("/", \$data);
    }

    public static function updateRecord(int \$id, array \$data): array
    {
        return static::put("/{id}", \$data, ['id' => \$id]);
    }

    public static function deleteRecord(int \$id): array
    {
        return static::delete("/{id}", ['id' => \$id]);
    }
}
PHP;

file_put_contents($modulePath . '/Module.php', $moduleContent);
echo "✅ Created: Module.php\n";

// Generate Controller.php
$controllerContent = <<<PHP
<?php

namespace App\Modules\\{$moduleName};

use App\Core\Controller as BaseController;
use App\Modules\\{$moduleName}\\Service;
use App\Core\App;

class Controller extends BaseController
{
    protected Service \$service;

    public function __construct()
    {
        \$this->service = new Service();
    }

    /**
     * Get all {$moduleName} records
     */
    public function index(): array
    {
        \$data = \$this->service->getAll();
        return \$this->success(\$data, '{$moduleName} records retrieved successfully');
    }

    /**
     * Get {$moduleName} by ID
     */
    public function show(string \$id): array
    {
        \$data = \$this->service->getById((int)\$id);
        
        if (\$data) {
            return \$this->success(\$data, '{$moduleName} retrieved successfully');
        }

        return \$this->error('{$moduleName} not found', [], 404);
    }

    /**
     * Create new {$moduleName}
     */
    public function store(): array
    {
        \$requestData = App::request()->all();
        \$result = \$this->service->create(\$requestData);
        
        if (\$result['success']) {
            return \$this->success(\$result['data'], '{$moduleName} created successfully', 201);
        }

        return \$this->error(\$result['message'], \$result['errors'] ?? [], 400);
    }

    /**
     * Update {$moduleName}
     */
    public function update(string \$id): array
    {
        \$requestData = App::request()->all();
        \$result = \$this->service->update((int)\$id, \$requestData);
        
        if (\$result['success']) {
            return \$this->success(\$result['data'], '{$moduleName} updated successfully');
        }

        return \$this->error(\$result['message'], \$result['errors'] ?? [], 400);
    }

    /**
     * Delete {$moduleName}
     */
    public function destroy(string \$id): array
    {
        \$result = \$this->service->delete((int)\$id);
        
        if (\$result['success']) {
            return \$this->success([], '{$moduleName} deleted successfully');
        }

        return \$this->error(\$result['message'], [], 404);
    }
}
PHP;

file_put_contents($modulePath . '/Controller.php', $controllerContent);
echo "✅ Created: Controller.php\n";

// Generate Service.php
$serviceContent = <<<PHP
<?php

namespace App\Modules\\{$moduleName};

use App\Core\Service as BaseService;
use App\Modules\\{$moduleName}\\Model;
use App\Core\App;

class Service extends BaseService
{
    public function getAll(): array
    {
        \$request = App::request();

        \$page = max(1, (int)(\$request->get('page') ?? 1));
        \$perPage = min(100, max(1, (int)(\$request->get('per_page') ?? 10)));
        \$search = \$request->get('search') ?? null;

        \$query = Model::where()
            ->search(\$search, ['name', 'email']) 
            ->autoFilter(['name', 'status'])
            ->sort();

        \$total = \$query->count();

        \$records = \$query
            ->paginate(\$page, \$perPage)
            ->all();

        \$lastPage = max(1, (int)ceil(\$total / \$perPage));
        \$from = \$total > 0 ? ((\$page - 1) * \$perPage) + 1 : null;
        \$to = \$total > 0 ? min(\$total, \$page * \$perPage) : null;

        return [
            'meta' => [
                'total' => \$total,
                'page' => \$page,
                'per_page' => \$perPage,
                'last_page' => \$lastPage,
                'from' => \$from,
                'to' => \$to,
                'has_more' => \$page < \$lastPage,
            ],
            'data' => \$records
        ];
    }

    public function getById(int \$id): ?array
    {
        \$model = Model::find(\$id);
        return \$model ? \$model->toArray() : null;
    }

    public function create(array \$data): array
    {
        \$model = Model::create(\$data);

        if (\$model) {
            return [
                'success' => true,
                'data' => \$model->toArray()
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create {$moduleName}'
        ];
    }

    public function update(int \$id, array \$data): array
    {
        \$model = Model::find(\$id);
        
        if (!\$model) {
            return [
                'success' => false,
                'message' => '{$moduleName} not found'
            ];
        }

        \$allowedFields = ['name', 'status', 'description'];
        
        foreach (\$data as \$key => \$value) {
            if (in_array(\$key, \$allowedFields)) {
                \$model->\$key = \$value;
            }
        }

        if (\$model->save()) {
            return [
                'success' => true,
                'data' => \$model->toArray()
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to update {$moduleName}'
        ];
    }

    public function delete(int \$id): array
    {
        \$model = Model::find(\$id);
        
        if (!\$model) {
            return [
                'success' => false,
                'message' => '{$moduleName} not found'
            ];
        }

        if (\$model->delete()) {
            return ['success' => true];
        }

        return [
            'success' => false,
            'message' => 'Failed to delete {$moduleName}'
        ];
    }
}
PHP;

file_put_contents($modulePath . '/Service.php', $serviceContent);
echo "✅ Created: Service.php\n";

// Generate Model.php
$tableName = strtolower($moduleName);
$modelContent = <<<PHP
<?php

namespace App\Modules\\{$moduleName};

use App\Core\Model as BaseModel;
use Frontend\Palm\Validation\Attributes\Required;
use Frontend\Palm\Validation\Attributes\IsString;

class Model extends BaseModel
{
    protected string \$table = '{$tableName}';
    
    public int \$id;

    // #[Required]
    // #[IsString]
    // public string \$name;
    
    public ?string \$created_at = null;
    public ?string \$updated_at = null;
}
PHP;

file_put_contents($modulePath . '/Model.php', $modelContent);
echo "✅ Created: Model.php\n";

// Generate API Docs
if (class_exists('ApiDocGenerator')) {
    ApiDocGenerator::generate($modulePath, $moduleName, $routePrefix);
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "✅ MODULE GENERATED SUCCESSFULLY!\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "📦 Module Name: {$moduleName}\n";
echo "📁 Location: {$modulePath}\n";
echo "🔗 Route Prefix: {$routePrefix}\n";
echo "\n📄 Files Generated:\n";
echo "   ✅ Module.php       - Route registration\n";
echo "   ✅ Controller.php   - HTTP request handlers\n";
echo "   ✅ Service.php      - Business logic\n";
echo "   ✅ Model.php        - Database model\n";
echo "   ✅ API.md           - API Documentation\n";
echo "\n";

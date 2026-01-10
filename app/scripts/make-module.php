<?php

/**
 * Module Generator (Improved)
 * Creates a complete module with ActiveRecord support
 * Files: Module.php, Controller.php, Service.php, Model.php
 */

if ($argc < 2) {
    echo "\n";
    echo "Error: Module name is required\n";
    echo "\n";
    echo "Usage: php make-module.php <ModuleName> [route-prefix]\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php make-module.php Product\n";
    echo "  php make-module.php Product /products\n";
    echo "  php make-module.php products\n";
    echo "\n";
    exit(1);
}

// Get module name and ensure it's not empty
$moduleName = trim($argv[1]);
if (empty($moduleName)) {
    echo "\n";
    echo "Error: Module name cannot be empty\n";
    echo "\n";
    exit(1);
}

// Validate module name (alphanumeric and underscores only)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $moduleName)) {
    echo "\n";
    echo "Error: Module name can only contain letters, numbers, and underscores\n";
    echo "Invalid module name: $moduleName\n";
    echo "\n";
    exit(1);
}

// Convert to PascalCase (handle snake_case and lowercase)
$moduleName = str_replace('_', ' ', $moduleName);
$moduleName = ucwords(strtolower($moduleName));
$moduleName = str_replace(' ', '', $moduleName);
$moduleName = ucfirst($moduleName);

$routePrefix = isset($argv[2]) && !empty(trim($argv[2])) ? trim($argv[2]) : '/' . strtolower($moduleName);
$modulePath = __DIR__ . '/../../modules/' . $moduleName;

// Ensure modules directory exists
$modulesDir = __DIR__ . '/../../modules';
if (!is_dir($modulesDir)) {
    if (!mkdir($modulesDir, 0777, true)) {
        echo "\n";
        echo "Error: Could not create modules directory: $modulesDir\n";
        echo "\n";
        exit(1);
    }
}

// Create module directory
if (!is_dir($modulePath)) {
    if (!mkdir($modulePath, 0777, true)) {
        echo "\n";
        echo "Error: Could not create module directory: $modulePath\n";
        echo "\n";
        exit(1);
    }
    echo "Created directory: $modulePath\n";
} else {
    echo "\n";
    echo "Error: Module directory already exists: $modulePath\n";
    echo "\n";
    echo "If you want to add files to an existing module, use:\n";
    echo "  palm make controller $moduleName <ControllerName>\n";
    echo "  palm make model $moduleName <ModelName>\n";
    echo "  palm make service $moduleName <ServiceName>\n";
    echo "\n";
    exit(1);
}

// Generate Module.php
$moduleContent = <<<PHP
<?php

namespace App\\Modules\\{$moduleName};

use App\\Core\\Module as BaseModule;
use App\\Modules\\{$moduleName}\\Controller;
use PhpPalm\\Core\\Route;

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

    /**
     * Get all records (convenience method)
     * 
     * Usage: {$moduleName}Module::all()
     *        {$moduleName}Module::all(['status' => 'active', 'limit' => 10])
     */
    public static function all(array \$filters = []): array
    {
        return static::get("/", \$filters);
    }

    /**
     * Find record by ID
     * 
     * Usage: {$moduleName}Module::find(5)
     */
    public static function find(int \$id): ?array
    {
        return static::get("/{id}", ['id' => \$id]);
    }

    /**
     * Create new record
     * 
     * Usage: {$moduleName}Module::createRecord(['name' => 'John'])
     */
    public static function createRecord(array \$data): array
    {
        return static::post("/", \$data);
    }

    /**
     * Update record
     * 
     * Usage: {$moduleName}Module::updateRecord(5, ['name' => 'Jane'])
     */
    public static function updateRecord(int \$id, array \$data): array
    {
        return static::put("/{id}", \$data, ['id' => \$id]);
    }

    /**
     * Delete record
     * 
     * Usage: {$moduleName}Module::deleteRecord(5)
     */
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

namespace App\\Modules\\{$moduleName};

use App\\Core\\Controller as BaseController;
use App\\Modules\\{$moduleName}\\Service;

class Controller extends BaseController
{
    protected Service \$service;

    public function __construct()
    {
        \$this->service = new Service();
    }

    /**
     * Get all {$moduleName} records
     * Supports filtering via query parameters
     */
    public function index(): array
    {
        // Extract query parameters for filtering
        \$filters = [
            'status' => \$_GET['status'] ?? null,
            'search' => \$_GET['search'] ?? null,
            'limit' => isset(\$_GET['limit']) ? (int)\$_GET['limit'] : null,
            'offset' => isset(\$_GET['offset']) ? (int)\$ _GET['offset'] : null,
        ];
        
        // Remove null values
        \$filters = array_filter(\$filters, fn(\$v) => \$v !== null);
        
        \$data = \$this->service->getAll(\$filters);
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
        \$requestData = \$this->getRequestData();
        
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
        \$requestData = \$this->getRequestData();
        
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

// Generate Service.php (using ActiveRecord & Validation)
$serviceContent = <<<PHP
<?php

namespace App\\Modules\\{$moduleName};

use App\\Core\\Service as BaseService;
use App\\Modules\\{$moduleName}\\Model;

class Service extends BaseService
{
    /**
     * Get all {$moduleName} records
     * Uses ActiveRecord: Model::all()
     */
    public function getAll(array \$filters = []): array
    {
        \$query = Model::query();
        
        // Example filters
        if (isset(\$filters['search'])) {
            \$query->where('name', 'LIKE', '%' . \$filters['search'] . '%');
        }
        
        // Paging
        if (isset(\$filters['limit'])) {
            \$query->limit((int)\$filters['limit']);
        }
        if (isset(\$filters['offset'])) {
            \$query->offset((int)\$filters['offset']);
        }
        
        \$records = \$query->all();
        
        return [
            'total' => \$records->count(),
            'items' => \$records,
            'filters' => \$filters
        ];
    }

    /**
     * Get {$moduleName} by ID
     */
    public function getById(int \$id): ?array
    {
        \$model = Model::find(\$id);
        return \$model ? \$model->toArray() : null;
    }

    /**
     * Create {$moduleName}
     */
    public function create(array \$data): array
    {
        // 1. Validate & Hydrate using Model Attributes
        // Throws ValidationException if invalid
        \$model = Model::validate(\$data);

        // 2. Save record
        if (\$model->save()) {
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

    /**
     * Update {$moduleName}
     */
    public function update(int \$id, array \$data): array
    {
        \$model = Model::find(\$id);
        
        if (!\$model) {
            return [
                'success' => false,
                'message' => '{$moduleName} not found'
            ];
        }

        // 1. Manually update fields or re-validate partially?
        // For updates, we often want to allow partial updates.
        // Current Model::validate() enforces Required attributes.
        // For now, let's manually bind. 
        // TODO: Add Model::validatePartial() for updates.
        
        foreach (\$data as \$key => \$value) {
            \$model->\$key = \$value;
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

    /**
     * Delete {$moduleName}
     */
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

namespace App\\Modules\\{$moduleName};

use App\\Core\\Model as BaseModel;
use Frontend\\Palm\\Validation\\Attributes\\Required;
use Frontend\\Palm\\Validation\\Attributes\\IsString;

/**
 * {$moduleName} Model
 */
class Model extends BaseModel
{
    protected string \$table = '{$tableName}';
    
    // Primary Key
    public int \$id;

    // TODO: Add your fields here with Validation Attributes!
    
    // #[Required]
    // #[IsString]
    // public string \$name;

    public ?string \$created_at = null;
    public ?string \$updated_at = null;
}
PHP;

file_put_contents($modulePath . '/Model.php', $modelContent);
echo "✅ Created: Model.php\n";

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
echo "   ✅ Model.php        - Database model (ActiveRecord)\n";
echo "\nNext steps:\n";
echo "1. Update the table name in Model.php if needed\n";
echo "2. Add field definitions in Model.php (optional - for IDE autocomplete)\n";
echo "3. Add validation rules in Service.php\n";
echo "4. Customize the controller methods as needed\n";
echo "\n💡 Tip: Use 'palm make usetable <table_name>' to auto-generate from database!\n";

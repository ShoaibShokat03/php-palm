<?php

use App\Database\Db;

/**
 * Table Module Generator
 * Encapsulates logic to generate a module from a database table
 */
class TableModuleGenerator
{
    /**
     * Generate entire module from table
     */
    public static function generate(Db $db, string $tableName, string $moduleName, ?string $routePrefix = null): void
    {
        // Check if table exists
        $result = $db->query("SHOW TABLES LIKE '{$tableName}'");
        if (!$result || $result->num_rows === 0) {
            echo "❌ Table '{$tableName}' does not exist.\n";
            return;
        }

        // Get table columns
        $result = $db->query("DESCRIBE `{$tableName}`");
        $columns = [];
        $primaryKey = 'id';

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[] = [
                    'name' => $row['Field'],
                    'type' => $row['Type'],
                    'null' => $row['Null'],
                    'key' => $row['Key'],
                    'default' => $row['Default'],
                    'extra' => $row['Extra']
                ];

                if ($row['Key'] === 'PRI') {
                    $primaryKey = $row['Field'];
                }
            }
        }

        if (empty($columns)) {
            echo "❌ Table '{$tableName}' has no columns.\n";
            return;
        }

        $modulePath = __DIR__ . '/../../modules/' . $moduleName;

        // Create module directory
        if (!is_dir($modulePath)) {
            mkdir($modulePath, 0777, true);
            echo "📁 Created directory: {$modulePath}\n";
        }

        // Generate route prefix if not provided
        if ($routePrefix === null) {
            $routePrefix = '/' . strtolower($moduleName);
        }

        // Generate Files
        self::generateModel($modulePath, $moduleName, $tableName, $columns, $primaryKey);
        self::generateService($modulePath, $moduleName, $columns);
        self::generateController($modulePath, $moduleName);
        self::generateModule($modulePath, $moduleName, $routePrefix);

        // Generate API Docs
        if (class_exists('ApiDocGenerator')) {
            ApiDocGenerator::generate($modulePath, $moduleName, $routePrefix);
        }

        echo "\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "✅ MODULE GENERATED FROM DATABASE TABLE!\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "📦 Module Name: {$moduleName}\n";
        echo "📁 Location: {$modulePath}\n";
        echo "📊 Database Table: {$tableName}\n";
        echo "🔗 Route Prefix: {$routePrefix}\n";
    }

    private static function generateModel(string $modulePath, string $moduleName, string $tableName, array $columns, string $primaryKey)
    {
        $fields = [];
        foreach ($columns as $column) {
            $fieldName = $column['name'];
            if ($fieldName === 'id') continue;
            if (in_array($fieldName, ['created_at', 'updated_at'])) continue;

            $fieldType = self::mapSqlTypeToPhpType($column['type']);
            $nullable = $column['null'] === 'YES';
            $phpType = $nullable ? "?{$fieldType}" : $fieldType;
            $default = $column['default'] !== null ? " = '{$column['default']}'" : ($nullable ? " = null" : "");

            $fields[] = "    public {$phpType} \${$fieldName}{$default};";
        }
        $fieldsStr = implode("\n", $fields);

        $content = <<<PHP
<?php

namespace App\Modules\\{$moduleName};

use App\Core\Model as BaseModel;
use Frontend\Palm\Validation\Attributes\Required;
use Frontend\Palm\Validation\Attributes\IsString;

class Model extends BaseModel
{
    protected string \$table = '{$tableName}';
    
    public int \$id;
    
{$fieldsStr}

    public ?string \$created_at = null;
    public ?string \$updated_at = null;
}
PHP;
        file_put_contents($modulePath . '/Model.php', $content);
        echo "   ✅ Created: Model.php\n";
    }

    private static function generateService(string $modulePath, string $moduleName, array $columns)
    {
        // Reuse logic for column classification
        $searchableCols = [];
        $filterableCols = [];
        $allowedUpdateCols = [];

        foreach ($columns as $col) {
            $name = $col['name'];
            $type = strtolower($col['type']);
            if ($name === 'id') continue;
            if (strpos($type, 'char') !== false || strpos($type, 'text') !== false) $searchableCols[] = "'$name'";
            if (strpos($type, 'blob') === false && strpos($type, 'text') === false) $filterableCols[] = "'$name'";
            if (!in_array($name, ['created_at', 'updated_at', 'deleted_at'])) $allowedUpdateCols[] = "'$name'";
        }

        $searchColsStr = implode(', ', $searchableCols);
        $filterColsStr = implode(', ', $filterableCols);
        $updateColsStr = implode(', ', $allowedUpdateCols);

        $content = <<<PHP
<?php

namespace App\Modules\\{$moduleName};

use App\Core\Service as BaseService;
use App\Modules\\{$moduleName}\Model;
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
            ->search(\$search, [{$searchColsStr}])
            ->autoFilter([{$filterColsStr}])
            ->sort();

        \$total = \$query->count();
        \$records = \$query->paginate(\$page, \$perPage)->all();
        
        \$lastPage = max(1, (int)ceil(\$total / \$perPage));
        
        return [
            'meta' => [
                'total' => \$total,
                'page' => \$page,
                'per_page' => \$perPage,
                'last_page' => \$lastPage,
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
            return ['success' => true, 'data' => \$model->toArray()];
        }
        return ['success' => false, 'message' => 'Failed to create'];
    }

    public function update(int \$id, array \$data): array
    {
        \$model = Model::find(\$id);
        if (!\$model) return ['success' => false, 'message' => 'Not found'];

        \$allowed = [{$updateColsStr}];
        foreach (\$data as \$key => \$val) {
            if (in_array(\$key, \$allowed)) \$model->\$key = \$val;
        }

        if (\$model->save()) {
            return ['success' => true, 'data' => \$model->toArray()];
        }
        return ['success' => false, 'message' => 'Failed to update'];
    }

    public function delete(int \$id): array
    {
        \$model = Model::find(\$id);
        if (!\$model) return ['success' => false, 'message' => 'Not found'];
        
        if (\$model->delete()) return ['success' => true];
        return ['success' => false, 'message' => 'Failed to delete'];
    }
}
PHP;
        file_put_contents($modulePath . '/Service.php', $content);
        echo "   ✅ Created: Service.php\n";
    }

    private static function generateController(string $modulePath, string $moduleName)
    {
        $content = <<<PHP
<?php

namespace App\Modules\\{$moduleName};

use App\Core\Controller as BaseController;
use App\Modules\\{$moduleName}\Service;
use App\Core\App;

class Controller extends BaseController
{
    protected Service \$service;

    public function __construct()
    {
        \$this->service = new Service();
    }

    public function index(): array
    {
        return \$this->success(\$this->service->getAll());
    }

    public function show(string \$id): array
    {
        \$data = \$this->service->getById((int)\$id);
        return \$data ? \$this->success(\$data) : \$this->error('Not found', [], 404);
    }

    public function store(): array
    {
        \$result = \$this->service->create(App::request()->all());
        return \$result['success'] 
            ? \$this->success(\$result['data'], 'Created', 201)
            : \$this->error(\$result['message'], [], 400);
    }

    public function update(string \$id): array
    {
        \$result = \$this->service->update((int)\$id, App::request()->all());
        return \$result['success']
            ? \$this->success(\$result['data'], 'Updated')
            : \$this->error(\$result['message'], [], 400);
    }

    public function destroy(string \$id): array
    {
        \$result = \$this->service->delete((int)\$id);
        return \$result['success']
            ? \$this->success([], 'Deleted')
            : \$this->error(\$result['message'], [], 404);
    }
}
PHP;
        file_put_contents($modulePath . '/Controller.php', $content);
        echo "   ✅ Created: Controller.php\n";
    }

    private static function generateModule(string $modulePath, string $moduleName, string $routePrefix)
    {
        $content = <<<PHP
<?php

namespace App\Modules\\{$moduleName};

use App\Core\Module as BaseModule;
use App\Modules\\{$moduleName}\Controller;
use PhpPalm\Core\Route;

class Module extends BaseModule
{
    public function __construct()
    {
        parent::__construct('{$moduleName}', '{$routePrefix}');
    }

    public function registerRoutes(): void
    {
        \$c = new Controller();
        Route::get('{$routePrefix}', [\$c, 'index']);
        Route::get('{$routePrefix}/{id}', [\$c, 'show']);
        Route::post('{$routePrefix}', [\$c, 'store']);
        Route::put('{$routePrefix}/{id}', [\$c, 'update']);
        Route::delete('{$routePrefix}/{id}', [\$c, 'destroy']);
    }
}
PHP;
        file_put_contents($modulePath . '/Module.php', $content);
        echo "   ✅ Created: Module.php\n";
    }

    private static function mapSqlTypeToPhpType(string $sqlType): string
    {
        $sqlType = strtolower($sqlType);
        if (strpos($sqlType, 'int') !== false) return 'int';
        if (strpos($sqlType, 'float') !== false || strpos($sqlType, 'decimal') !== false) return 'float';
        if (strpos($sqlType, 'bool') !== false) return 'bool';
        return 'string';
    }

    /**
     * Convert table name to module name
     */
    public static function tableToModuleName(string $tableName): string
    {
        $name = preg_replace('/^tbl_|_tbl$/i', '', $tableName);
        $parts = explode('_', $name);
        return implode('', array_map('ucfirst', $parts));
    }
}

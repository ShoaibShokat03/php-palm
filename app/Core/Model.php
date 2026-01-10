<?php

namespace App\Core;

use App\Database\Db;

use function PHPSTORM_META\type;

/**
 * Base Model Class with ActiveRecord-like functionality
 * All models should extend this class
 * 
 * Usage:
 * - UsersModel::all() - Get all records
 * - UsersModel::where('status', 'active')->all() - Query with conditions
 * - UsersModel::where(['id' => 1])->one() - Get single record
 * - UsersModel::with('posts')->all() - Eager load relationships
 */
abstract class Model implements \JsonSerializable
{
    protected ?Db $db = null;
    protected string $table;
    protected array $attributes = [];
    protected array $relations = [];
    protected static ?Db $staticDb = null;

    // Relationship cache
    protected static array $relationshipCache = [];

    // Reflection cache for performance
    protected static array $reflectionCache = [];

    // Query result cache (simple in-memory cache)
    protected static array $queryCache = [];
    protected static int $queryCacheSize = 100;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        // Unset public properties initially so __get() is always called
        // This ensures property access goes through __get() which checks attributes
        // We don't sync here - properties will be set lazily when accessed via __get()
        $this->unsetPublicProperties();
    }

    /**
     * Unset all public properties so __get() is called when accessing them
     * This ensures that property access always checks the attributes array first
     */
    protected function unsetPublicProperties(): void
    {
        $className = static::class;

        // Cache reflection for performance
        if (!isset(self::$reflectionCache[$className])) {
            $reflection = new \ReflectionClass($this);
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

            $propertyNames = [];
            foreach ($properties as $property) {
                $name = $property->getName();
                // Skip internal properties
                if ($name !== 'db' && $name !== 'table' && $name !== 'attributes' && $name !== 'relations') {
                    $propertyNames[] = $name;
                }
            }
            self::$reflectionCache[$className] = $propertyNames;
        }

        // Unset all public properties so __get() is called
        foreach (self::$reflectionCache[$className] as $name) {
            unset($this->$name);
        }
    }

    /**
     * Get database instance (instance method)
     */
    public function getDb(): Db
    {
        if ($this->db === null) {
            $this->db = new Db();
            $this->db->connect();
        }
        return $this->db;
    }

    /**
     * Get database instance (static method)
     */
    protected static function getStaticDb(): Db
    {
        if (self::$staticDb === null) {
            self::$staticDb = new Db();
            self::$staticDb->connect();
        }
        return self::$staticDb;
    }

    /**
     * Get table name
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Primary key column name.
     * Child models can override this, but we default to conventional 'id'.
     */
    public static function getPrimaryKey(): string
    {
        return 'id';
    }

    /**
     * Create new instance with attributes
     * Optimized to avoid unnecessary operations
     * Properties are unset initially and will be set lazily when accessed via __get()
     */
    public function newInstance(array $attributes): self
    {
        $instance = new static();
        $instance->attributes = $attributes;
        // Unset properties so __get() is always called when accessing them
        $instance->unsetPublicProperties();
        return $instance;
    }

    /**
     * Sync attributes array to public properties
     * This allows both $model->username and $model->attributes['username'] to work
     * Note: Properties are unset initially, so setting them will make them accessible
     */
    protected function syncAttributesToProperties(): void
    {
        foreach ($this->attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Sync public properties back to attributes array
     * This ensures that when properties are set directly, they're saved to attributes
     * Optimized with reflection caching
     * Note: Only syncs properties that are actually set (not unset)
     */
    protected function syncPropertiesToAttributes(): void
    {
        $className = static::class;

        // Use cached reflection to avoid repeated ReflectionClass instantiation
        if (!isset(self::$reflectionCache[$className])) {
            $reflection = new \ReflectionClass($this);
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

            // Cache property names for this class
            $propertyNames = [];
            foreach ($properties as $property) {
                $name = $property->getName();
                // Skip internal properties
                if ($name !== 'db' && $name !== 'table' && $name !== 'attributes' && $name !== 'relations') {
                    $propertyNames[] = $name;
                }
            }
            self::$reflectionCache[$className] = $propertyNames;
        }

        // Use cached property names and check if property is initialized
        $reflection = new \ReflectionClass($this);
        foreach (self::$reflectionCache[$className] as $name) {
            // Check if property exists and is initialized (not unset)
            if ($reflection->hasProperty($name)) {
                $property = $reflection->getProperty($name);
                if ($property->isInitialized($this)) {
                    $this->attributes[$name] = $this->$name;
                }
            }
        }
    }

    /**
     * Get attribute value
     */
    public function getAttribute(string $name)
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Set attribute value
     */
    public function setAttribute(string $name, $value): void
    {
        $this->attributes[$name] = $value;
        // Sync to public property if it exists
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    /**
     * Get all attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set relation
     */
    public function setRelation(string $name, $value): void
    {
        $this->relations[$name] = $value;
    }

    /**
     * Get relation
     */
    public function getRelation(string $name)
    {
        return $this->relations[$name] ?? null;
    }

    /**
     * Magic method to get attributes and relations
     * This is called when accessing a property that doesn't exist or is unset
     */
    public function __get(string $name)
    {
        // Check relations first
        if (isset($this->relations[$name])) {
            return $this->relations[$name];
        }

        // Check attributes - this is the primary source of data
        if (isset($this->attributes[$name])) {
            $value = $this->attributes[$name];
            // Set the property so subsequent accesses are faster (but still go through __get if unset)
            if (property_exists($this, $name)) {
                $this->$name = $value;
            }
            return $value;
        }

        // Check for relationship method
        if (method_exists($this, $name)) {
            $relation = $this->$name();
            if (is_array($relation) && isset($relation['type'])) {
                return $this->loadRelation($name, $relation);
            }
        }

        return null;
    }

    /**
     * Magic method to set attributes
     */
    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
        // Sync to public property if it exists
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    /**
     * Check if attribute exists
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]) || isset($this->relations[$name]);
    }

    /**
     * Convert model to array
     */
    public function toArray(): array
    {
        $array = $this->attributes;

        // Include relations if loaded
        foreach ($this->relations as $key => $value) {
            if (is_array($value)) {
                $array[$key] = array_map(function ($item) {
                    return $item instanceof Model ? $item->toArray() : $item;
                }, $value);
            } elseif ($value instanceof Model) {
                $array[$key] = $value->toArray();
            } else {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    /**
     * Allow models to be json_encode()-ed without manual ->toArray()
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Load a relationship
     */
    protected function loadRelation(string $name, array $relation)
    {
        $type = $relation['type'];
        $relatedClass = $relation['class'];
        $foreignKey = $relation['foreignKey'];
        $localKey = $relation['localKey'] ?? 'id';

        $localValue = $this->getAttribute($localKey);

        if ($type === 'hasOne') {
            $related = $relatedClass::where($foreignKey, $localValue)->one();
            $this->setRelation($name, $related);
            return $related;
        } elseif ($type === 'hasMany') {
            $related = $relatedClass::where($foreignKey, $localValue)->all();
            $this->setRelation($name, $related);
            return $related;
        } elseif ($type === 'belongsTo') {
            $related = $relatedClass::where($relation['localKey'] ?? 'id', $this->getAttribute($foreignKey))->one();
            $this->setRelation($name, $related);
            return $related;
        }

        return null;
    }

    // ============================================
    // STATIC METHODS (ActiveRecord-like)
    // ============================================

    /**
     * Base query builder factory.
     * Reuses a shared DB connection for every model class to reduce connection churn.
     */
    protected static function newQuery(): QueryBuilder
    {
        $model = new static();
        // Reuse static DB connection for better performance
        if (self::$staticDb === null) {
            self::$staticDb = new Db();
            self::$staticDb->connect();
        }
        $model->db = self::$staticDb;

        $query = new QueryBuilder($model);
        return $query->asModels();
    }

    /**
     * Flexible finder modeled after Yii's ActiveRecord::find().
     * - Without arguments returns a QueryBuilder for fluent chaining.
     * - With an ID/condition returns a single model (findOne style) for backwards compatibility.
     */
    public static function find($condition = null)
    {
        if ($condition === null) {
            return static::newQuery();
        }

        return static::findOne($condition);
    }

    /**
     * Get all records
     * Usage: UsersModel::all()
     */
    public static function all(): ModelCollection
    {
        /** @var ModelCollection $results */
        $results = static::newQuery()->all();
        return $results;
    }

    /**
     * Start a query builder
     * Usage: UsersModel::where('status', 'active')->all()
     * Optimized: Reuses static DB connection
     */
    public static function where(?string $column = null, $operator = null, $value = null): QueryBuilder
    {
        $query = static::newQuery();
        if ($column !== null) {
            $query->where($column, $operator, $value);
        }
        return $query;
    }


    /**
     * Alias for where() - allows filter() method for better readability
     * Usage: Model::filter('status', 'active')->filter('role', 'admin')->all()
     */
    public static function filter(?string $column = null, $operator = null, $value = null): QueryBuilder
    {
        return static::where($column, $operator, $value);
    }

    /**
     * Start a query builder with WHERE IN condition
     * Usage: Model::whereIn('id', [1, 2, 3])->all()
     */
    public static function whereIn(string $column, array $values): QueryBuilder
    {
        $query = static::newQuery();
        return $query->whereIn($column, $values);
    }

    // ============================================
    // LINQ-STYLE AGGREGATE METHODS (Static Proxies)
    // ============================================

    /**
     * Get the sum of a column
     * Usage: Order::sum('amount')
     */
    public static function sum(string $column)
    {
        return static::newQuery()->sum($column);
    }

    /**
     * Get the average of a column
     * Usage: Employee::avg('salary')
     */
    public static function avg(string $column)
    {
        return static::newQuery()->avg($column);
    }

    /**
     * Get the maximum value of a column
     * Usage: Product::max('price')
     */
    public static function max(string $column)
    {
        return static::newQuery()->max($column);
    }

    /**
     * Get the minimum value of a column
     * Usage: Product::min('price')
     */
    public static function min(string $column)
    {
        return static::newQuery()->min($column);
    }

    // ============================================
    // ADVANCED WHERE CONDITIONS (Static Proxies)
    // ============================================

    /**
     * Add WHERE BETWEEN condition
     * Usage: Product::whereBetween('price', [10, 100])->get()
     */
    public static function whereBetween(string $column, array $range): QueryBuilder
    {
        return static::newQuery()->whereBetween($column, $range);
    }

    /**
     * Add WHERE NOT BETWEEN condition
     * Usage: Product::whereNotBetween('price', [10, 100])->get()
     */
    public static function whereNotBetween(string $column, array $range): QueryBuilder
    {
        return static::newQuery()->whereNotBetween($column, $range);
    }

    /**
     * Add WHERE NULL condition
     * Usage: User::whereNull('deleted_at')->get()
     */
    public static function whereNull(string $column): QueryBuilder
    {
        return static::newQuery()->whereNull($column);
    }

    /**
     * Add WHERE NOT NULL condition
     * Usage: User::whereNotNull('email')->get()
     */
    public static function whereNotNull(string $column): QueryBuilder
    {
        return static::newQuery()->whereNotNull($column);
    }

    /**
     * Add WHERE DATE condition
     * Usage: Order::whereDate('created_at', '2024-01-15')->get()
     */
    public static function whereDate(string $column, string $operator, string $date = null): QueryBuilder
    {
        return static::newQuery()->whereDate($column, $operator, $date);
    }

    /**
     * Add WHERE MONTH condition
     * Usage: Order::whereMonth('created_at', 12)->get()
     */
    public static function whereMonth(string $column, int $month): QueryBuilder
    {
        return static::newQuery()->whereMonth($column, $month);
    }

    /**
     * Add WHERE YEAR condition
     * Usage: Order::whereYear('created_at', 2024)->get()
     */
    public static function whereYear(string $column, int $year): QueryBuilder
    {
        return static::newQuery()->whereYear($column, $year);
    }

    /**
     * Add WHERE column comparison
     * Usage: User::whereColumn('first_name', '=', 'last_name')->get()
     */
    public static function whereColumn(string $column1, string $operator, string $column2): QueryBuilder
    {
        return static::newQuery()->whereColumn($column1, $operator, $column2);
    }

    /**
     * Add raw WHERE condition
     * Usage: User::whereRaw('age > ? AND status = ?', [18, 'active'])->get()
     */
    public static function whereRaw(string $sql, array $bindings = []): QueryBuilder
    {
        return static::newQuery()->whereRaw($sql, $bindings);
    }

    // ============================================
    // JOIN METHODS (Static Proxies)
    // ============================================

    /**
     * Add INNER JOIN
     * Usage: Order::join('users', 'orders.user_id', '=', 'users.id')->get()
     */
    public static function join(string $table, string $first, string $operator, string $second): QueryBuilder
    {
        return static::newQuery()->join($table, $first, $operator, $second);
    }

    /**
     * Add LEFT JOIN
     * Usage: Order::leftJoin('users', 'orders.user_id', '=', 'users.id')->get()
     */
    public static function leftJoin(string $table, string $first, string $operator, string $second): QueryBuilder
    {
        return static::newQuery()->leftJoin($table, $first, $operator, $second);
    }

    /**
     * Add RIGHT JOIN
     * Usage: Order::rightJoin('users', 'orders.user_id', '=', 'users.id')->get()
     */
    public static function rightJoin(string $table, string $first, string $operator, string $second): QueryBuilder
    {
        return static::newQuery()->rightJoin($table, $first, $operator, $second);
    }

    /**
     * Process records in chunks
     * Usage: User::chunk(100, function($users) { foreach($users as $user) { ... } })
     */
    public static function chunk(int $size, \Closure $callback): bool
    {
        return static::newQuery()->chunk($size, $callback);
    }

    /**
     * Process records in chunks by ID (more efficient)
     * Usage: User::chunkById(100, function($users) { ... })
     */
    public static function chunkById(int $size, \Closure $callback, string $column = 'id'): bool
    {
        return static::newQuery()->chunkById($size, $callback, $column);
    }

    /**
     * Find record by ID
     * Usage: UsersModel::find(1)
     */
    /**
     * Fast and correct: Find single record by ID or condition.
     * - If $id is null: returns first record quickly (limit 1 in SQL)
     * - If $id is array: expects ['column' => ..., 'operator' => ..., 'value' => ...]
     * - Otherwise: find by primary 'id'
     */
    public static function findOne($condition = null): ?self
    {
        $query = static::newQuery();

        if ($condition !== null) {
            $query = static::applyCondition($query, $condition);
        }

        return $query->limit(1)->one();
    }

    /**
     * Fast and correct: Find all records with a specific ID value, or all if $id == 0.
     * Returns a ModelCollection (may be empty).
     * Usage: UsersModel::findAll(1)
     */
    public static function findAll($condition = null): ModelCollection
    {
        $query = static::newQuery();

        if ($condition !== null) {
            $query = static::applyCondition($query, $condition);
        }

        return $query->all();
    }

    /**
     * Find record by ID or throw exception
     */
    public static function findOrFail($condition): self
    {
        $record = static::findOne($condition);

        if ($record === null) {
            if (is_array($condition)) {
                $conditionString = json_encode($condition, JSON_UNESCAPED_UNICODE);
            } else {
                $conditionString = (string)$condition;
            }
            throw new \Exception("Record {$conditionString} not found in " . static::class);
        }
        return $record;
    }

    /**
     * Quickly check if any record matches the condition.
     */
    public static function exists($condition = null): bool
    {
        $query = static::newQuery()->select(['COUNT(1) AS aggregate'])->limit(1);
        $query = $condition !== null ? static::applyCondition($query, $condition) : $query;
        $result = $query->asArrays()->one();
        return $result ? (int)$result['aggregate'] > 0 : false;
    }

    /**
     * Count rows for an optional condition.
     */
    public static function count($condition = null): int
    {
        $query = static::newQuery()->select(['COUNT(1) AS aggregate']);
        $query = $condition !== null ? static::applyCondition($query, $condition) : $query;
        $result = $query->asArrays()->one();
        return $result ? (int)$result['aggregate'] : 0;
    }

    /**
     * Apply a flexible condition (id / associative array / operator triplet) to the builder.
     */
    protected static function applyCondition(QueryBuilder $query, $condition): QueryBuilder
    {
        if ($condition instanceof QueryBuilder) {
            return $condition;
        }

        if (is_array($condition)) {
            // Support ['id' => 1, 'status' => 'active'] style arrays
            if (isset($condition['column'], $condition['operator'], $condition['value']) && count($condition) === 3) {
                return $query->where($condition['column'], $condition['operator'], $condition['value']);
            }

            foreach ($condition as $column => $value) {
                $query->where($column, $value);
            }

            return $query;
        }

        // Scalar condition -> primary key lookup
        $primaryKey = static::getPrimaryKey();
        return $query->where($primaryKey, $condition);
    }

    /**
     * Create new record
     * Usage: UsersModel::create(['name' => 'John', 'email' => 'john@example.com'])
     * Optimized: Uses single query, avoids unnecessary find() call
     */
    public static function create(array $attributes): ?self
    {
        $model = new static();
        $db = $model->getDb();

        $fields = [];
        $values = [];

        foreach ($attributes as $key => $value) {
            if ($key !== 'id') {
                $fields[] = $key;
                if ($value === null) {
                    $values[] = "NULL";
                } else {
                    $values[] = "'" . $db->escape($value) . "'";
                }
            }
        }

        if (empty($fields)) {
            return null;
        }

        $sql = "INSERT INTO `{$model->table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $values) . ")";

        if ($db->query($sql)) {
            $id = $db->insert_id();
            // Optimize: Set attributes directly instead of querying again
            $attributes['id'] = $id;
            $model->attributes = $attributes;
            $model->syncAttributesToProperties();
            // Clear query cache for this table
            static::clearQueryCache();
            return $model;
        }

        return null;
    }

    /**
     * Clear query cache for this model's table
     */
    protected static function clearQueryCache(): void
    {
        $model = new static();
        $table = $model->getTable();
        foreach (self::$queryCache as $key => $value) {
            if (strpos($key, $table) !== false) {
                unset(self::$queryCache[$key]);
            }
        }
    }

    /**
     * Update record
     */
    public function update(array $attributes = []): bool
    {
        if (empty($attributes)) {
            $attributes = $this->attributes;
        }

        $db = $this->getDb();
        $id = $this->getAttribute('id');

        if (!$id) {
            return false;
        }

        $updates = [];
        foreach ($attributes as $key => $value) {
            if ($key !== 'id') {
                if ($value === null) {
                    $updates[] = "`{$key}` = NULL";
                } else {
                    $updates[] = "`{$key}` = '" . $db->escape($value) . "'";
                }
                $this->setAttribute($key, $value);
            }
        }
        // Ensure all attributes are synced to properties after update
        $this->syncAttributesToProperties();

        if (empty($updates)) {
            return false;
        }

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $updates) . " WHERE `id` = " . (int)$id;

        return $db->query($sql) !== false;
    }

    /**
     * Delete record
     */
    public function delete(): bool
    {
        $db = $this->getDb();
        $id = $this->getAttribute('id');

        if (!$id) {
            return false;
        }

        $sql = "DELETE FROM `{$this->table}` WHERE `id` = " . (int)$id;

        return $db->query($sql) !== false;
    }

    /**
     * Save record (insert or update)
     */
    public function save(): bool
    {
        // Sync public properties back to attributes before saving
        // This ensures that direct property assignments (e.g., $model->username = 'value')
        // are captured in the attributes array
        $this->syncPropertiesToAttributes();

        $id = $this->getAttribute('id');

        if ($id) {
            return $this->update();
        } else {
            $created = static::create($this->attributes);
            if ($created) {
                $this->attributes = $created->attributes;
                // Sync attributes to public properties after creation
                $this->syncAttributesToProperties();
                return true;
            }
            return false;
        }
    }

    // ============================================
    // RELATIONSHIP METHODS
    // ============================================

    /**
     * Define hasOne relationship
     * Usage: return $this->hasOne(ProfileModel::class, 'user_id');
     */
    protected function hasOne(string $relatedClass, string $foreignKey, string $localKey = 'id'): array
    {
        return [
            'type' => 'hasOne',
            'class' => $relatedClass,
            'foreignKey' => $foreignKey,
            'localKey' => $localKey
        ];
    }

    /**
     * Define hasMany relationship
     * Usage: return $this->hasMany(PostModel::class, 'user_id');
     */
    protected function hasMany(string $relatedClass, string $foreignKey, string $localKey = 'id'): array
    {
        return [
            'type' => 'hasMany',
            'class' => $relatedClass,
            'foreignKey' => $foreignKey,
            'localKey' => $localKey
        ];
    }

    /**
     * Define belongsTo relationship
     * Usage: return $this->belongsTo(UserModel::class, 'user_id');
     */
    protected function belongsTo(string $relatedClass, string $foreignKey, string $localKey = 'id'): array
    {
        return [
            'type' => 'belongsTo',
            'class' => $relatedClass,
            'foreignKey' => $foreignKey,
            'localKey' => $localKey
        ];
    }

    // ============================================
    // AUTO-RELATIONSHIP DETECTION
    // ============================================

    /**
     * Auto-detect relationships from database schema
     * This method analyzes foreign keys and suggests relationships
     */
    public static function autoDetectRelationships(): array
    {
        $model = new static();
        $db = $model->getDb();
        $table = $model->getTable();
        $dbName = $db->db_name;

        $relationships = [];

        // Get all foreign keys for this table
        $sql = "SELECT 
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = '{$dbName}'
                AND TABLE_NAME = '{$table}'
                AND REFERENCED_TABLE_NAME IS NOT NULL";

        $result = $db->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columnName = $row['COLUMN_NAME'];
                $referencedTable = $row['REFERENCED_TABLE_NAME'];
                $referencedColumn = $row['REFERENCED_COLUMN_NAME'];

                // Try to find the model class for referenced table
                $relatedModelClass = static::findModelClass($referencedTable);

                if ($relatedModelClass) {
                    $relationships[] = [
                        'type' => 'belongsTo',
                        'column' => $columnName,
                        'related_table' => $referencedTable,
                        'related_class' => $relatedModelClass,
                        'foreign_key' => $columnName,
                        'local_key' => $referencedColumn
                    ];
                }
            }
        }

        // Get tables that reference this table
        $sql = "SELECT 
                    TABLE_NAME,
                    COLUMN_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = '{$dbName}'
                AND REFERENCED_TABLE_NAME = '{$table}'
                AND REFERENCED_COLUMN_NAME IS NOT NULL";

        $result = $db->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $referencingTable = $row['TABLE_NAME'];
                $columnName = $row['COLUMN_NAME'];
                $referencedColumn = $row['REFERENCED_COLUMN_NAME'];

                // Try to find the model class for referencing table
                $relatedModelClass = static::findModelClass($referencingTable);

                if ($relatedModelClass) {
                    $relationships[] = [
                        'type' => 'hasMany',
                        'column' => $columnName,
                        'related_table' => $referencingTable,
                        'related_class' => $relatedModelClass,
                        'foreign_key' => $columnName,
                        'local_key' => $referencedColumn
                    ];
                }
            }
        }

        return $relationships;
    }

    /**
     * Find model class for a table name
     * Tries to match table name to model class
     */
    protected static function findModelClass(string $tableName): ?string
    {
        // Check cache first
        $cacheKey = $tableName;
        if (isset(self::$relationshipCache[$cacheKey])) {
            return self::$relationshipCache[$cacheKey];
        }

        // Try common naming patterns
        $patterns = [
            $tableName . 'Model',
            ucfirst($tableName) . 'Model',
            ucfirst(rtrim($tableName, 's')) . 'Model', // users -> UserModel
            ucfirst($tableName) . 'sModel', // user -> UsersModel
        ];

        // Search in modules directory
        $modulesPath = dirname(__DIR__, 2) . '/modules';
        if (is_dir($modulesPath)) {
            $modules = glob($modulesPath . '/*', GLOB_ONLYDIR);

            foreach ($modules as $modulePath) {
                $moduleName = basename($modulePath);
                $modelFile = $modulePath . '/Model.php';

                if (file_exists($modelFile)) {
                    $className = "App\\Modules\\{$moduleName}\\Model";

                    if (class_exists($className)) {
                        $tempModel = new $className();
                        if ($tempModel->getTable() === $tableName) {
                            self::$relationshipCache[$cacheKey] = $className;
                            return $className;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Generate relationship methods from auto-detected relationships
     * This can be called to generate relationship code
     */
    public static function generateRelationshipMethods(): string
    {
        $relationships = static::autoDetectRelationships();
        $methods = [];

        foreach ($relationships as $rel) {
            $methodName = static::generateMethodName($rel['related_table'], $rel['type']);
            $relatedClass = $rel['related_class'];

            if ($rel['type'] === 'belongsTo') {
                $methods[] = "    public function {$methodName}()\n    {\n        return \$this->belongsTo({$relatedClass}::class, '{$rel['foreign_key']}');\n    }";
            } elseif ($rel['type'] === 'hasMany') {
                $methods[] = "    public function {$methodName}()\n    {\n        return \$this->hasMany({$relatedClass}::class, '{$rel['foreign_key']}');\n    }";
            }
        }

        return implode("\n\n", $methods);
    }

    /**
     * Generate method name from table name and relationship type
     */
    protected static function generateMethodName(string $tableName, string $type): string
    {
        // Convert table name to method name
        $name = rtrim($tableName, 's'); // Remove plural
        $name = str_replace('_', '', ucwords($name, '_'));
        $name = lcfirst($name);

        return $name;
    }

    /**
     * Validate data using Model Attributes (NestJS style)
     * 
     * @param array $data Data to validate
     * @return static Populated Model instance
     * @throws \Frontend\Palm\Validation\ValidationException
     */
    public static function validate(array $data): static
    {
        // Ensure Validator class is loaded
        if (!class_exists('Frontend\Palm\Validation\Validator')) {
            require_once dirname(__DIR__, 2) . '/app/Palm/Validation/Validator.php';
        }

        // Validate using the Model class itself as the DTO definition
        // The Validator returns an object of the class type
        /** @var static $model */
        $model = \Frontend\Palm\Validation\Validator::validate(static::class, $data);

        // Sync attributes for ActiveRecord compatibility
        // The Validator sets properties directly, but ActiveRecord relies on $attributes array
        $model->syncPropertiesToAttributes();

        return $model;
    }
}

<?php

namespace App\Core;

use App\Database\Db;

/**
 * Query Builder for ActiveRecord-like queries
 * Supports fluent interface: Model::where()->andWhere()->orderBy()->all()
 */
class QueryBuilder
{
    protected Model $model;
    protected Db $db;
    protected string $table;
    protected array $where = [];
    protected array $whereParams = [];
    protected array $bindings = []; // Parameter bindings for prepared statements
    protected array $orderBy = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $select = ['*'];
    protected array $with = []; // For eager loading relationships
    protected ?string $cachedSql = null; // Cache built SQL until query changes
    protected bool $sqlDirty = true; // Flag to rebuild SQL
    protected string $returnFormat = 'object'; // object|array|model

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->db = $model->getDb();
        $this->table = $model->getTable();
    }

    /**
     * Return hydrated model instances (default for ActiveRecord entry points)
     */
    public function asModels(): self
    {
        $this->returnFormat = 'model';
        return $this;
    }

    /**
     * Return stdClass objects (default when using builder directly)
     */
    public function asObjects(): self
    {
        $this->returnFormat = 'object';
        return $this;
    }

    /**
     * Return raw associative arrays
     */
    public function asArrays(): self
    {
        $this->returnFormat = 'array';
        return $this;
    }

    /**
     * Add WHERE condition
     * Optimized: Marks SQL as dirty when query changes
     */
    public function where(string $column, $operator = null, $value = null): self
    {
        // Support: where('id', 1) or where('id', '=', 1) or where(['id' => 1])
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val);
            }
            return $this;
        }

        if ($value === null && $operator !== null) {
            // where('id', 1) -> where id = 1
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = [
            'column' => $column,
            'operator' => $operator ?? '=',
            'value' => $value,
            'logic' => 'AND'
        ];

        $this->sqlDirty = true; // Mark SQL as needing rebuild

        return $this;
    }

    /**
     * Filter method - supports multiple formats for better readability
     * Usage: 
     *   ->filter('name', 'John')
     *   ->filter('age', '>', 20)
     *   ->filter(['name' => 'John', 'status' => 'active'])
     *   ->filter('age', '>20')  // Operator and value combined
     */
    public function filter($column, $operator = null, $value = null): self
    {
        // Support array format: filter(['name' => 'John', 'age' => '>20'])
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                // Check if value contains operator (e.g., '>20', '<=100')
                if (is_string($val) && preg_match('/^(>=|<=|>|<|!=|<>)\s*(.+)$/', $val, $matches)) {
                    $this->filter($key, $matches[1], $matches[2]);
                } else {
                    $this->filter($key, '=', $val);
                }
            }
            return $this;
        }

        // Support combined operator+value: filter('age', '>20')
        if ($value === null && $operator !== null && is_string($operator) && preg_match('/^(>=|<=|>|<|!=|<>)\s*(.+)$/', $operator, $matches)) {
            return $this->where($column, $matches[1], $matches[2]);
        }

        // Standard format: filter('name', 'John') or filter('age', '>', 20)
        return $this->where($column, $operator, $value);
    }

    /**
     * Add AND WHERE condition
     */
    public function andWhere(string $column, $operator = null, $value = null): self
    {
        return $this->where($column, $operator, $value);
    }

    /**
     * Add OR WHERE condition
     * Optimized: Marks SQL as dirty when query changes
     */
    public function orWhere(string $column, $operator = null, $value = null): self
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->orWhere($key, '=', $val);
            }
            return $this;
        }

        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = [
            'column' => $column,
            'operator' => $operator ?? '=',
            'value' => $value,
            'logic' => 'OR'
        ];

        $this->sqlDirty = true; // Mark SQL as needing rebuild

        return $this;
    }

    /**
     * Add WHERE IN condition
     * Optimized: Marks SQL as dirty when query changes
     */
    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            // Empty IN clause - return no results
            $this->where[] = [
                'column' => '1',
                'operator' => '=',
                'value' => '0',
                'logic' => 'AND'
            ];
            $this->sqlDirty = true;
            return $this;
        }

        $escaped = array_map(function ($val) {
            return is_numeric($val) ? (int)$val : "'" . $this->db->escape($val) . "'";
        }, $values);

        $this->where[] = [
            'column' => $column,
            'operator' => 'IN',
            'value' => '(' . implode(', ', $escaped) . ')',
            'logic' => 'AND'
        ];

        $this->sqlDirty = true;

        return $this;
    }

    /**
     * Add WHERE NOT IN condition
     * Optimized: Marks SQL as dirty when query changes
     */
    public function whereNotIn(string $column, array $values): self
    {
        $escaped = array_map(function ($val) {
            return is_numeric($val) ? (int)$val : "'" . $this->db->escape($val) . "'";
        }, $values);

        $this->where[] = [
            'column' => $column,
            'operator' => 'NOT IN',
            'value' => '(' . implode(', ', $escaped) . ')',
            'logic' => 'AND'
        ];

        $this->sqlDirty = true;

        return $this;
    }

    /**
     * Add ORDER BY clause
     * Optimized: Marks SQL as dirty when query changes
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = [
            'column' => $column,
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC'
        ];

        $this->sqlDirty = true;

        return $this;
    }

    /**
     * Add LIMIT clause
     * Optimized: Marks SQL as dirty when query changes
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Add OFFSET clause
     * Optimized: Marks SQL as dirty when query changes
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Set columns to select
     * Optimized: Marks SQL as dirty when query changes
     */
    public function select(array|string $columns): self
    {
        $this->select = is_array($columns) ? $columns : [$columns];
        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Eager load relationships
     */
    public function with(array|string $relations): self
    {
        $this->with = is_array($relations) ? $relations : [$relations];
        return $this;
    }

    /**
     * Search across multiple columns
     * Usage: 
     *   ->search('john', ['name', 'email'])
     *   ->search('john', 'name')  // Single column
     * 
     * Creates: WHERE (name LIKE '%john%' OR email LIKE '%john%')
     */
    public function search(string $term, array|string $columns): self
    {
        if (empty($term)) {
            return $this;
        }

        // Convert single column to array
        $searchColumns = is_array($columns) ? $columns : [$columns];

        if (empty($searchColumns)) {
            return $this;
        }

        $db = $this->db;
        $escapedTerm = $db->escape($term);
        $searchTerm = "%{$escapedTerm}%";

        // Build OR conditions for each column
        $conditions = [];
        foreach ($searchColumns as $column) {
            $conditions[] = "`{$column}` LIKE '{$searchTerm}'";
        }

        // Add as a grouped OR condition
        $this->where[] = [
            'column' => '(' . implode(' OR ', $conditions) . ')',
            'operator' => '',
            'value' => '',
            'logic' => 'AND'
        ];

        $this->sqlDirty = true;

        return $this;
    }

    /**
     * Skip records (alias for offset) - better for pagination readability
     * Usage: ->skip(10)  // Skip first 10 records
     */
    public function skip(int $count): self
    {
        return $this->offset($count);
    }

    /**
     * Get records from a range (for pagination)
     * Usage: 
     *   ->fromTo(0, 10)   // Get records 0-10 (first page, 10 items)
     *   ->fromTo(10, 20)  // Get records 10-20 (second page, 10 items)
     * 
     * This sets offset and limit automatically
     */
    public function fromTo(int $from, int $to): self
    {
        $offset = $from;
        $limit = $to - $from;

        if ($limit < 0) {
            $limit = 0;
        }

        $this->offset($offset);
        $this->limit($limit);

        return $this;
    }

    /**
     * Build WHERE clause SQL with parameter bindings (SECURE)
     * Returns array with SQL and bindings for prepared statements
     */
    protected function buildWhere(): array
    {
        if (empty($this->where)) {
            return ['sql' => '', 'bindings' => []];
        }

        $conditions = [];
        $bindings = [];
        $adapter = $this->db->getAdapter();

        foreach ($this->where as $index => $condition) {
            $column = $condition['column'];
            $operator = $condition['operator'];
            $value = $condition['value'];
            $logic = $index > 0 ? $condition['logic'] : '';
            $isRaw = $condition['raw'] ?? false;

            // Quote column name using adapter
            $quotedColumn = $adapter->quote($column);

            // Handle grouped conditions (from search method)
            if (empty($operator) && empty($value) && strpos($column, '(') === 0) {
                // This is a grouped condition - already contains placeholders/values
                $conditions[] = ($logic ? $logic . ' ' : '') . $column;
            } elseif ($operator === 'IN' || $operator === 'NOT IN') {
                // IN clauses - value is already formatted as (?, ?, ?)
                $conditions[] = ($logic ? $logic . ' ' : '') . "{$quotedColumn} {$operator} {$value}";
            } elseif ($operator === 'BETWEEN' || $operator === 'NOT BETWEEN') {
                // BETWEEN - value is already formatted as ? AND ?
                $conditions[] = ($logic ? $logic . ' ' : '') . "{$quotedColumn} {$operator} {$value}";
            } elseif ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                // NULL checks - no value needed
                $conditions[] = ($logic ? $logic . ' ' : '') . "{$quotedColumn} {$operator}";
            } elseif ($isRaw) {
                // Raw comparison - value is already a quoted column
                $conditions[] = ($logic ? $logic . ' ' : '') . "{$column} {$operator} {$value}";
            } else {
                if ($value === null) {
                    $nullOperator = in_array($operator, ['!=', '<>'], true) ? 'IS NOT NULL' : 'IS NULL';
                    $conditions[] = ($logic ? $logic . ' ' : '') . "{$quotedColumn} {$nullOperator}";
                } else {
                    // Use placeholder for prepared statement
                    $conditions[] = ($logic ? $logic . ' ' : '') . "{$quotedColumn} {$operator} ?";
                    $bindings[] = $value;
                }
            }
        }

        return [
            'sql' => 'WHERE ' . implode(' ', $conditions),
            'bindings' => $bindings
        ];
    }

    /**
     * Build ORDER BY clause SQL
     */
    protected function buildOrderBy(): string
    {
        if (empty($this->orderBy)) {
            return '';
        }

        $orders = [];
        foreach ($this->orderBy as $order) {
            $orders[] = "`{$order['column']}` {$order['direction']}";
        }

        return 'ORDER BY ' . implode(', ', $orders);
    }

    /**
     * Build LIMIT clause SQL
     */
    protected function buildLimit(): string
    {
        if ($this->limit === null) {
            return '';
        }

        $sql = "LIMIT {$this->limit}";
        if ($this->offset !== null) {
            $sql = "LIMIT {$this->offset}, {$this->limit}";
        }

        return $sql;
    }

    /**
     * Build SELECT clause SQL
     */
    protected function buildSelect(): string
    {
        if (in_array('*', $this->select)) {
            return '*';
        }

        return implode(', ', array_map(function ($col) {
            // Don't wrap SQL expressions (functions, AS aliases, etc.) in backticks
            // Check if it contains SQL functions, AS keyword, or other SQL expressions
            if (
                preg_match('/\b(COUNT|SUM|AVG|MAX|MIN|CONCAT|CASE|WHEN|THEN|ELSE|END|AS|DISTINCT)\b/i', $col)
                || preg_match('/[()]/', $col)
            ) {
                return $col;
            }
            return "`{$col}`";
        }, $this->select));
    }


    /**
     * Build complete SQL query with parameter bindings
     * Returns array with 'sql' and 'bindings' for prepared statements
     */
    protected function buildSql(): array
    {
        $adapter = $this->db->getAdapter();
        $select = $this->buildSelect();
        $joins = $this->buildJoins();
        $whereData = $this->buildWhere();
        $groupBy = $this->buildGroupBy();
        $having = $this->buildHaving();
        $orderBy = $this->buildOrderBy();
        $limit = $this->buildLimit();

        // Quote table name using adapter
        $quotedTable = $adapter->quote($this->table);

        $sql = "SELECT {$select} FROM {$quotedTable}";
        if ($joins) $sql .= $joins;
        if ($whereData['sql']) $sql .= ' ' . $whereData['sql'];
        if ($groupBy) $sql .= ' ' . $groupBy;
        if ($having) $sql .= ' ' . $having;
        if ($orderBy) $sql .= ' ' . $orderBy;
        if ($limit) $sql .= ' ' . $limit;

        return [
            'sql' => $sql,
            'bindings' => $whereData['bindings'] ?? []
        ];
    }

    /**
     * Execute query and return all results (SECURE with prepared statements)
     * Returns ModelCollection for model hydration, or array for other formats
     */
    public function all()
    {
        $query = $this->buildSql();
        $result = $this->db->prepare($query['sql'], $query['bindings']);

        $records = [];
        $models = [];

        if ($result) {
            // Optimize: Pre-fetch all rows into array first
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }

            // Batch create instances / objects / arrays
            if ($this->returnFormat === 'model') {
                foreach ($rows as $row) {
                    $models[] = $this->model->newInstance($row);
                }
            } elseif ($this->returnFormat === 'object') {
                foreach ($rows as $row) {
                    $records[] = (object)$row;
                }
            } else {
                $records = $rows;
            }
        }

        if ($this->returnFormat === 'model') {
            if (!empty($this->with) && !empty($models)) {
                $this->loadRelations($models);
            }

            return new ModelCollection($models);
        }

        return $records;
    }

    /**
     * Execute query and return first result
     */
    public function one()
    {
        $this->limit(1);
        $results = $this->all();

        if ($this->returnFormat === 'model') {
            return $results instanceof ModelCollection ? $results->first() : null;
        }

        return $results[0] ?? null;
    }

    /**
     * Execute query and return count
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}`";
        $where = $this->buildWhere();
        if ($where) $sql .= ' ' . $where;

        $result = $this->db->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            return (int)$row['count'];
        }

        return 0;
    }

    /**
     * Check if record exists
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Eager load relationships for records
     */
    protected function loadRelations(array $records): void
    {
        foreach ($this->with as $relationName) {
            if (method_exists($this->model, $relationName)) {
                $relation = $this->model->$relationName();

                if (is_array($relation) && isset($relation['type'], $relation['class'], $relation['foreignKey'])) {
                    $this->loadRelation($records, $relationName, $relation);
                }
            }
        }
    }

    /**
     * Load a specific relationship
     */
    protected function loadRelation(array $records, string $relationName, array $relation): void
    {
        $type = $relation['type'];
        $relatedClass = $relation['class'];
        $foreignKey = $relation['foreignKey'];
        $localKey = $relation['localKey'] ?? 'id';

        // Get all foreign keys from records
        $foreignKeys = array_filter(array_map(function ($record) use ($localKey) {
            return $record->getAttribute($localKey);
        }, $records));

        if (empty($foreignKeys)) {
            return;
        }

        // Load related records
        $relatedRecords = [];
        if ($type === 'hasMany' || $type === 'hasOne') {
            // For hasMany/hasOne: related table has foreignKey pointing to parent's localKey
            $relatedRecords = $relatedClass::whereIn($foreignKey, array_unique($foreignKeys))->all();
        } elseif ($type === 'belongsTo') {
            // For belongsTo: parent has foreignKey pointing to related's localKey
            $relatedLocalKey = $relation['localKey'] ?? 'id';
            $relatedRecords = $relatedClass::whereIn($relatedLocalKey, array_unique($foreignKeys))->all();
        }

        // Map related records to parent records
        $relatedMap = [];
        foreach ($relatedRecords as $related) {
            if ($type === 'belongsTo') {
                // For belongsTo: match on related's localKey
                $relatedLocalKey = $relation['localKey'] ?? 'id';
                $key = $related->getAttribute($relatedLocalKey);
            } else {
                // For hasMany/hasOne: match on related's foreignKey
                $key = $related->getAttribute($foreignKey);
            }

            if ($type === 'hasMany') {
                if (!isset($relatedMap[$key])) {
                    $relatedMap[$key] = [];
                }
                $relatedMap[$key][] = $related;
            } else {
                $relatedMap[$key] = $related;
            }
        }

        // Attach related records to parent records
        foreach ($records as $record) {
            $key = $record->getAttribute($localKey);
            if (isset($relatedMap[$key])) {
                $record->setRelation($relationName, $relatedMap[$key]);
            } else {
                $record->setRelation($relationName, $type === 'hasMany' ? [] : null);
            }
        }
    }

    // ============================================
    // LINQ-STYLE AGGREGATE METHODS
    // ============================================

    /**
     * Get the sum of a column
     * Usage: Product::sum('price')
     */
    public function sum(string $column)
    {
        return $this->aggregate('SUM', $column);
    }

    /**
     * Get the average of a column
     * Usage: Employee::avg('salary')
     */
    public function avg(string $column)
    {
        return $this->aggregate('AVG', $column);
    }

    /**
     * Get the maximum value of a column
     * Usage: Order::max('amount')
     */
    public function max(string $column)
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * Get the minimum value of a column
     * Usage: Order::min('amount')
     */
    public function min(string $column)
    {
        return $this->aggregate('MIN', $column);
    }

    /**
     * Get the SQL query string (for debugging)
     * Returns only SQL without bindings for display purposes
     */
    public function toSql(): string
    {
        $query = $this->buildSql();
        return $query['sql'];
    }

    /**
     * Get the SQL query with bindings (for debugging)
     */
    public function toSqlWithBindings(): array
    {
        return $this->buildSql();
    }

    /**
     * Execute aggregate function (SECURE with prepared statements)
     */
    protected function aggregate(string $function, string $column)
    {
        $adapter = $this->db->getAdapter();
        $quotedTable = $adapter->quote($this->table);
        $quotedColumn = $adapter->quote($column);

        $sql = "SELECT {$function}({$quotedColumn}) as aggregate FROM {$quotedTable}";
        $whereData = $this->buildWhere();

        $bindings = [];
        if ($whereData['sql']) {
            $sql .= ' ' . $whereData['sql'];
            $bindings = $whereData['bindings'];
        }

        $result = $this->db->prepare($sql, $bindings);
        if ($result && $row = $result->fetch_assoc()) {
            return $row['aggregate'];
        }

        return null;
    }

    // ============================================
    // ADVANCED WHERE CONDITIONS
    // ============================================

    /**
     * Add WHERE BETWEEN condition
     * Usage: Product::whereBetween('price', [10, 100])->get()
     */
    public function whereBetween(string $column, array $range): self
    {
        if (count($range) !== 2) {
            throw new \InvalidArgumentException('whereBetween expects an array with exactly 2 elements');
        }

        $min = is_numeric($range[0]) ? $range[0] : "'" . $this->db->escape($range[0]) . "'";
        $max = is_numeric($range[1]) ? $range[1] : "'" . $this->db->escape($range[1]) . "'";

        $this->where[] = [
            'column' => $column,
            'operator' => 'BETWEEN',
            'value' => "{$min} AND {$max}",
            'logic' => 'AND'
        ];

        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Add WHERE NOT BETWEEN condition
     * Usage: Product::whereNotBetween('price', [10, 100])->get()
     */
    public function whereNotBetween(string $column, array $range): self
    {
        if (count($range) !== 2) {
            throw new \InvalidArgumentException('whereNotBetween expects an array with exactly 2 elements');
        }

        $min = is_numeric($range[0]) ? $range[0] : "'" . $this->db->escape($range[0]) . "'";
        $max = is_numeric($range[1]) ? $range[1] : "'" . $this->db->escape($range[1]) . "'";

        $this->where[] = [
            'column' => $column,
            'operator' => 'NOT BETWEEN',
            'value' => "{$min} AND {$max}",
            'logic' => 'AND'
        ];

        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Add WHERE NULL condition
     * Usage: User::whereNull('deleted_at')->get()
     */
    public function whereNull(string $column): self
    {
        $this->where[] = [
            'column' => $column,
            'operator' => 'IS NULL',
            'value' => null,
            'logic' => 'AND'
        ];

        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Add WHERE NOT NULL condition
     * Usage: User::whereNotNull('email')->get()
     */
    public function whereNotNull(string $column): self
    {
        $this->where[] = [
            'column' => $column,
            'operator' => 'IS NOT NULL',
            'value' => null,
            'logic' => 'AND'
        ];

        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Add WHERE DATE condition
     * Usage: Order::whereDate('created_at', '2024-01-15')->get()
     */
    public function whereDate(string $column, string $operator, string $date = null): self
    {
        // Support whereDate('created_at', '2024-01-15') or whereDate('created_at', '>', '2024-01-15')
        if ($date === null) {
            $date = $operator;
            $operator = '=';
        }

        $this->where[] = [
            'column' => "DATE(`{$column}`)",
            'operator' => $operator,
            'value' => "'" . $this->db->escape($date) . "'",
            'logic' => 'AND'
        ];

        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Add WHERE MONTH condition
     * Usage: Order::whereMonth('created_at', 12)->get()
     */
    public function whereMonth(string $column, int $month): self
    {
        $this->where[] = [
            'column' => "MONTH(`{$column}`)",
            'operator' => '=',
            'value' => $month,
            'logic' => 'AND'
        ];

        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Add WHERE YEAR condition
     * Usage: Order::whereYear('created_at', 2024)->get()
     */
    public function whereYear(string $column, int $year): self
    {
        $this->where[] = [
            'column' => "YEAR(`{$column}`)",
            'operator' => '=',
            'value' => $year,
            'logic' => 'AND'
        ];

        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Add WHERE column comparison
     * Usage: User::whereColumn('first_name', '=', 'last_name')->get()
     */
    public function whereColumn(string $column1, string $operator, string $column2): self
    {
        $this->where[] = [
            'column' => "`{$column1}`",
            'operator' => $operator,
            'value' => "`{$column2}`",
            'logic' => 'AND',
            'raw' => true  // Flag to prevent quoting the value
        ];

        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Add raw WHERE condition
     * Usage: User::whereRaw('age > ? AND status = ?', [18, 'active'])->get()
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        // Replace ? with escaped values
        foreach ($bindings as $binding) {
            $escaped = is_numeric($binding) ? $binding : "'" . $this->db->escape($binding) . "'";
            $sql = preg_replace('/\?/', $escaped, $sql, 1);
        }

        $this->where[] = [
            'column' => "({$sql})",
            'operator' => '',
            'value' => '',
            'logic' => 'AND'
        ];

        $this->sqlDirty = true;
        return $this;
    }

    // ============================================
    // JOIN SUPPORT
    // ============================================

    protected array $joins = [];

    /**
     * Add INNER JOIN
     * Usage: Order::join('users', 'orders.user_id', '=', 'users.id')->get()
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Add LEFT JOIN
     * Usage: Order::leftJoin('users', 'orders.user_id', '=', 'users.id')->get()
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add RIGHT JOIN
     * Usage: Order::rightJoin('users', 'orders.user_id', '=', 'users.id')->get()
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Add CROSS JOIN
     * Usage: Product::crossJoin('categories')->get()
     */
    public function crossJoin(string $table): self
    {
        $this->joins[] = [
            'type' => 'CROSS',
            'table' => $table
        ];

        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Build JOIN clause SQL
     */
    protected function buildJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = '';
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN `{$join['table']}`";

            if (isset($join['first'])) {
                $sql .= " ON `{$join['first']}` {$join['operator']} `{$join['second']}`";
            }
        }

        return $sql;
    }

    // ============================================
    // GROUP BY AND HAVING
    // ============================================

    protected array $groupBy = [];
    protected array $having = [];

    /**
     * Add GROUP BY clause
     * Usage: Order::groupBy('user_id')->get()
     */
    public function groupBy(string $column): self
    {
        $this->groupBy[] = $column;
        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Add HAVING clause
     * Usage: Order::groupBy('user_id')->having('COUNT(*)', '>', 5)->get()
     */
    public function having(string $column, string $operator, $value): self
    {
        $this->having[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];

        $this->sqlDirty = true;
        return $this;
    }

    /**
     * Build GROUP BY clause SQL
     */
    protected function buildGroupBy(): string
    {
        if (empty($this->groupBy)) {
            return '';
        }

        $columns = array_map(function ($col) {
            return "`{$col}`";
        }, $this->groupBy);

        return 'GROUP BY ' . implode(', ', $columns);
    }

    /**
     * Build HAVING clause SQL
     */
    protected function buildHaving(): string
    {
        if (empty($this->having)) {
            return '';
        }

        $conditions = [];
        foreach ($this->having as $condition) {
            $value = is_numeric($condition['value']) ? $condition['value'] : "'" . $this->db->escape($condition['value']) . "'";
            $conditions[] = "{$condition['column']} {$condition['operator']} {$value}";
        }

        return 'HAVING ' . implode(' AND ', $conditions);
    }

    // ============================================
    // CHUNK PROCESSING
    // ============================================

    /**
     * Process records in chunks to avoid memory issues
     * Usage: User::chunk(100, function($users) { ... })
     */
    public function chunk(int $size, \Closure $callback): bool
    {
        $page = 1;

        do {
            $results = $this->forPage($page, $size)->all();

            $count = $results instanceof ModelCollection ? $results->count() : count($results);

            if ($count == 0) {
                break;
            }

            // Process chunk
            if ($callback($results) === false) {
                return false;
            }

            $page++;
        } while ($count == $size);

        return true;
    }

    /**
     * Get results for a specific page
     */
    protected function forPage(int $page, int $perPage): self
    {
        return $this->skip(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * Process records in chunks using ID for better performance
     * Usage: User::chunkById(100, function($users) { ... })
     */
    public function chunkById(int $size, \Closure $callback, string $column = 'id'): bool
    {
        $lastId = 0;

        do {
            $clone = clone $this;
            $results = $clone->where($column, '>', $lastId)
                ->orderBy($column, 'ASC')
                ->limit($size)
                ->all();

            $count = $results instanceof ModelCollection ? $results->count() : count($results);

            if ($count == 0) {
                break;
            }

            // Process chunk
            if ($callback($results) === false) {
                return false;
            }

            // Get last ID
            $lastResult = $results instanceof ModelCollection ? $results->last() : end($results);
            $lastId = $lastResult instanceof Model ? $lastResult->getAttribute($column) : $lastResult[$column];
        } while ($count == $size);

        return true;
    }
}

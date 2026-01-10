<?php

namespace App\Database;

use Closure;

/**
 * Blueprint - Table Builder with Fluent API
 * The easiest way to define database tables!
 */
class Blueprint
{
    protected string $table;
    protected string $action; // 'create' or 'alter'
    protected array $columns = [];
    protected array $indexes = [];
    protected array $foreignKeys = [];
    protected string $engine = 'InnoDB';
    protected string $charset = 'utf8mb4';
    protected string $collation = 'utf8mb4_unicode_ci';

    public function __construct(string $table, string $action = 'create')
    {
        $this->table = $table;
        $this->action = $action;
    }

    // ============================================
    // PRIMARY KEY & ID
    // ============================================

    public function id(string $name = 'id'): Column
    {
        return $this->bigInteger($name)->unsigned()->primary()->autoIncrement();
    }

    // ============================================
    // STRING TYPES
    // ============================================

    public function string(string $name, int $length = 255): Column
    {
        return $this->addColumn('VARCHAR', $name, ['length' => $length]);
    }

    public function char(string $name, int $length = 255): Column
    {
        return $this->addColumn('CHAR', $name, ['length' => $length]);
    }

    public function text(string $name): Column
    {
        return $this->addColumn('TEXT', $name);
    }

    public function mediumText(string $name): Column
    {
        return $this->addColumn('MEDIUMTEXT', $name);
    }

    public function longText(string $name): Column
    {
        return $this->addColumn('LONGTEXT', $name);
    }

    public function email(string $name = 'email'): Column
    {
        return $this->string($name)->index();
    }

    public function url(string $name = 'url'): Column
    {
        return $this->string($name);
    }

    // ============================================
    // NUMERIC TYPES
    // ============================================

    public function integer(string $name): Column
    {
        return $this->addColumn('INT', $name);
    }

    public function tinyInteger(string $name): Column
    {
        return $this->addColumn('TINYINT', $name);
    }

    public function smallInteger(string $name): Column
    {
        return $this->addColumn('SMALLINT', $name);
    }

    public function mediumInteger(string $name): Column
    {
        return $this->addColumn('MEDIUMINT', $name);
    }

    public function bigInteger(string $name): Column
    {
        return $this->addColumn('BIGINT', $name);
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): Column
    {
        return $this->addColumn('DECIMAL', $name, compact('precision', 'scale'));
    }

    public function float(string $name, int $precision = 8, int $scale = 2): Column
    {
        return $this->addColumn('FLOAT', $name, compact('precision', 'scale'));
    }

    public function double(string $name, int $precision = 8, int $scale = 2): Column
    {
        return $this->addColumn('DOUBLE', $name, compact('precision', 'scale'));
    }

    // ============================================
    // BOOLEAN
    // ============================================

    public function boolean(string $name): Column
    {
        return $this->addColumn('BOOLEAN', $name);
    }

    // ============================================
    // DATE & TIME
    // ============================================

    public function date(string $name): Column
    {
        return $this->addColumn('DATE', $name);
    }

    public function datetime(string $name): Column
    {
        return $this->addColumn('DATETIME', $name);
    }

    public function timestamp(string $name): Column
    {
        return $this->addColumn('TIMESTAMP', $name);
    }

    public function time(string $name): Column
    {
        return $this->addColumn('TIME', $name);
    }

    public function year(string $name): Column
    {
        return $this->addColumn('YEAR', $name);
    }

    // ============================================
    // JSON & BINARY
    // ============================================

    public function json(string $name): Column
    {
        return $this->addColumn('JSON', $name);
    }

    public function binary(string $name): Column
    {
        return $this->addColumn('BLOB', $name);
    }

    // ============================================
    // ENUM
    // ============================================

    public function enum(string $name, array $values): Column
    {
        return $this->addColumn('ENUM', $name, ['values' => $values]);
    }

    // ============================================
    // FOREIGN KEYS
    // ============================================

    public function foreignId(string $name): Column
    {
        return $this->bigInteger($name)->unsigned()->index();
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $foreign = new ForeignKeyDefinition($column);
        $this->foreignKeys[] = $foreign;
        return $foreign;
    }

    // ============================================
    // INDEXES
    // ============================================

    public function index($columns, string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->table . '_' . implode('_', $columns) . '_index';

        $this->indexes[] = [
            'type' => 'index',
            'name' => $name,
            'columns' => $columns
        ];
    }

    public function unique($columns, string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->table . '_' . implode('_', $columns) . '_unique';

        $this->indexes[] = [
            'type' => 'unique',
            'name' => $name,
            'columns' => $columns
        ];
    }

    // ============================================
    // TABLE OPTIONS
    // ============================================

    public function engine(string $engine): self
    {
        $this->engine = $engine;
        return $this;
    }

    public function charset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    public function collation(string $collation): self
    {
        $this->collation = $collation;
        return $this;
    }

    // ============================================
    // INTERNAL METHODS
    // ============================================

    protected function addColumn(string $type, string $name, array $attributes = []): Column
    {
        $column = new Column($name, $type, $attributes);
        $this->columns[] = $column;
        return $column;
    }

    /**
     * Build and execute the blueprint
     */
    public function build(Db $db): void
    {
        $sql = $this->toSQL($db->getAdapter()->getDriver());

        try {
            $db->query($sql);
        } catch (\Exception $e) {
            throw new \Exception("Migration failed: " . $e->getMessage() . "\nSQL: " . $sql);
        }
    }

    /**
     * Generate SQL for this blueprint
     */
    public function toSQL(string $driver = 'mysql'): string
    {
        if ($this->action === 'create') {
            return $this->generateCreateTableSQL($driver);
        } else {
            return $this->generateAlterTableSQL($driver);
        }
    }

    /**
     * Generate CREATE TABLE SQL
     */
    protected function generateCreateTableSQL(string $driver): string
    {
        $sql = "CREATE TABLE `{$this->table}` (\n";

        // Columns
        $columnDefinitions = [];
        $primaryKeys = [];

        foreach ($this->columns as $column) {
            $columnDefinitions[] = "  " . $column->toSQL($driver);

            if ($column->isPrimary()) {
                $primaryKeys[] = $column->getName();
            }
        }

        $sql .= implode(",\n", $columnDefinitions);

        // Primary key
        if (!empty($primaryKeys)) {
            $sql .= ",\n  PRIMARY KEY (`" . implode('`, `', $primaryKeys) . "`)";
        }

        // Indexes
        foreach ($this->indexes as $index) {
            $columns = '`' . implode('`, `', $index['columns']) . '`';

            if ($index['type'] === 'unique') {
                $sql .= ",\n  UNIQUE KEY `{$index['name']}` ({$columns})";
            } else {
                $sql .= ",\n  KEY `{$index['name']}` ({$columns})";
            }
        }

        // Foreign keys
        foreach ($this->foreignKeys as $foreign) {
            $sql .= ",\n  " . $foreign->toSQL();
        }

        $sql .= "\n)";

        // Table options (MySQL only)
        if ($driver === 'mysql') {
            $sql .= " ENGINE={$this->engine} DEFAULT CHARSET={$this->charset} COLLATE={$this->collation}";
        }

        $sql .= ";";

        return $sql;
    }

    /**
     * Generate ALTER TABLE SQL
     */
    protected function generateAlterTableSQL(string $driver): string
    {
        $statements = [];

        foreach ($this->columns as $column) {
            $statements[] = "ALTER TABLE `{$this->table}` ADD COLUMN " . $column->toSQL($driver) . ";";
        }

        return implode("\n", $statements);
    }

    /**
     * Generate SQL files for all database drivers
     */
    public function generateSQLFiles(): void
    {
        $basePath = dirname(dirname(__DIR__)) . '/database/migrations/sql';

        $drivers = ['mysql', 'pgsql', 'sqlite'];

        foreach ($drivers as $driver) {
            $sql = $this->toSQL($driver);
            $dir = $basePath . '/' . $driver;

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Get migration filename from backtrace
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            $migrationFile = '';

            foreach ($trace as $frame) {
                if (isset($frame['file']) && strpos($frame['file'], 'migrations') !== false) {
                    $migrationFile = basename($frame['file'], '.php');
                    break;
                }
            }

            if (empty($migrationFile)) {
                $migrationFile = date('Y_m_d_His') . '_' . $this->table;
            }

            $filename = $dir . '/' . $migrationFile . '.sql';
            file_put_contents($filename, $sql);
        }
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }
}

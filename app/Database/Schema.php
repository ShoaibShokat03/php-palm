<?php

namespace App\Database;

use Closure;

/**
 * Schema Builder - Fluent API for Database Migrations
 * Easier than Laravel with auto default columns!
 */
class Schema
{
    protected static ?Db $db = null;

    /**
     * Set database connection
     */
    public static function setConnection(Db $db): void
    {
        self::$db = $db;
    }

    /**
     * Get database connection
     */
    protected static function getConnection(): Db
    {
        if (self::$db === null) {
            self::$db = new Db();
        }
        return self::$db;
    }

    /**
     * Create a new table
     */
    public static function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table, 'create');

        // AUTO-ADD DEFAULT COLUMNS AT START
        $blueprint->id();
        $blueprint->boolean('active')->default(true);
        $blueprint->boolean('deleted')->default(false);
        $blueprint->integer('created_by')->nullable();
        $blueprint->integer('updated_by')->nullable();

        // User-defined columns
        $callback($blueprint);

        // AUTO-ADD TIMESTAMPS AT END
        $blueprint->timestamp('created_at')->useCurrent();
        $blueprint->timestamp('updated_at')->useCurrent()->onUpdate();

        // Execute the blueprint
        $blueprint->build(self::getConnection());

        // Generate SQL files
        $blueprint->generateSQLFiles();
    }

    /**
     * Modify an existing table
     */
    public static function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table, 'alter');

        $callback($blueprint);

        $blueprint->build(self::getConnection());
        $blueprint->generateSQLFiles();
    }

    /**
     * Drop a table
     */
    public static function drop(string $table): void
    {
        $db = self::getConnection();
        $adapter = $db->getAdapter();
        $quotedTable = $adapter->quote($table);

        $sql = "DROP TABLE IF EXISTS {$quotedTable}";
        $db->query($sql);
    }

    /**
     * Drop a table if it exists
     */
    public static function dropIfExists(string $table): void
    {
        self::drop($table);
    }

    /**
     * Check if a table exists
     */
    public static function hasTable(string $table): bool
    {
        $db = self::getConnection();
        $driver = $db->getAdapter()->getDriver();

        try {
            if ($driver === 'mysql') {
                $result = $db->query("SHOW TABLES LIKE '{$table}'");
                return $result->num_rows() > 0;
            } elseif ($driver === 'pgsql') {
                $result = $db->query("SELECT FROM information_schema.tables WHERE table_name = '{$table}'");
                return $result->num_rows() > 0;
            } elseif ($driver === 'sqlite') {
                $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
                return $result->num_rows() > 0;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Check if a column exists in a table
     */
    public static function hasColumn(string $table, string $column): bool
    {
        $db = self::getConnection();
        $driver = $db->getAdapter()->getDriver();

        try {
            if ($driver === 'mysql') {
                $result = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
                return $result->num_rows() > 0;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Rename a table
     */
    public static function rename(string $from, string $to): void
    {
        $db = self::getConnection();
        $adapter = $db->getAdapter();

        $quotedFrom = $adapter->quote($from);
        $quotedTo = $adapter->quote($to);

        $sql = "RENAME TABLE {$quotedFrom} TO {$quotedTo}";
        $db->query($sql);
    }
}

<?php

namespace App\Database\Adapters;

/**
 * SQLite Database Adapter
 * Supports SQLite 3+
 */
class SQLiteAdapter implements DatabaseAdapter
{
    public function getDriver(): string
    {
        return 'sqlite';
    }

    public function quote(string $identifier): string
    {
        // SQLite uses backticks or double quotes, we'll use backticks for consistency with MySQL
        return "`{$identifier}`";
    }

    public function buildDSN(array $config): string
    {
        // SQLite uses file path as database
        $database = $config['database'] ?? ':memory:';

        return 'sqlite:' . $database;
    }

    public function getPlaceholder(): string
    {
        return '?';
    }

    public function getDefaultPort(): int
    {
        // SQLite doesn't use ports (file-based)
        return 0;
    }

    public function getSupportedFeatures(): array
    {
        return [
            'transactions',
            'foreign_keys',
            'full_text_search',
            'json_columns',
            'triggers',
            'views',
            'cte', // Common Table Expressions
        ];
    }
}

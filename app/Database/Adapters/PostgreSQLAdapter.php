<?php

namespace App\Database\Adapters;

/**
 * PostgreSQL Database Adapter
 * Supports PostgreSQL 10+
 */
class PostgreSQLAdapter implements DatabaseAdapter
{
    public function getDriver(): string
    {
        return 'pgsql';
    }

    public function quote(string $identifier): string
    {
        // PostgreSQL uses double quotes for identifiers
        return "\"{$identifier}\"";
    }

    public function buildDSN(array $config): string
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $config['host'] ?? 'localhost',
            $config['port'] ?? $this->getDefaultPort(),
            $config['database'] ?? 'postgres'
        );

        // Add options if specified
        if (isset($config['options'])) {
            $dsn .= ';options=' . $config['options'];
        }

        return $dsn;
    }

    public function getPlaceholder(): string
    {
        // PostgreSQL uses ? in PDO (numbered placeholders $1, $2 are native SQL only)
        return '?';
    }

    public function getDefaultPort(): int
    {
        return 5432;
    }

    public function getSupportedFeatures(): array
    {
        return [
            'transactions',
            'foreign_keys',
            'full_text_search',
            'json_columns',
            'jsonb_columns',
            'array_columns',
            'spatial_indexes',
            'stored_procedures',
            'triggers',
            'views',
            'materialized_views',
            'window_functions',
            'cte', // Common Table Expressions
        ];
    }
}

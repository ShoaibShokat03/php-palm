<?php

namespace App\Database\Adapters;

/**
 * Microsoft SQL Server Database Adapter
 * Supports SQL Server 2012+
 */
class SQLServerAdapter implements DatabaseAdapter
{
    public function getDriver(): string
    {
        return 'sqlsrv';
    }

    public function quote(string $identifier): string
    {
        // SQL Server uses square brackets for identifiers
        return "[{$identifier}]";
    }

    public function buildDSN(array $config): string
    {
        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s',
            $config['host'] ?? 'localhost',
            $config['database'] ?? 'master'
        );

        // Add port if specified
        if (isset($config['port']) && $config['port'] != $this->getDefaultPort()) {
            $dsn = sprintf(
                'sqlsrv:Server=%s,%s;Database=%s',
                $config['host'] ?? 'localhost',
                $config['port'],
                $config['database'] ?? 'master'
            );
        }

        return $dsn;
    }

    public function getPlaceholder(): string
    {
        return '?';
    }

    public function getDefaultPort(): int
    {
        return 1433;
    }

    public function getSupportedFeatures(): array
    {
        return [
            'transactions',
            'foreign_keys',
            'full_text_search',
            'json_columns',
            'spatial_indexes',
            'stored_procedures',
            'triggers',
            'views',
            'window_functions',
            'cte', // Common Table Expressions
        ];
    }
}

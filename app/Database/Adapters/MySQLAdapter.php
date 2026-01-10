<?php

namespace App\Database\Adapters;

/**
 * MySQL Database Adapter
 * Supports MySQL 5.7+ and MariaDB 10.2+
 */
class MySQLAdapter implements DatabaseAdapter
{
    public function getDriver(): string
    {
        return 'mysql';
    }

    public function quote(string $identifier): string
    {
        // MySQL uses backticks for identifiers
        return "`{$identifier}`";
    }

    public function buildDSN(array $config): string
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'] ?? 'localhost',
            $config['database'] ?? 'test',
            $config['charset'] ?? 'utf8mb4'
        );

        // Add port if specified
        if (isset($config['port']) && $config['port'] != $this->getDefaultPort()) {
            $dsn .= ';port=' . $config['port'];
        }

        return $dsn;
    }

    public function getPlaceholder(): string
    {
        return '?';
    }

    public function getDefaultPort(): int
    {
        return 3306;
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
        ];
    }
}

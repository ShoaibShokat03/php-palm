<?php

namespace App\Database\Adapters;

/**
 * Database Adapter Interface
 * Provides abstraction for different database systems
 */
interface DatabaseAdapter
{
    /**
     * Get the database driver name
     * @return string Driver name (mysql, pgsql, sqlite, sqlsrv)
     */
    public function getDriver(): string;

    /**
     * Quote an identifier (table name, column name)
     * @param string $identifier The identifier to quote
     * @return string Quoted identifier
     */
    public function quote(string $identifier): string;

    /**
     * Build DSN (Data Source Name) string
     * @param array $config Configuration array
     * @return string DSN string
     */
    public function buildDSN(array $config): string;

    /**
     * Get the parameter placeholder format
     * @return string Placeholder (? for most, $1 for PostgreSQL)
     */
    public function getPlaceholder(): string;

    /**
     * Get the default port for this database
     * @return int Default port number
     */
    public function getDefaultPort(): int;

    /**
     * Get supported features for this database
     * @return array List of features (e.g., 'transactions', 'foreign_keys', 'full_text')
     */
    public function getSupportedFeatures(): array;
}

<?php

namespace App\Database;

use Exception;
use PDO;
use PDOException;

/**
 * Modern PDO database wrapper with transparent APCu-backed query caching.
 * Cached reads live in PHP shared memory (APCu) when available, falling back
 * to an in-process array so the same code runs everywhere. Cache entries are
 * invalidated automatically whenever a table is mutated (INSERT/UPDATE/DELETE).
 */
class Db
{
    private string $host;
    private string $username;
    private string $password;
    private string $database;
    private string $charset;
    private ?PDO $conn = null;
    public string $db_name;

    private bool $apcuAvailable;
    private string $cacheNamespace = 'php_palm_db:';
    private static array $localCache = [];
    private static array $localTableVersions = [];
    private ?string $lastError = null;

    public function __construct()
    {
        $this->host = $_ENV['DATABASE_SERVER_NAME'] ?? 'localhost';
        $this->username = $_ENV['DATABASE_USERNAME'] ?? 'root';
        $this->password = $_ENV['DATABASE_PASSWORD'] ?? ($_ENV['DB_PASSWORD'] ?? '');
        $this->database = $_ENV['DATABASE_NAME'] ?? 'test';
        $this->charset = $_ENV['DATABASE_CHARSET'] ?? 'utf8mb4';
        $this->db_name = $this->database;
        $this->apcuAvailable = function_exists('apcu_enabled') && \call_user_func('apcu_enabled');
    }

    /**
     * Establish a PDO connection if not already connected.
     *
     * @throws Exception
     */
    public function connect(): void
    {
        if ($this->conn !== null) {
            return;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $this->host,
            $this->database,
            $this->charset
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ];

        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getConnection(): PDO
    {
        $this->connect();
        return $this->conn;
    }

    /**
     * Run a SQL query with transparent caching for read operations.
     * Returns DbResult for reads, bool for writes.
     */
    public function query(string $sql)
    {
        $this->connect();

        $command = $this->detectCommand($sql);

        if ($this->isReadCommand($command)) {
            return $this->runReadQuery($sql, $command);
        }

        return $this->runWriteQuery($sql, $command);
    }

    /**
     * Get the last inserted ID.
     */
    public function insert_id(): int
    {
        $this->connect();
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Close the PDO connection.
     */
    public function close(): void
    {
        $this->conn = null;
    }

    /**
     * Return the last error message (if any).
     */
    public function error(): ?string
    {
        return $this->lastError;
    }

    /**
     * Escape a value for use in manual SQL fragments.
     * (Prefer parameter binding when possible.)
     */
    public function escape($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string)$value;
        }

        $this->connect();
        $quoted = $this->conn->quote((string)$value);

        // Strip surrounding quotes because callers wrap it themselves.
        return substr($quoted, 1, -1);
    }

    /**
     * Determine SQL command (SELECT/INSERT/etc).
     */
    protected function detectCommand(string $sql): string
    {
        $trimmed = ltrim($sql);
        $command = strtoupper(strtok($trimmed, " \t\n\r"));
        return $command ?: 'UNKNOWN';
    }

    protected function isReadCommand(string $command): bool
    {
        return in_array($command, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'], true);
    }

    protected function runReadQuery(string $sql, string $command): DbResult
    {
        $tables = $this->extractTablesFromSql($sql, $command);
        $cacheKey = $this->buildCacheKey($sql, $tables);

        $cached = $this->cacheFetch($cacheKey);
        if ($cached !== null) {
            return new DbResult($cached);
        }

        try {
            $stmt = $this->conn->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $this->cacheStore($cacheKey, $rows);
            return new DbResult($rows);
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            throw $e;
        }
    }

    protected function runWriteQuery(string $sql, string $command): bool
    {
        $tables = $this->extractTablesFromSql($sql, $command);

        try {
            $affected = $this->conn->exec($sql);
            $this->invalidateTables($tables);
            return $affected !== false;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Extract tables touched by a SQL statement (best-effort).
     */
    protected function extractTablesFromSql(string $sql, string $command): array
    {
        $tables = [];
        $normalized = strtolower($sql);

        switch ($command) {
            case 'SELECT':
            case 'EXPLAIN':
                if (preg_match_all('/\bfrom\s+`?([a-z0-9_]+)`?/i', $sql, $matches)) {
                    $tables = array_merge($tables, $matches[1]);
                }
                if (preg_match_all('/\bjoin\s+`?([a-z0-9_]+)`?/i', $sql, $matches)) {
                    $tables = array_merge($tables, $matches[1]);
                }
                break;
            case 'SHOW':
            case 'DESCRIBE':
                if (preg_match('/\btable\s+`?([a-z0-9_]+)`?/i', $sql, $match)) {
                    $tables[] = $match[1];
                }
                break;
            case 'INSERT':
                if (preg_match('/\binto\s+`?([a-z0-9_]+)`?/i', $sql, $match)) {
                    $tables[] = $match[1];
                }
                break;
            case 'UPDATE':
                if (preg_match('/\bupdate\s+`?([a-z0-9_]+)`?/i', $sql, $match)) {
                    $tables[] = $match[1];
                }
                break;
            case 'DELETE':
                if (preg_match('/\bfrom\s+`?([a-z0-9_]+)`?/i', $sql, $match)) {
                    $tables[] = $match[1];
                }
                break;
            default:
                // For other commands we can't reliably extract tables.
                break;
        }

        $tables = array_map('strtolower', $tables);
        $tables = array_filter($tables);

        return array_values(array_unique($tables));
    }

    /**
     * Build a cache key that incorporates per-table versions.
     */
    protected function buildCacheKey(string $sql, array $tables): string
    {
        $normalizedSql = preg_replace('/\s+/', ' ', trim($sql));
        $hash = hash('sha256', $normalizedSql);

        if (empty($tables)) {
            $tables = ['__global__'];
        }

        $versionParts = [];
        foreach ($tables as $table) {
            $versionParts[] = $table . ':' . $this->getTableVersion($table);
        }

        return $this->cacheNamespace . $hash . ':' . implode('|', $versionParts);
    }

    protected function cacheFetch(string $key): ?array
    {
        if ($this->apcuAvailable) {
            $success = false;
            $data = $this->apcuFetchValue($key, $success);
            return $success ? $data : null;
        }

        return self::$localCache[$key] ?? null;
    }

    protected function cacheStore(string $key, array $value): void
    {
        if ($this->apcuAvailable) {
            $this->apcuStoreValue($key, $value);
        } else {
            self::$localCache[$key] = $value;
        }
    }

    protected function getTableVersion(string $table): int
    {
        $table = strtolower($table);
        $key = $this->cacheNamespace . 'table_version:' . $table;

        if ($this->apcuAvailable) {
            $success = false;
            $version = $this->apcuFetchValue($key, $success);
            if (!$success) {
                $version = 1;
                $this->apcuStoreValue($key, $version);
            }
            return (int)$version;
        }

        if (!isset(self::$localTableVersions[$key])) {
            self::$localTableVersions[$key] = 1;
        }

        return self::$localTableVersions[$key];
    }

    protected function invalidateTables(array $tables): void
    {
        if (empty($tables)) {
            $tables = ['__global__'];
        }

        foreach ($tables as $table) {
            $table = strtolower($table);
            $key = $this->cacheNamespace . 'table_version:' . $table;

            if ($this->apcuAvailable) {
                if ($this->apcuExists($key)) {
                    $this->apcuIncrement($key);
                } else {
                    $this->apcuStoreValue($key, 2);
                }
            } else {
                if (!isset(self::$localTableVersions[$key])) {
                    self::$localTableVersions[$key] = 2;
                } else {
                    self::$localTableVersions[$key]++;
                }

                // Drop any stale local cache entries referencing this table
                foreach (array_keys(self::$localCache) as $cacheKey) {
                    if (strpos($cacheKey, $table . ':') !== false) {
                        unset(self::$localCache[$cacheKey]);
                    }
                }
            }
        }
    }

    /**
     * Safe wrappers for APCu functions (so static analyzers don't require ext-apcu).
     */
    private function apcuFetchValue(string $key, ?bool &$success = null)
    {
        if (!$this->apcuAvailable || !function_exists('apcu_fetch')) {
            $success = false;
            return null;
        }

        return \call_user_func('apcu_fetch', $key, $success);
    }

    private function apcuStoreValue(string $key, $value): void
    {
        if (!$this->apcuAvailable || !function_exists('apcu_store')) {
            return;
        }

        \call_user_func('apcu_store', $key, $value);
    }

    private function apcuExists(string $key): bool
    {
        return $this->apcuAvailable
            && function_exists('apcu_exists')
            && \call_user_func('apcu_exists', $key);
    }

    private function apcuIncrement(string $key): void
    {
        if (!$this->apcuAvailable || !function_exists('apcu_inc')) {
            return;
        }

        \call_user_func('apcu_inc', $key);
    }
}

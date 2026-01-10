<?php

namespace App\Database;

use App\Database\Adapters\DatabaseAdapter;
use App\Database\Adapters\MySQLAdapter;
use App\Database\Adapters\PostgreSQLAdapter;
use App\Database\Adapters\SQLiteAdapter;
use App\Database\Adapters\SQLServerAdapter;
use Exception;
use PDO;
use PDOException;

/**
 * Modern PDO database wrapper with multi-database support and prepared statements.
 * Supports MySQL, PostgreSQL, SQLite, and SQL Server with secure parameter binding.
 * Features transparent APCu-backed query caching with automatic invalidation.
 */
class Db
{
    private string $driver;
    private string $host;
    private string $username;
    private string $password;
    private string $database;
    private string $charset;
    private ?int $port = null;
    private ?PDO $conn = null;
    public string $db_name;

    private DatabaseAdapter $adapter;
    private bool $apcuAvailable;
    private string $cacheNamespace = 'php_palm_db:';
    private static array $localCache = [];
    private static array $localTableVersions = [];
    private ?string $lastError = null;

    public function __construct()
    {
        // Determ database driver
        $this->driver = strtolower($_ENV['DATABASE_DRIVER'] ?? 'mysql');

        // Initialize appropriate adapter
        $this->adapter = $this->createAdapter();

        // Load configuration
        $this->host = $_ENV['DATABASE_SERVER_NAME'] ?? 'localhost';
        $this->username = $_ENV['DATABASE_USERNAME'] ?? 'root';
        $this->password = $_ENV['DATABASE_PASSWORD'] ?? ($_ENV['DB_PASSWORD'] ?? '');
        $this->database = $_ENV['DATABASE_NAME'] ?? 'test';
        $this->charset = $_ENV['DATABASE_CHARSET'] ?? 'utf8mb4';
        $this->port = isset($_ENV['DATABASE_PORT']) ? (int)$_ENV['DATABASE_PORT'] : null;
        $this->db_name = $this->database;
        $this->apcuAvailable = function_exists('apcu_enabled') && \call_user_func('apcu_enabled');
    }

    /**
     * Create appropriate database adapter based on driver
     */
    protected function createAdapter(): DatabaseAdapter
    {
        switch ($this->driver) {
            case 'mysql':
                return new MySQLAdapter();
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                return new PostgreSQLAdapter();
            case 'sqlite':
            case 'sqlite3':
                return new SQLiteAdapter();
            case 'sqlsrv':
            case 'mssql':
            case 'dblib':
                return new SQLServerAdapter();
            default:
                throw new Exception("Unsupported database driver: {$this->driver}");
        }
    }

    /**
     * Get the database adapter
     */
    public function getAdapter(): DatabaseAdapter
    {
        return $this->adapter;
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

        // Build DSN using adapter
        $dsn = $this->adapter->buildDSN([
            'host' => $this->host,
            'database' => $this->database,
            'charset' => $this->charset,
            'port' => $this->port ?? $this->adapter->getDefaultPort(),
        ]);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => (bool)($_ENV['DATABASE_PERSISTENT'] ?? false),
        ];

        // Add SSL/TLS support for MySQL
        if ($this->driver === 'mysql' && isset($_ENV['DATABASE_SSL_CA'])) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $_ENV['DATABASE_SSL_CA'];
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] =
                (bool)($_ENV['DATABASE_SSL_VERIFY'] ?? true);

            // Optional: SSL cert and key
            if (isset($_ENV['DATABASE_SSL_CERT'])) {
                $options[PDO::MYSQL_ATTR_SSL_CERT] = $_ENV['DATABASE_SSL_CERT'];
            }
            if (isset($_ENV['DATABASE_SSL_KEY'])) {
                $options[PDO::MYSQL_ATTR_SSL_KEY] = $_ENV['DATABASE_SSL_KEY'];
            }
        }

        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new Exception(
                "Database connection failed ({$this->driver}): " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    public function getConnection(): PDO
    {
        $this->connect();
        return $this->conn;
    }

    /**
     * Execute a prepared statement with parameter binding (SECURE)
     * This is the recommended method for all database operations.
     *
     * @param string $sql SQL query with placeholders (?)
     * @param array $bindings Parameter values to bind
     * @return DbResult|bool DbResult for SELECT, bool for INSERT/UPDATE/DELETE
     * @throws PDOException
     */
    public function prepare(string $sql, array $bindings = [])
    {
        $this->connect();

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($bindings);

            // For SELECT queries, return DbResult
            if ($stmt->columnCount() > 0) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return new DbResult($rows);
            }

            // For INSERT/UPDATE/DELETE, return success status
            return true;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Run a SQL query with transparent caching for read operations.
     * Returns DbResult for reads, bool for writes.
     * 
     * @deprecated Use prepare() for queries with user input to prevent SQL injection
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

        // Use enhanced auto-caching if available
        if (class_exists('\App\Core\Cache\AutoCache')) {
            $rows = \App\Core\Cache\AutoCache::cacheQuery($sql, $tables, function () use ($sql) {
                $stmt = $this->conn->query($sql);
                return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            }, 3600);

            return new DbResult($rows);
        }

        // Fallback to original caching
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

        // Use enhanced auto-cache invalidation if available
        if (class_exists('\App\Core\Cache\AutoCache')) {
            foreach ($tables as $table) {
                if ($table !== '__global__') {
                    \App\Core\Cache\AutoCache::invalidateTable($table);
                }
            }
        }

        // Original invalidation logic (for backward compatibility)
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

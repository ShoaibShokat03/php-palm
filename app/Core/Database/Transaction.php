<?php

namespace App\Core\Database;

use App\Database\Db;

/**
 * Database Transaction Manager
 * 
 * Features:
 * - Transaction wrapper with closures
 * - Nested transaction support (savepoints)
 * - Automatic rollback on errors
 * - Deadlock retry with exponential backoff
 * 
 * Usage:
 *   Transaction::run(function() {
 *       User::create(['name' => 'John']);
 *       Order::create(['user_id' => 1]);
 *   });
 */
class Transaction
{
    protected static int $level = 0;
    protected static array $connections = [];
    protected static int $maxRetries = 3;
    protected static ?Db $defaultDb = null;

    /**
     * Retryable MySQL error codes
     */
    protected static array $retryableErrors = [
        1213, // Deadlock found
        1205, // Lock wait timeout
    ];

    /**
     * Get or create default database connection
     */
    protected static function getDefaultDb(): Db
    {
        if (self::$defaultDb === null) {
            self::$defaultDb = new Db();
            self::$defaultDb->connect();
        }
        return self::$defaultDb;
    }

    /**
     * Execute callback within transaction with automatic retry on deadlock
     * This is the preferred way to use transactions
     * 
     * Usage:
     *   $result = Transaction::run(function() {
     *       $user = User::create(['name' => 'John']);
     *       Order::create(['user_id' => $user->id]);
     *       return $user;
     *   });
     * 
     * @param callable $callback Function to execute
     * @param int|null $attempts Max retry attempts (null = use default)
     * @return mixed Result of callback
     * @throws \Throwable If callback throws and all retries exhausted
     */
    public static function run(callable $callback, ?int $attempts = null): mixed
    {
        $attempts = $attempts ?? self::$maxRetries;
        $lastException = null;

        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            self::begin();

            try {
                $result = $callback();
                self::commit();
                return $result;
            } catch (\Throwable $e) {
                self::rollback();
                $lastException = $e;

                // Check if this is a retryable error (deadlock, lock timeout)
                if (self::isRetryable($e) && $currentAttempt < $attempts) {
                    // Exponential backoff: 50ms, 100ms, 200ms, ...
                    $waitMs = 50 * pow(2, $currentAttempt - 1);
                    usleep($waitMs * 1000);
                    continue;
                }

                throw $e;
            }
        }

        throw $lastException ?? new \RuntimeException('Transaction failed after all retry attempts');
    }

    /**
     * Begin transaction
     */
    public static function begin(?Db $db = null): bool
    {
        $db = $db ?? self::getDefaultDb();
        $connection = $db->getConnection();

        if (self::$level === 0) {
            // First level - real transaction
            $result = $connection->beginTransaction();
            if ($result) {
                self::$connections[self::$level] = $db;
                self::$level = 1;
            }
            return $result;
        } else {
            // Nested level - use savepoint
            $savepoint = 'sp_' . self::$level;
            $connection->query("SAVEPOINT {$savepoint}");
            self::$connections[self::$level] = $db;
            self::$level++;
            return true;
        }
    }

    /**
     * Alias for begin()
     */
    public static function beginTransaction(?Db $db = null): bool
    {
        return self::begin($db);
    }

    /**
     * Commit transaction
     */
    public static function commit(): bool
    {
        if (self::$level === 0) {
            return false; // No active transaction
        }

        $db = self::$connections[self::$level - 1];
        $connection = $db->getConnection();

        if (self::$level === 1) {
            // First level - real commit
            $result = $connection->commit();
            if ($result) {
                self::$level = 0;
                self::$connections = [];
            }
            return $result;
        } else {
            // Nested level - release savepoint
            $savepoint = 'sp_' . (self::$level - 1);
            $connection->query("RELEASE SAVEPOINT {$savepoint}");
            self::$level--;
            unset(self::$connections[self::$level]);
            return true;
        }
    }

    /**
     * Rollback transaction
     */
    public static function rollback(): bool
    {
        if (self::$level === 0) {
            return false; // No active transaction
        }

        $db = self::$connections[self::$level - 1];
        $connection = $db->getConnection();

        if (self::$level === 1) {
            // First level - real rollback
            $result = $connection->rollBack();
            self::$level = 0;
            self::$connections = [];
            return $result;
        } else {
            // Nested level - rollback to savepoint
            $savepoint = 'sp_' . (self::$level - 1);
            $connection->query("ROLLBACK TO SAVEPOINT {$savepoint}");
            self::$level--;
            unset(self::$connections[self::$level]);
            return true;
        }
    }

    /**
     * Execute callback within transaction (alias for run)
     * @deprecated Use run() instead
     */
    public static function transaction(callable $callback, ?Db $db = null)
    {
        return self::run($callback);
    }

    /**
     * Check if transaction is active
     */
    public static function isActive(): bool
    {
        return self::$level > 0;
    }

    /**
     * Alias for isActive()
     */
    public static function inTransaction(): bool
    {
        return self::isActive();
    }

    /**
     * Get transaction level (0 = not in transaction, 1+ = nested depth)
     */
    public static function getLevel(): int
    {
        return self::$level;
    }

    /**
     * Alias for getLevel()
     */
    public static function level(): int
    {
        return self::$level;
    }

    /**
     * Set maximum retry attempts for deadlocks
     */
    public static function setMaxRetries(int $retries): void
    {
        self::$maxRetries = max(1, $retries);
    }

    /**
     * Check if an exception is retryable (deadlock, lock timeout)
     */
    protected static function isRetryable(\Throwable $e): bool
    {
        $message = $e->getMessage();

        // Check for specific error codes
        foreach (self::$retryableErrors as $errorCode) {
            if (str_contains($message, (string)$errorCode)) {
                return true;
            }
        }

        // Check for error patterns
        if (stripos($message, 'deadlock') !== false) {
            return true;
        }

        if (stripos($message, 'lock wait timeout') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Reset transaction state (use with caution, mainly for testing)
     */
    public static function reset(): void
    {
        while (self::$level > 0) {
            self::rollback();
        }
        self::$defaultDb = null;
    }
}

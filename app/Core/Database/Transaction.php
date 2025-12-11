<?php

namespace App\Core\Database;

use App\Database\Db;

/**
 * Database Transaction Manager
 * 
 * Features:
 * - Transaction wrapper
 * - Nested transaction support (savepoints)
 * - Automatic rollback on errors
 */
class Transaction
{
    protected static int $level = 0;
    protected static array $connections = [];

    /**
     * Begin transaction
     */
    public static function begin(?Db $db = null): bool
    {
        if ($db === null) {
            $db = new Db();
            $db->connect();
        }

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
            $connection->exec("SAVEPOINT {$savepoint}");
            self::$connections[self::$level] = $db;
            self::$level++;
            return true;
        }
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
            $connection->exec("RELEASE SAVEPOINT {$savepoint}");
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
            $connection->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            self::$level--;
            unset(self::$connections[self::$level]);
            return true;
        }
    }

    /**
     * Execute callback within transaction
     */
    public static function transaction(callable $callback, ?Db $db = null)
    {
        self::begin($db);
        
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
    }

    /**
     * Check if transaction is active
     */
    public static function isActive(): bool
    {
        return self::$level > 0;
    }

    /**
     * Get transaction level
     */
    public static function getLevel(): int
    {
        return self::$level;
    }
}


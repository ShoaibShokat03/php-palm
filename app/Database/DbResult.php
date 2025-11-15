<?php

namespace App\Database;

/**
 * Lightweight result wrapper for cached PDO queries.
 * Provides a mysqli-like API (fetch_assoc/num_rows) so the
 * rest of the codebase can stay unchanged while upgrading Db.
 */
class DbResult
{
    protected array $rows;
    protected int $pointer = 0;
    public int $num_rows = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    /**
     * Fetch next row as associative array.
     */
    public function fetch_assoc(): ?array
    {
        if ($this->pointer >= $this->num_rows) {
            return null;
        }

        return $this->rows[$this->pointer++];
    }

    /**
     * Reset the internal pointer (mainly for debugging/testing).
     */
    public function rewind(): void
    {
        $this->pointer = 0;
    }

    /**
     * Return all rows at once.
     */
    public function all(): array
    {
        return $this->rows;
    }
}


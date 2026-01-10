<?php

namespace App\Database;

/**
 * Foreign Key Definition
 * Fluent API for defining foreign key constraints
 */
class ForeignKeyDefinition
{
    protected string $column;
    protected string $references;
    protected string $on;
    protected string $onDelete = 'RESTRICT';
    protected string $onUpdate = 'RESTRICT';

    public function __construct(string $column)
    {
        $this->column = $column;
    }

    public function references(string $column): self
    {
        $this->references = $column;
        return $this;
    }

    public function on(string $table): self
    {
        $this->on = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    public function cascadeOnDelete(): self
    {
        return $this->onDelete('CASCADE');
    }

    public function nullOnDelete(): self
    {
        return $this->onDelete('SET NULL');
    }

    public function toSQL(): string
    {
        $sql = "FOREIGN KEY (`{$this->column}`) ";
        $sql .= "REFERENCES `{$this->on}` (`{$this->references}`)";

        if ($this->onDelete !== 'RESTRICT') {
            $sql .= " ON DELETE {$this->onDelete}";
        }

        if ($this->onUpdate !== 'RESTRICT') {
            $sql .= " ON UPDATE {$this->onUpdate}";
        }

        return $sql;
    }
}

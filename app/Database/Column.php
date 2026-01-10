<?php

namespace App\Database;

/**
 * Column Definition Class
 * Provides fluent interface for column modifiers
 */
class Column
{
    protected string $name;
    protected string $type;
    protected array $attributes = [];
    protected bool $nullable = false;
    protected $default = null;
    protected bool $unique = false;
    protected bool $index = false;
    protected bool $primary = false;
    protected bool $autoIncrement = false;
    protected bool $unsigned = false;
    protected bool $useCurrent = false;
    protected bool $onUpdate = false;
    protected ?int $length = null;
    protected ?int $precision = null;
    protected ?int $scale = null;

    public function __construct(string $name, string $type, array $attributes = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->attributes = $attributes;

        if (isset($attributes['length'])) {
            $this->length = $attributes['length'];
        }
        if (isset($attributes['precision'])) {
            $this->precision = $attributes['precision'];
        }
        if (isset($attributes['scale'])) {
            $this->scale = $attributes['scale'];
        }
    }

    // Modifiers

    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    public function default($value): self
    {
        $this->default = $value;
        return $this;
    }

    public function unique(): self
    {
        $this->unique = true;
        return $this;
    }

    public function index(): self
    {
        $this->index = true;
        return $this;
    }

    public function primary(): self
    {
        $this->primary = true;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->autoIncrement = true;
        return $this;
    }

    public function unsigned(): self
    {
        $this->unsigned = true;
        return $this;
    }

    public function useCurrent(): self
    {
        $this->useCurrent = true;
        return $this;
    }

    public function onUpdate(): self
    {
        $this->onUpdate = true;
        return $this;
    }

    // Getters

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function isIndex(): bool
    {
        return $this->index;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    public function usesCurrentTimestamp(): bool
    {
        return $this->useCurrent;
    }

    public function hasOnUpdate(): bool
    {
        return $this->onUpdate;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function getPrecision(): ?int
    {
        return $this->precision;
    }

    public function getScale(): ?int
    {
        return $this->scale;
    }

    /**
     * Generate SQL for this column
     */
    public function toSQL(string $driver = 'mysql'): string
    {
        $sql = "`{$this->name}` ";

        // Type with length/precision
        switch ($this->type) {
            case 'VARCHAR':
                $sql .= "VARCHAR(" . ($this->length ?? 255) . ")";
                break;
            case 'CHAR':
                $sql .= "CHAR(" . ($this->length ?? 255) . ")";
                break;
            case 'DECIMAL':
                $sql .= "DECIMAL({$this->precision},{$this->scale})";
                break;
            case 'BIGINT':
                $sql .= "BIGINT";
                if ($this->unsigned) $sql .= " UNSIGNED";
                break;
            case 'INT':
                $sql .= "INT";
                if ($this->unsigned) $sql .= " UNSIGNED";
                break;
            case 'BOOLEAN':
                $sql .= $driver === 'pgsql' ? 'BOOLEAN' : 'TINYINT(1)';
                break;
            default:
                $sql .= $this->type;
        }

        // Nullable/Not Null
        $sql .= $this->nullable ? " NULL" : " NOT NULL";

        // Auto Increment
        if ($this->autoIncrement) {
            $sql .= " AUTO_INCREMENT";
        }

        // Default value
        if ($this->default !== null) {
            if (is_bool($this->default)) {
                $sql .= " DEFAULT " . ($this->default ? '1' : '0');
            } elseif (is_numeric($this->default)) {
                $sql .= " DEFAULT {$this->default}";
            } else {
                $sql .= " DEFAULT '{$this->default}'";
            }
        } elseif ($this->useCurrent) {
            $sql .= " DEFAULT CURRENT_TIMESTAMP";
        }

        // On Update
        if ($this->onUpdate) {
            $sql .= " ON UPDATE CURRENT_TIMESTAMP";
        }

        return $sql;
    }
}

<?php

namespace App\Core\Traits;

/**
 * Has Timestamps Trait for Models
 * 
 * Automatically manages created_at and updated_at columns
 * 
 * Usage:
 *   class User extends Model {
 *       use HasTimestamps;
 *       protected string $table = 'users';
 *   }
 * 
 * @package PhpPalm\ORM
 */
trait HasTimestamps
{
    /**
     * Indicates if the model should be timestamped
     */
    protected bool $timestamps = true;

    /**
     * The name of the "created at" column
     */
    protected string $createdAtColumn = 'created_at';

    /**
     * The name of the "updated at" column
     */
    protected string $updatedAtColumn = 'updated_at';

    /**
     * Check if timestamps are enabled
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * Get the name of the "created at" column
     */
    public function getCreatedAtColumn(): string
    {
        return $this->createdAtColumn;
    }

    /**
     * Get the name of the "updated at" column
     */
    public function getUpdatedAtColumn(): string
    {
        return $this->updatedAtColumn;
    }

    /**
     * Set the timestamps on the model
     */
    public function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Update the creation and update timestamps
     */
    public function updateTimestamps(): void
    {
        if (!$this->usesTimestamps()) {
            return;
        }

        $time = $this->freshTimestamp();

        // Always update updated_at
        if ($this->updatedAtColumn) {
            $this->setAttribute($this->updatedAtColumn, $time);
        }

        // Only set created_at if this is a new record (no ID)
        if ($this->createdAtColumn && !$this->getAttribute('id')) {
            if (!$this->getAttribute($this->createdAtColumn)) {
                $this->setAttribute($this->createdAtColumn, $time);
            }
        }
    }

    /**
     * Disable timestamps for a specific save operation
     * Usage: $model->withoutTimestamps()->save();
     */
    public function withoutTimestamps(): static
    {
        $this->timestamps = false;
        return $this;
    }

    /**
     * Enable timestamps (if they were disabled)
     */
    public function withTimestamps(): static
    {
        $this->timestamps = true;
        return $this;
    }

    /**
     * Touch the model (update updated_at to current time)
     */
    public function touch(): bool
    {
        if (!$this->usesTimestamps()) {
            return false;
        }

        $this->setAttribute($this->updatedAtColumn, $this->freshTimestamp());
        return $this->save();
    }

    /**
     * Get the created_at timestamp
     */
    public function getCreatedAt(): ?string
    {
        return $this->getAttribute($this->createdAtColumn);
    }

    /**
     * Get the updated_at timestamp
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getAttribute($this->updatedAtColumn);
    }
}

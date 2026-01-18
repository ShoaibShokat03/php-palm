<?php

namespace App\Core\Traits;

/**
 * Soft Deletes Trait for Models
 * 
 * Adds soft delete functionality - records are marked as deleted
 * instead of being permanently removed from the database.
 * 
 * Usage:
 *   class User extends Model {
 *       use SoftDeletes;
 *       protected string $table = 'users';
 *   }
 * 
 * Then:
 *   $user->delete();        // Sets deleted_at, doesn't remove row
 *   $user->forceDelete();   // Permanently removes row
 *   $user->restore();       // Clears deleted_at
 *   User::withTrashed()->all();   // Include soft-deleted
 *   User::onlyTrashed()->all();   // Only soft-deleted
 * 
 * @package PhpPalm\ORM
 */
trait SoftDeletes
{
    /**
     * Column name for soft delete timestamp
     */
    protected string $deletedAtColumn = 'deleted_at';

    /**
     * Indicates if the model is currently force deleting
     */
    protected bool $forceDeleting = false;

    /**
     * Boot the soft delete trait
     * Called automatically when model is instantiated
     */
    protected function bootSoftDeletes(): void
    {
        // Add global scope to exclude soft-deleted records by default
        static::addGlobalScope('soft_delete', function ($query) {
            $query->whereNull($this->getDeletedAtColumn());
        });
    }

    /**
     * Get the name of the "deleted at" column
     */
    public function getDeletedAtColumn(): string
    {
        return $this->deletedAtColumn;
    }

    /**
     * Get the fully qualified column name for "deleted at"
     */
    public function getQualifiedDeletedAtColumn(): string
    {
        return $this->getTable() . '.' . $this->getDeletedAtColumn();
    }

    /**
     * Check if the model is currently being force deleted
     */
    public function isForceDeleting(): bool
    {
        return $this->forceDeleting;
    }

    /**
     * Check if this model uses soft deletes
     */
    public function usesSoftDeletes(): bool
    {
        return true;
    }

    /**
     * Check if the model has been soft deleted
     */
    public function trashed(): bool
    {
        $deletedAt = $this->getAttribute($this->getDeletedAtColumn());
        return $deletedAt !== null && $deletedAt !== '';
    }

    /**
     * Soft delete the model (set deleted_at timestamp)
     */
    public function delete(): bool
    {
        if ($this->forceDeleting) {
            return $this->forceDelete();
        }

        // Fire deleting event if method exists
        if (method_exists($this, 'fireModelEvent')) {
            if ($this->fireModelEvent('deleting') === false) {
                return false;
            }
        }

        $time = date('Y-m-d H:i:s');
        $this->setAttribute($this->getDeletedAtColumn(), $time);

        $result = $this->update([
            $this->getDeletedAtColumn() => $time
        ]);

        // Fire deleted event
        if ($result && method_exists($this, 'fireModelEvent')) {
            $this->fireModelEvent('deleted');
        }

        return $result;
    }

    /**
     * Permanently delete the model from the database
     */
    public function forceDelete(): bool
    {
        $this->forceDeleting = true;

        // Fire force deleting event
        if (method_exists($this, 'fireModelEvent')) {
            if ($this->fireModelEvent('forceDeleting') === false) {
                $this->forceDeleting = false;
                return false;
            }
        }

        $db = $this->getDb();
        $id = $this->getAttribute('id');

        if (!$id) {
            $this->forceDeleting = false;
            return false;
        }

        $sql = "DELETE FROM `{$this->table}` WHERE `id` = " . (int)$id;
        $result = $db->query($sql) !== false;

        // Fire force deleted event
        if ($result && method_exists($this, 'fireModelEvent')) {
            $this->fireModelEvent('forceDeleted');
        }

        $this->forceDeleting = false;
        return $result;
    }

    /**
     * Restore a soft-deleted model
     */
    public function restore(): bool
    {
        // Fire restoring event
        if (method_exists($this, 'fireModelEvent')) {
            if ($this->fireModelEvent('restoring') === false) {
                return false;
            }
        }

        $this->setAttribute($this->getDeletedAtColumn(), null);

        $result = $this->update([
            $this->getDeletedAtColumn() => null
        ]);

        // Fire restored event
        if ($result && method_exists($this, 'fireModelEvent')) {
            $this->fireModelEvent('restored');
        }

        return $result;
    }

    /**
     * Include soft-deleted records in the query
     * 
     * @return \App\Core\QueryBuilder
     */
    public static function withTrashed()
    {
        return static::newQueryWithoutScope('soft_delete');
    }

    /**
     * Only get soft-deleted records
     * 
     * @return \App\Core\QueryBuilder
     */
    public static function onlyTrashed()
    {
        $instance = new static();
        return static::newQueryWithoutScope('soft_delete')
            ->whereNotNull($instance->getDeletedAtColumn());
    }

    /**
     * Get a new query without a specific global scope
     * 
     * @param string $scope Scope name to exclude
     * @return \App\Core\QueryBuilder
     */
    protected static function newQueryWithoutScope(string $scope)
    {
        // Create query without the soft delete scope
        $query = static::newQuery();

        // Since we're creating a fresh query, we need to bypass the scope
        // This is done by not applying the whereNull condition
        return $query;
    }
}

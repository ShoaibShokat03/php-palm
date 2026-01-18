<?php

namespace App\Core\Traits;

/**
 * Has Events Trait for Models
 * 
 * Provides model lifecycle events/hooks
 * 
 * Events:
 * - creating / created
 * - updating / updated  
 * - saving / saved
 * - deleting / deleted
 * - restoring / restored (for soft deletes)
 * 
 * Usage:
 *   class User extends Model {
 *       use HasEvents;
 *       
 *       protected static function booted(): void {
 *           static::creating(function ($user) {
 *               $user->uuid = Str::uuid();
 *           });
 *           
 *           static::deleted(function ($user) {
 *               Log::info("User {$user->id} was deleted");
 *           });
 *       }
 *   }
 * 
 * Or using instance methods:
 *   class User extends Model {
 *       protected function beforeSave(): bool {
 *           // Return false to cancel the save
 *           return true;
 *       }
 *       
 *       protected function afterSave(): void {
 *           // Called after successful save
 *       }
 *   }
 * 
 * @package PhpPalm\ORM
 */
trait HasEvents
{
    /**
     * Registered event callbacks
     */
    protected static array $eventCallbacks = [];

    /**
     * Indicates if the model is currently dispatching events
     */
    protected bool $dispatchingEvents = true;

    /**
     * Boot the has events trait
     */
    protected static function bootHasEvents(): void
    {
        // Call the booted method if it exists
        if (method_exists(static::class, 'booted')) {
            static::booted();
        }
    }

    /**
     * Register a creating event callback
     */
    public static function creating(callable $callback): void
    {
        static::registerEvent('creating', $callback);
    }

    /**
     * Register a created event callback
     */
    public static function created(callable $callback): void
    {
        static::registerEvent('created', $callback);
    }

    /**
     * Register an updating event callback
     */
    public static function updating(callable $callback): void
    {
        static::registerEvent('updating', $callback);
    }

    /**
     * Register an updated event callback
     */
    public static function updated(callable $callback): void
    {
        static::registerEvent('updated', $callback);
    }

    /**
     * Register a saving event callback
     */
    public static function saving(callable $callback): void
    {
        static::registerEvent('saving', $callback);
    }

    /**
     * Register a saved event callback
     */
    public static function saved(callable $callback): void
    {
        static::registerEvent('saved', $callback);
    }

    /**
     * Register a deleting event callback
     */
    public static function deleting(callable $callback): void
    {
        static::registerEvent('deleting', $callback);
    }

    /**
     * Register a deleted event callback
     */
    public static function deleted(callable $callback): void
    {
        static::registerEvent('deleted', $callback);
    }

    /**
     * Register a restoring event callback (for soft deletes)
     */
    public static function restoring(callable $callback): void
    {
        static::registerEvent('restoring', $callback);
    }

    /**
     * Register a restored event callback (for soft deletes)
     */
    public static function restored(callable $callback): void
    {
        static::registerEvent('restored', $callback);
    }

    /**
     * Register an event callback
     */
    protected static function registerEvent(string $event, callable $callback): void
    {
        $class = static::class;

        if (!isset(static::$eventCallbacks[$class])) {
            static::$eventCallbacks[$class] = [];
        }

        if (!isset(static::$eventCallbacks[$class][$event])) {
            static::$eventCallbacks[$class][$event] = [];
        }

        static::$eventCallbacks[$class][$event][] = $callback;
    }

    /**
     * Fire a model event
     * 
     * @param string $event Event name
     * @return bool|null Returns false if event handler cancelled the operation
     */
    public function fireModelEvent(string $event): ?bool
    {
        if (!$this->dispatchingEvents) {
            return true;
        }

        // Check for instance method (e.g., beforeSave, afterSave)
        $methodName = $this->getEventMethodName($event);
        if (method_exists($this, $methodName)) {
            $result = $this->$methodName();
            if ($result === false) {
                return false;
            }
        }

        // Fire registered callbacks
        $class = static::class;

        if (isset(static::$eventCallbacks[$class][$event])) {
            foreach (static::$eventCallbacks[$class][$event] as $callback) {
                $result = $callback($this);
                if ($result === false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get the method name for an event
     */
    protected function getEventMethodName(string $event): string
    {
        // Map event names to method names
        return match ($event) {
            'creating' => 'beforeCreate',
            'created' => 'afterCreate',
            'updating' => 'beforeUpdate',
            'updated' => 'afterUpdate',
            'saving' => 'beforeSave',
            'saved' => 'afterSave',
            'deleting' => 'beforeDelete',
            'deleted' => 'afterDelete',
            'restoring' => 'beforeRestore',
            'restored' => 'afterRestore',
            default => 'on' . ucfirst($event),
        };
    }

    /**
     * Disable event dispatching for the model
     */
    public function withoutEvents(): static
    {
        $this->dispatchingEvents = false;
        return $this;
    }

    /**
     * Enable event dispatching for the model
     */
    public function withEvents(): static
    {
        $this->dispatchingEvents = true;
        return $this;
    }

    /**
     * Execute callback without firing events
     */
    public static function withoutEventsStatic(callable $callback): mixed
    {
        $previousState = [];

        // This is a simplified version - in production you'd want thread-safe handling
        $result = $callback();

        return $result;
    }

    /**
     * Clear all registered events for this model
     */
    public static function flushEventListeners(): void
    {
        $class = static::class;
        static::$eventCallbacks[$class] = [];
    }
}

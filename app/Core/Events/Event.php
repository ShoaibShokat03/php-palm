<?php

namespace App\Core\Events;

/**
 * Event System
 * 
 * Features:
 * - Event-driven triggers
 * - Event listeners
 * - Event dispatching
 */
class Event
{
    protected static array $listeners = [];
    protected static array $fired = [];

    /**
     * Register event listener
     */
    public static function listen(string $event, callable $listener, int $priority = 0): void
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }

        self::$listeners[$event][] = [
            'listener' => $listener,
            'priority' => $priority
        ];

        // Sort by priority (higher first)
        usort(self::$listeners[$event], function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
    }

    /**
     * Fire event
     */
    public static function fire(string $event, $payload = null): array
    {
        $results = [];
        
        if (!isset(self::$listeners[$event])) {
            return $results;
        }

        foreach (self::$listeners[$event] as $handler) {
            try {
                $result = call_user_func($handler['listener'], $payload);
                $results[] = $result;
            } catch (\Throwable $e) {
                \App\Core\Logger::errorStatic('Event listener error', [
                    'event' => $event,
                    'error' => $e->getMessage()
                ]);
            }
        }

        self::$fired[$event] = time();
        return $results;
    }

    /**
     * Check if event has listeners
     */
    public static function hasListeners(string $event): bool
    {
        return isset(self::$listeners[$event]) && !empty(self::$listeners[$event]);
    }

    /**
     * Get all listeners for event
     */
    public static function getListeners(string $event): array
    {
        return self::$listeners[$event] ?? [];
    }

    /**
     * Remove listener
     */
    public static function forget(string $event): void
    {
        unset(self::$listeners[$event]);
    }

    /**
     * Clear all listeners
     */
    public static function clear(): void
    {
        self::$listeners = [];
        self::$fired = [];
    }
}


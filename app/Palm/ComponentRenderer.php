<?php

namespace Frontend\Palm;

/**
 * Component Renderer
 * 
 * Handles component rendering and registration
 */
class ComponentRenderer
{
    protected static array $components = [];
    protected static string $componentNamespace = 'Frontend\\Components';
    protected static bool $initialized = false;

    /**
     * Initialize built-in components
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        // Register built-in components
        self::register('Alert', \Frontend\Palm\Components\Alert::class);
        self::register('Card', \Frontend\Palm\Components\Card::class);
        self::register('Button', \Frontend\Palm\Components\Button::class);
        self::register('Badge', \Frontend\Palm\Components\Badge::class);
        self::register('Spinner', \Frontend\Palm\Components\Spinner::class);
        self::register('Modal', \Frontend\Palm\Components\Modal::class);
        
        self::$initialized = true;
    }

    /**
     * Register component
     */
    public static function register(string $name, string $componentClass): void
    {
        self::$components[$name] = $componentClass;
    }

    /**
     * Render component
     */
    public static function render(string $name, array $props = [], array $slots = []): string
    {
        $component = self::createComponent($name, $props, $slots);
        return $component->render();
    }

    /**
     * Create component instance
     */
    protected static function createComponent(string $name, array $props = [], array $slots = []): Component
    {
        // Check if component is registered
        if (isset(self::$components[$name])) {
            $className = self::$components[$name];
        } else {
            // Try to auto-resolve component class
            $className = self::resolveComponentClass($name);
        }

        if (!class_exists($className)) {
            throw new \RuntimeException("Component '{$name}' not found. Tried: {$className}");
        }

        if (!is_subclass_of($className, Component::class)) {
            throw new \RuntimeException("Component '{$name}' must extend " . Component::class);
        }

        $component = new $className($props);
        
        // Set slots
        foreach ($slots as $slotName => $slotContent) {
            $component->setSlot($slotName, $slotContent);
        }

        return $component;
    }

    /**
     * Resolve component class name
     */
    protected static function resolveComponentClass(string $name): string
    {
        // Convert kebab-case to PascalCase
        $parts = explode('-', $name);
        $className = implode('', array_map('ucfirst', $parts));
        
        // Try in Frontend\Palm\Components namespace first (built-in components)
        $builtInClass = 'Frontend\\Palm\\Components\\' . $className;
        if (class_exists($builtInClass)) {
            return $builtInClass;
        }
        
        // Then try in Frontend\Components namespace
        return self::$componentNamespace . '\\' . $className;
    }

    /**
     * Get all registered components
     */
    public static function all(): array
    {
        return self::$components;
    }
}


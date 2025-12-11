<?php

namespace Frontend\Palm;

/**
 * Base Component Class
 * 
 * Provides a foundation for reusable UI components
 */
abstract class Component
{
    protected array $props = [];
    protected array $slots = [];
    protected ?string $viewPath = null;

    /**
     * Create component instance
     */
    public function __construct(array $props = [])
    {
        $this->props = $props;
    }

    /**
     * Get component property
     */
    protected function prop(string $key, mixed $default = null): mixed
    {
        return $this->props[$key] ?? $default;
    }

    /**
     * Get all props
     */
    protected function props(): array
    {
        return $this->props;
    }

    /**
     * Set slot content
     */
    public function setSlot(string $name, string $content): void
    {
        $this->slots[$name] = $content;
    }

    /**
     * Get slot content
     */
    protected function slot(string $name, string $default = ''): string
    {
        return $this->slots[$name] ?? $default;
    }

    /**
     * Get default slot content
     */
    protected function defaultSlot(): string
    {
        return $this->slots['default'] ?? '';
    }

    /**
     * Render component
     */
    public function render(): string
    {
        // Try to find component view
        $viewPath = $this->getViewPath();
        
        if ($viewPath && file_exists($viewPath)) {
            return $this->renderView($viewPath);
        }

        // Fallback to render method
        return $this->renderComponent();
    }

    /**
     * Get component view path
     */
    protected function getViewPath(): ?string
    {
        if ($this->viewPath) {
            return $this->viewPath;
        }

        // Auto-detect view path based on class name
        $className = get_class($this);
        $className = str_replace('Frontend\\Components\\', '', $className);
        $className = str_replace('\\', '/', $className);
        
        // Try in src/components/views/
        $basePath = defined('PALM_ROOT') ? PALM_ROOT . '/src/components/views' : __DIR__ . '/../../src/components/views';
        $viewFile = $basePath . '/' . $className . '.php';
        
        if (file_exists($viewFile)) {
            return $viewFile;
        }

        // Try in src/views/components/
        $viewFile = $basePath . '/../views/components/' . basename($className) . '.php';
        if (file_exists($viewFile)) {
            return $viewFile;
        }

        return null;
    }

    /**
     * Render component view
     */
    protected function renderView(string $viewPath): string
    {
        extract($this->props);
        $slots = $this->slots;
        
        ob_start();
        require $viewPath;
        return ob_get_clean();
    }

    /**
     * Render component (override in subclasses)
     */
    abstract protected function renderComponent(): string;

    /**
     * Convert component to string
     */
    public function __toString(): string
    {
        return $this->render();
    }
}


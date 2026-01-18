<?php

namespace Frontend\Palm;

/**
 * Base Component Class
 */
abstract class Component
{
    protected array $props;
    protected array $slots = [];

    public function __construct(array $props = [])
    {
        $this->props = $props;
    }

    public function setSlot(string $name, mixed $content): void
    {
        $this->slots[$name] = $content;
    }

    protected function prop(string $key, mixed $default = null): mixed
    {
        return $this->props[$key] ?? $default;
    }

    protected function slot(string $name, mixed $default = null): mixed
    {
        return $this->slots[$name] ?? $default;
    }

    protected function defaultSlot(mixed $default = null): mixed
    {
        return $this->slot('default', $default);
    }

    /**
     * Render the component logic/template
     */
    abstract protected function renderComponent(): string;

    /**
     * Public render method called by renderer
     */
    public function render(): string
    {
        return $this->renderComponent();
    }
}

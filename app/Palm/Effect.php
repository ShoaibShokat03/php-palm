<?php

namespace Frontend\Palm;

/**
 * Effect primitive for side effects with reactive dependencies
 * 
 * Usage:
 *   Effect(function() use ($counter) {
 *       console_log('Counter changed:', $counter->get());
 *   });
 * 
 * Effects run automatically when dependencies change
 */
class Effect
{
    protected ComponentContext $context;
    protected string $effectId;
    /** @var callable */
    protected $callback;
    protected array $dependencies = [];

    public function __construct(ComponentContext $context, string $effectId, callable $callback)
    {
        $this->context = $context;
        $this->effectId = $effectId;
        $this->callback = $callback;
    }

    public function getEffectId(): string
    {
        return $this->effectId;
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }

    public function setDependencies(array $dependencies): void
    {
        $this->dependencies = $dependencies;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function execute(): void
    {
        ($this->callback)();
    }
}


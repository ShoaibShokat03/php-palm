<?php

namespace Frontend\Palm;

class ComponentContext
{
    protected string $id;
    /** @var StateSlot[] */
    protected array $states = [];
    protected array $actions = [];
    protected ?string $recordingAction = null;
    protected array $currentOperations = [];

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function createState(mixed $initial = null, bool $global = false, ?string $globalKey = null): StateSlot
    {
        $slotId = 's' . count($this->states);
        $slot = new StateSlot($this, $slotId, $initial, $global, $globalKey);
        $this->states[$slotId] = $slot;
        return $slot;
    }

    public function createGlobalState(string $key, mixed $initial = null): StateSlot
    {
        $normalizedKey = trim($key);
        if ($normalizedKey === '') {
            throw new \InvalidArgumentException('PalmState key cannot be empty');
        }

        return $this->createState($initial, true, $normalizedKey);
    }

    public function hasInteractiveState(): bool
    {
        return !empty($this->states);
    }

    public function isRecording(): bool
    {
        return $this->recordingAction !== null;
    }

    public function recordOperation(array $operation): void
    {
        if ($this->recordingAction === null) {
            return;
        }

        $this->currentOperations[] = $operation;
    }

    public function registerAction(string $name, callable $callback): void
    {
        if (isset($this->actions[$name])) {
            return;
        }

        if ($callback instanceof \Closure) {
            $callback = ActionRewriter::rewrite($callback);
        }

        if (\is_array($callback)) {
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
            $argCount = $reflection->getNumberOfParameters();
            $args = [];
            for ($i = 0; $i < $argCount; $i++) {
                $args[] = new ActionArgument($i);
            }
            $this->recordingAction = $name;
            $this->currentOperations = [];
            $reflection->invokeArgs($callback[0], $args);
            $this->actions[$name] = $this->currentOperations;
            $this->recordingAction = null;
            $this->currentOperations = [];
            return;
        }

        $reflection = new \ReflectionFunction($callback);
        $argCount = $reflection->getNumberOfParameters();
        $args = [];
        for ($i = 0; $i < $argCount; $i++) {
            $args[] = new ActionArgument($i);
        }

        $this->recordingAction = $name;
        $this->currentOperations = [];
        $reflection->invokeArgs($args);
        $this->actions[$name] = $this->currentOperations;
        $this->recordingAction = null;
        $this->currentOperations = [];
    }

    public function finalizeHtml(string $html): string
    {
        if (!$this->hasInteractiveState()) {
            return $html;
        }

        $html = preg_replace_callback('/onclick="([^"]+)"/i', function ($matches) {
            $expression = trim($matches[1]);
            $action = $expression;
            $args = '';

            if (preg_match('/^([a-zA-Z0-9_]+)\s*\((.*)\)\s*$/', $expression, $parts)) {
                $action = $parts[1];
                $args = trim($parts[2]);
            } elseif (substr($expression, -2) === '()') {
                $action = substr($expression, 0, -2);
            }

            if (!isset($this->actions[$action])) {
                return $matches[0];
            }

            $safe = htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $attributes = 'data-palm-action="' . $safe . '" data-palm-component="' . $this->id . '"';
            if ($args !== '') {
                $attributes .= ' data-palm-args="' . htmlspecialchars($args, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
            return $attributes;
        }, $html);

        return '<div data-palm-component="' . $this->id . '">' . $html . '</div>';
    }

    public function buildPayload(): ?array
    {
        if (!$this->hasInteractiveState()) {
            return null;
        }

        $statePayload = [];
        foreach ($this->states as $slot) {
            $statePayload[] = [
                'id' => $slot->getSlotId(),
                'value' => $slot->getValue(),
                'global' => $slot->isGlobal(),
                'key' => $slot->getGlobalKey(),
            ];
        }

        return [
            'id' => $this->id,
            'states' => $statePayload,
            'actions' => $this->actions,
        ];
    }
}


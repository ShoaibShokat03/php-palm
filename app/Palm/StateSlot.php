<?php

namespace Frontend\Palm;

class StateSlot
{
    protected ComponentContext $context;
    protected string $slotId;
    protected mixed $value;
    protected bool $global;
    protected ?string $globalKey;

    public function __construct(ComponentContext $context, string $slotId, mixed $initial = null, bool $global = false, ?string $globalKey = null)
    {
        $this->context = $context;
        $this->slotId = $slotId;
        $this->value = $initial;
        $this->global = $global;
        $this->globalKey = $global ? ($globalKey ?? $slotId) : null;
    }

    public function getSlotId(): string
    {
        return $this->slotId;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isGlobal(): bool
    {
        return $this->global;
    }

    public function getGlobalKey(): ?string
    {
        return $this->globalKey;
    }

    public function __invoke(mixed $value = null): mixed
    {
        if (func_num_args() === 0) {
            return $this->get();
        }

        $this->set($value);
        return $this->value;
    }

    protected function normalizeRecordedValue(mixed $value): mixed
    {
        if ($value instanceof self) {
            $value = $value->get();
        }

        if ($value instanceof ActionArgument) {
            return [
                'type' => 'arg',
                'index' => $value->getIndex(),
            ];
        }

        return $value;
    }

    public function set(mixed $value): void
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'set',
                'slot' => $this->slotId,
                'value' => $this->normalizeRecordedValue($value),
            ]);
            return;
        }

        if ($value instanceof self) {
            $value = $value->get();
        }

        $this->value = $value;
    }

    public function increment(int|float $step = 1): void
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'increment',
                'slot' => $this->slotId,
                'value' => $step,
            ]);
            return;
        }

        $this->value = ($this->value ?? 0) + $step;
    }

    public function decrement(int|float $step = 1): void
    {
        $this->increment($step * -1);
    }

    public function toggle(): void
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'toggle',
                'slot' => $this->slotId,
            ]);
            return;
        }

        $this->value = !$this->value;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function __toString(): string
    {
        $escaped = htmlspecialchars((string)($this->value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $attributes = 'data-palm-bind="' . $this->context->getId() . '::' . $this->slotId . '"';
        if ($this->global && $this->globalKey) {
            $attributes .= ' data-palm-scope="global" data-palm-key="' . htmlspecialchars($this->globalKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return '<span ' . $attributes . '>' . $escaped . '</span>';
    }

    public function token(): string
    {
        return $this->context->getId() . '::' . $this->slotId;
    }
}


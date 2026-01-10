<?php

namespace Frontend\Palm;

class ActionArgument implements \JsonSerializable
{
    protected int $index;

    public function __construct(int $index)
    {
        $this->index = $index;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function __toString(): string
    {
        return '{{arg:' . $this->index . '}}';
    }

    /**
     * Allow implicit casting to numeric types
     * This prevents errors when ActionArgument is used in numeric operations
     * Returns 0 as default to prevent errors during recording/evaluation
     */
    public function __toInt(): int
    {
        return 0;
    }

    /**
     * Implement JsonSerializable to prevent JSON errors
     */
    public function jsonSerialize(): mixed
    {
        return [
            'type' => 'arg',
            'index' => $this->index,
        ];
    }
}


<?php

namespace Frontend\Palm;

class ActionArgument
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
}


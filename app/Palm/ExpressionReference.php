<?php

namespace Frontend\Palm;

/**
 * Carries both the evaluated PHP value and the original expression string.
 * During action recording we need the expression for JS compilation, while
 * during normal execution we still need the evaluated value.
 */
class ExpressionReference
{
    protected mixed $value;
    protected string $expression;

    public function __construct(mixed $value, string $expression, bool $isEncoded = false)
    {
        $this->value = $value;
        $this->expression = $isEncoded
            ? (base64_decode($expression, true) ?: $expression)
            : $expression;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }
}






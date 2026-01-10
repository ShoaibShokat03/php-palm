<?php

namespace Frontend\Palm\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Required {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Optional {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsString {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsInt {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsBool {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsArray {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsEmail {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsUrl {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsDate {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Length
{
    public function __construct(
        public ?int $min = null,
        public ?int $max = null
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min
{
    public function __construct(public int|float $value) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Max
{
    public function __construct(public int|float $value) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Matches
{
    public function __construct(public string $pattern) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Enum
{
    public function __construct(public array $values) {}
}

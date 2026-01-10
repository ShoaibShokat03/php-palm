<?php

namespace Frontend\Palm\Validation;

use ReflectionClass;
use ReflectionProperty;
use Frontend\Palm\Validation\Attributes\Required;
use Frontend\Palm\Validation\Attributes\Optional;
use Frontend\Palm\Validation\Attributes\IsString;
use Frontend\Palm\Validation\Attributes\IsInt;
use Frontend\Palm\Validation\Attributes\IsBool;
use Frontend\Palm\Validation\Attributes\IsArray;
use Frontend\Palm\Validation\Attributes\IsEmail;
use Frontend\Palm\Validation\Attributes\IsUrl;
use Frontend\Palm\Validation\Attributes\IsDate;
use Frontend\Palm\Validation\Attributes\Length;
use Frontend\Palm\Validation\Attributes\Min;
use Frontend\Palm\Validation\Attributes\Max;
use Frontend\Palm\Validation\Attributes\Matches;
use Frontend\Palm\Validation\Attributes\Enum;

class Validator
{
    /**
     * Validate data against a DTO class
     * 
     * @template T
     * @param class-string<T> $dtoClass
     * @param array $data
     * @return T
     * @throws ValidationException
     */
    public static function validate(string $dtoClass, array $data): object
    {
        $reflection = new ReflectionClass($dtoClass);
        $dto = $reflection->newInstance();
        $errors = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propName = $property->getName();
            $value = $data[$propName] ?? null;
            $attributes = $property->getAttributes();

            // Check Required
            $hasRequired = !empty($property->getAttributes(Required::class));
            $hasOptional = !empty($property->getAttributes(Optional::class));

            // Default to optional if not specified, unless it's strictly typed? 
            // Let's implement strict explicit Required/Optional logic or implicit Required if no attribute.
            // NestJS style: If missing and not Optional, it might trigger error only if rules fail.
            // Let's go with: implicit optional if null, unless Required.

            if ($value === null || $value === '') {
                if ($hasRequired) {
                    $errors[$propName][] = "{$propName} is required.";
                }
                if ($value === null) {
                    continue; // Skip other validators if null and not required (or already errored)
                }
            }

            // Type Assignment and Casting
            // We'll try to cast if simple type, else validate

            foreach ($attributes as $attribute) {
                $attrName = $attribute->getName();
                $attrInst = $attribute->newInstance();

                switch ($attrName) {
                    case IsString::class:
                        if (!is_string($value)) {
                            $errors[$propName][] = "{$propName} must be a string.";
                        }
                        break;
                    case IsInt::class:
                        if (!filter_var($value, FILTER_VALIDATE_INT) && $value !== 0 && $value !== '0') {
                            $errors[$propName][] = "{$propName} must be an integer.";
                        } else {
                            $value = (int)$value; // Cast
                        }
                        break;
                    case IsBool::class:
                        if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                            $errors[$propName][] = "{$propName} must be a boolean.";
                        } else {
                            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN); // Cast
                        }
                        break;
                    case IsArray::class:
                        if (!is_array($value)) {
                            $errors[$propName][] = "{$propName} must be an array.";
                        }
                        break;
                    case IsEmail::class:
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$propName][] = "{$propName} must be a valid email address.";
                        }
                        break;
                    case IsUrl::class:
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[$propName][] = "{$propName} must be a valid URL.";
                        }
                        break;
                    case Length::class:
                        $len = is_string($value) ? strlen($value) : 0;
                        if ($attrInst->min !== null && $len < $attrInst->min) {
                            $errors[$propName][] = "{$propName} must be longer than {$attrInst->min} characters.";
                        }
                        if ($attrInst->max !== null && $len > $attrInst->max) {
                            $errors[$propName][] = "{$propName} must be shorter than {$attrInst->max} characters.";
                        }
                        break;
                    case Min::class:
                        if (is_numeric($value) && $value < $attrInst->value) {
                            $errors[$propName][] = "{$propName} must be at least {$attrInst->value}.";
                        }
                        break;
                    case Max::class:
                        if (is_numeric($value) && $value > $attrInst->value) {
                            $errors[$propName][] = "{$propName} must be at most {$attrInst->value}.";
                        }
                        break;
                    case Matches::class:
                        if (!preg_match($attrInst->pattern, $value)) {
                            $errors[$propName][] = "{$propName} format is invalid.";
                        }
                        break;
                    case Enum::class:
                        if (!in_array($value, $attrInst->values)) {
                            $errors[$propName][] = "{$propName} must be one of: " . implode(', ', $attrInst->values);
                        }
                        break;
                }
            }

            // Assign value to DTO if no errors for this prop so far
            if (!isset($errors[$propName])) {
                $property->setValue($dto, $value);
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $dto;
    }
}

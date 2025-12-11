<?php

namespace Frontend\Palm;

/**
 * Form validation helper for Palm framework
 * Provides simple, developer-friendly validation
 */
class FormValidator
{
    protected array $errors = [];
    protected array $rules = [];
    protected array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Add validation rule
     */
    public function rule(string $field, string|callable $rule, ?string $message = null): self
    {
        if (!isset($this->rules[$field])) {
            $this->rules[$field] = [];
        }

        $this->rules[$field][] = [
            'rule' => $rule,
            'message' => $message ?? "Validation failed for {$field}",
        ];

        return $this;
    }

    /**
     * Validate all rules
     */
    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;

            foreach ($fieldRules as $ruleData) {
                $rule = $ruleData['rule'];
                $message = $ruleData['message'];

                if (is_callable($rule)) {
                    if (!$rule($value, $this->data)) {
                        $this->errors[$field][] = $message;
                    }
                } else {
                    if (!$this->applyRule($rule, $value, $field)) {
                        $this->errors[$field][] = $message;
                    }
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply built-in validation rule
     */
    protected function applyRule(string $rule, mixed $value, string $field): bool
    {
        // Parse rule with parameters: required, min:5, max:10, email, etc.
        if (strpos($rule, ':') !== false) {
            [$ruleName, $params] = explode(':', $rule, 2);
            $params = explode(',', $params);
        } else {
            $ruleName = $rule;
            $params = [];
        }

        switch ($ruleName) {
            case 'required':
                return !empty($value) || $value === '0' || $value === 0;

            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            case 'min':
                $min = (int)($params[0] ?? 0);
                if (is_numeric($value)) {
                    return (float)$value >= $min;
                }
                return strlen((string)$value) >= $min;

            case 'max':
                $max = (int)($params[0] ?? 0);
                if (is_numeric($value)) {
                    return (float)$value <= $max;
                }
                return strlen((string)$value) <= $max;

            case 'numeric':
                return is_numeric($value);

            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false;

            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;

            case 'regex':
                $pattern = $params[0] ?? '';
                return preg_match($pattern, (string)$value) === 1;

            case 'in':
                return in_array($value, $params, true);

            case 'not_in':
                return !in_array($value, $params, true);

            case 'same':
                $otherField = $params[0] ?? '';
                return isset($this->data[$otherField]) && $value === $this->data[$otherField];

            case 'different':
                $otherField = $params[0] ?? '';
                return !isset($this->data[$otherField]) || $value !== $this->data[$otherField];

            default:
                return true;
        }
    }

    /**
     * Get all errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for specific field
     */
    public function error(string $field): ?array
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Check if field has errors
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Get first error for field
     */
    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }
}


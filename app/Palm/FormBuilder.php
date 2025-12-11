<?php

namespace Frontend\Palm;

/**
 * Form Builder
 * 
 * Provides fluent API for building forms with auto CSRF and validation
 */
class FormBuilder
{
    protected string $action;
    protected string $method = 'POST';
    protected array $attributes = [];
    protected array $fields = [];
    protected bool $autoCsrf = true;

    public function __construct(string $action = '', string $method = 'POST', array $attributes = [])
    {
        $this->action = $action ?: current_url();
        $this->method = strtoupper($method);
        $this->attributes = $attributes;
    }

    /**
     * Open form tag
     */
    public function open(): string
    {
        return form_open($this->action, $this->method, $this->attributes);
    }

    /**
     * Close form tag
     */
    public function close(): string
    {
        return form_close();
    }

    /**
     * Add text input
     */
    public function text(string $name, mixed $value = null, array $attributes = []): string
    {
        $this->fields[] = ['name' => $name, 'type' => 'text'];
        return \Frontend\Palm\text($name, $value, $this->mergeAttributes($attributes, $name));
    }

    /**
     * Add email input
     */
    public function email(string $name, mixed $value = null, array $attributes = []): string
    {
        $this->fields[] = ['name' => $name, 'type' => 'email'];
        return \Frontend\Palm\email($name, $value, $this->mergeAttributes($attributes, $name));
    }

    /**
     * Add password input
     */
    public function password(string $name, array $attributes = []): string
    {
        $this->fields[] = ['name' => $name, 'type' => 'password'];
        return \Frontend\Palm\password($name, $this->mergeAttributes($attributes, $name));
    }

    /**
     * Add textarea
     */
    public function textarea(string $name, mixed $value = null, array $attributes = []): string
    {
        $this->fields[] = ['name' => $name, 'type' => 'textarea'];
        return \Frontend\Palm\textarea($name, $value, $this->mergeAttributes($attributes, $name));
    }

    /**
     * Add select dropdown
     */
    public function select(string $name, array $options, mixed $selected = null, array $attributes = []): string
    {
        $this->fields[] = ['name' => $name, 'type' => 'select'];
        return \Frontend\Palm\select($name, $options, $selected, $this->mergeAttributes($attributes, $name));
    }

    /**
     * Add checkbox
     */
    public function checkbox(string $name, mixed $value = '1', bool $checked = false, array $attributes = []): string
    {
        $this->fields[] = ['name' => $name, 'type' => 'checkbox'];
        return \Frontend\Palm\checkbox($name, $value, $checked, $this->mergeAttributes($attributes, $name));
    }

    /**
     * Add radio button
     */
    public function radio(string $name, mixed $value, bool $checked = false, array $attributes = []): string
    {
        $this->fields[] = ['name' => $name, 'type' => 'radio'];
        return \Frontend\Palm\radio($name, $value, $checked, $this->mergeAttributes($attributes, $name));
    }

    /**
     * Add submit button
     */
    public function submit(string $text = 'Submit', array $attributes = []): string
    {
        $attrs = array_merge(['type' => 'submit'], $attributes);
        $attrString = '';
        foreach ($attrs as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }
        return '<button' . $attrString . '>' . htmlspecialchars($text) . '</button>';
    }

    /**
     * Add button
     */
    public function button(string $text, string $type = 'button', array $attributes = []): string
    {
        $attrs = array_merge(['type' => $type], $attributes);
        $attrString = '';
        foreach ($attrs as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }
        return '<button' . $attrString . '>' . htmlspecialchars($text) . '</button>';
    }

    /**
     * Add label
     */
    public function label(string $for, string $text, array $attributes = []): string
    {
        $attrs = array_merge(['for' => $for], $attributes);
        $attrString = '';
        foreach ($attrs as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }
        return '<label' . $attrString . '>' . htmlspecialchars($text) . '</label>';
    }

    /**
     * Add form group (label + input + error)
     */
    public function group(string $name, string $label, string $type = 'text', mixed $value = null, array $attributes = []): string
    {
        $html = '<div class="form-group">';
        $html .= $this->label($name, $label);
        $html .= $this->inputByType($type, $name, $value, $attributes);
        
        // Show error if exists
        if (has_error($name)) {
            $html .= '<span class="error">' . htmlspecialchars(error($name)) . '</span>';
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Create input by type
     */
    protected function inputByType(string $type, string $name, mixed $value = null, array $attributes = []): string
    {
        return match ($type) {
            'email' => $this->email($name, $value, $attributes),
            'password' => $this->password($name, $attributes),
            'textarea' => $this->textarea($name, $value, $attributes),
            default => $this->text($name, $value, $attributes),
        };
    }

    /**
     * Merge attributes with error class
     */
    protected function mergeAttributes(array $attributes, string $name): array
    {
        if (has_error($name)) {
            $attributes['class'] = ($attributes['class'] ?? '') . ' ' . error_class($name);
            $attributes['class'] = trim($attributes['class']);
        }
        return $attributes;
    }

    /**
     * Disable auto CSRF
     */
    public function withoutCsrf(): self
    {
        $this->autoCsrf = false;
        return $this;
    }

    /**
     * Get all form fields
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}


<?php

namespace Frontend\Palm\Components;

use Frontend\Palm\Component;

/**
 * Button Component
 * 
 * Usage: component('Button', ['text' => 'Click', 'type' => 'primary', 'href' => '/link'])
 */
class Button extends Component
{
    protected function renderComponent(): string
    {
        $text = $this->prop('text', $this->defaultSlot() ?: 'Button');
        $type = $this->prop('type', 'primary');
        $href = $this->prop('href', null);
        $size = $this->prop('size', '');
        $outline = $this->prop('outline', false);
        $disabled = $this->prop('disabled', false);
        $class = $this->prop('class', '');

        $types = [
            'primary' => 'primary',
            'secondary' => 'secondary',
            'success' => 'success',
            'danger' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            'light' => 'light',
            'dark' => 'dark',
            'link' => 'link',
        ];

        $buttonType = $types[$type] ?? 'primary';
        $buttonClass = $outline ? 'btn btn-outline-' . $buttonType : 'btn btn-' . $buttonType;
        
        if ($size) {
            $buttonClass .= ' btn-' . $size;
        }
        
        if ($class) {
            $buttonClass .= ' ' . $class;
        }

        $attributes = '';
        if ($disabled) {
            $attributes .= ' disabled';
        }

        // If href provided, render as link styled as button
        if ($href) {
            return '<a href="' . htmlspecialchars($href) . '" class="' . htmlspecialchars($buttonClass) . '"' . $attributes . '>' . 
                   htmlspecialchars($text) . '</a>';
        }

        // Regular button
        return '<button type="button" class="' . htmlspecialchars($buttonClass) . '"' . $attributes . '>' . 
               htmlspecialchars($text) . '</button>';
    }
}


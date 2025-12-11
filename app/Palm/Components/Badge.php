<?php

namespace Frontend\Palm\Components;

use Frontend\Palm\Component;

/**
 * Badge Component
 * 
 * Usage: component('Badge', ['text' => 'New', 'type' => 'primary'])
 */
class Badge extends Component
{
    protected function renderComponent(): string
    {
        $text = $this->prop('text', $this->defaultSlot() ?: 'Badge');
        $type = $this->prop('type', 'primary');
        $pill = $this->prop('pill', false);
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
        ];

        $badgeType = $types[$type] ?? 'primary';
        $badgeClass = 'badge bg-' . $badgeType;
        
        if ($pill) {
            $badgeClass .= ' rounded-pill';
        }
        
        if ($class) {
            $badgeClass .= ' ' . $class;
        }

        return '<span class="' . htmlspecialchars($badgeClass) . '">' . htmlspecialchars($text) . '</span>';
    }
}


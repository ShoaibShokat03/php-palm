<?php

namespace Frontend\Palm\Components;

use Frontend\Palm\Component;

/**
 * Alert Component
 * 
 * Usage: component('Alert', ['type' => 'success', 'message' => 'Saved!'])
 */
class Alert extends Component
{
    protected function renderComponent(): string
    {
        $type = $this->prop('type', 'info');
        $message = $this->prop('message', '');
        $dismissible = $this->prop('dismissible', false);
        $class = $this->prop('class', '');

        $types = [
            'success' => 'success',
            'error' => 'danger',
            'danger' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            'primary' => 'primary',
            'secondary' => 'secondary',
        ];

        $alertType = $types[$type] ?? 'info';
        $alertClass = 'alert alert-' . $alertType;
        
        if ($dismissible) {
            $alertClass .= ' alert-dismissible fade show';
        }
        
        if ($class) {
            $alertClass .= ' ' . $class;
        }

        $html = '<div class="' . htmlspecialchars($alertClass) . '" role="alert">';
        
        // Message or default slot
        $content = $message ?: $this->defaultSlot();
        $html .= htmlspecialchars($content);

        // Dismiss button
        if ($dismissible) {
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        }

        $html .= '</div>';

        return $html;
    }
}


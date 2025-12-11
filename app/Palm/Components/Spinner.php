<?php

namespace Frontend\Palm\Components;

use Frontend\Palm\Component;

/**
 * Spinner Component
 * 
 * Usage: component('Spinner', ['type' => 'border', 'size' => 'sm'])
 */
class Spinner extends Component
{
    protected function renderComponent(): string
    {
        $type = $this->prop('type', 'border'); // border or grow
        $size = $this->prop('size', ''); // sm or empty
        $class = $this->prop('class', '');
        $text = $this->prop('text', '');
        $role = $this->prop('role', 'status');
        $ariaLabel = $this->prop('aria-label', 'Loading...');

        $spinnerClass = 'spinner-' . $type;
        
        if ($size) {
            $spinnerClass .= ' spinner-' . $type . '-' . $size;
        }
        
        if ($class) {
            $spinnerClass .= ' ' . $class;
        }

        $html = '<div class="' . htmlspecialchars($spinnerClass) . '" role="' . htmlspecialchars($role) . '" aria-label="' . htmlspecialchars($ariaLabel) . '">';
        
        if ($type === 'border') {
            $html .= '<span class="visually-hidden">Loading...</span>';
        }
        
        $html .= '</div>';

        if ($text) {
            $html .= ' <span class="ms-2">' . htmlspecialchars($text) . '</span>';
        }

        return $html;
    }
}


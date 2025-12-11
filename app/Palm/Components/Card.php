<?php

namespace Frontend\Palm\Components;

use Frontend\Palm\Component;

/**
 * Card Component
 * 
 * Usage: component('Card', ['title' => 'Card Title'], ['content' => 'Card body'])
 */
class Card extends Component
{
    protected function renderComponent(): string
    {
        $title = $this->prop('title', '');
        $subtitle = $this->prop('subtitle', '');
        $class = $this->prop('class', '');
        $header = $this->slot('header', '');
        $footer = $this->slot('footer', '');
        $content = $this->defaultSlot();

        $cardClass = 'card';
        if ($class) {
            $cardClass .= ' ' . $class;
        }

        $html = '<div class="' . htmlspecialchars($cardClass) . '">';

        // Card Header
        if ($header || $title) {
            $html .= '<div class="card-header">';
            if ($header) {
                $html .= $header;
            } elseif ($title) {
                $html .= '<h5 class="card-title">' . htmlspecialchars($title) . '</h5>';
                if ($subtitle) {
                    $html .= '<h6 class="card-subtitle mb-2 text-muted">' . htmlspecialchars($subtitle) . '</h6>';
                }
            }
            $html .= '</div>';
        }

        // Card Body
        if ($content || $this->slot('body')) {
            $bodyContent = $content ?: $this->slot('body');
            $html .= '<div class="card-body">';
            $html .= $bodyContent;
            $html .= '</div>';
        }

        // Card Footer
        if ($footer) {
            $html .= '<div class="card-footer">';
            $html .= $footer;
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}


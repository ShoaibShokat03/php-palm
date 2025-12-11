<?php

namespace Frontend\Palm\Components;

use Frontend\Palm\Component;

/**
 * Modal Component
 * 
 * Usage: 
 * component('Modal', [
 *     'id' => 'myModal',
 *     'title' => 'Confirm',
 *     'size' => 'lg'
 * ], [
 *     'body' => 'Are you sure?',
 *     'footer' => '<button>Close</button>'
 * ])
 */
class Modal extends Component
{
    protected function renderComponent(): string
    {
        $id = $this->prop('id', 'modal-' . uniqid());
        $title = $this->prop('title', 'Modal Title');
        $size = $this->prop('size', ''); // sm, lg, xl, fullscreen
        $centered = $this->prop('centered', false);
        $scrollable = $this->prop('scrollable', false);
        $static = $this->prop('static', false);
        $class = $this->prop('class', '');
        
        $body = $this->slot('body', $this->defaultSlot());
        $footer = $this->slot('footer', '');

        // Modal dialog classes
        $dialogClass = 'modal-dialog';
        
        if ($centered) {
            $dialogClass .= ' modal-dialog-centered';
        }
        
        if ($scrollable) {
            $dialogClass .= ' modal-dialog-scrollable';
        }
        
        if ($static) {
            $dialogClass .= ' modal-dialog-static';
        }
        
        if ($size) {
            if ($size === 'fullscreen') {
                $dialogClass .= ' modal-fullscreen';
            } else {
                $dialogClass .= ' modal-' . $size;
            }
        }
        
        if ($class) {
            $dialogClass .= ' ' . $class;
        }

        $html = '<div class="modal fade" id="' . htmlspecialchars($id) . '" tabindex="-1" aria-labelledby="' . htmlspecialchars($id) . 'Label" aria-hidden="true">';
        $html .= '<div class="' . htmlspecialchars($dialogClass) . '">';
        $html .= '<div class="modal-content">';
        
        // Modal Header
        $html .= '<div class="modal-header">';
        $html .= '<h5 class="modal-title" id="' . htmlspecialchars($id) . 'Label">' . htmlspecialchars($title) . '</h5>';
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
        $html .= '</div>';
        
        // Modal Body
        $html .= '<div class="modal-body">';
        $html .= $body;
        $html .= '</div>';
        
        // Modal Footer (if provided)
        if ($footer) {
            $html .= '<div class="modal-footer">';
            $html .= $footer;
            $html .= '</div>';
        }
        
        $html .= '</div>'; // modal-content
        $html .= '</div>'; // modal-dialog
        $html .= '</div>'; // modal

        return $html;
    }
}


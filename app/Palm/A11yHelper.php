<?php

namespace Frontend\Palm;

/**
 * Accessibility (a11y) Helper
 * 
 * Provides accessibility helpers for better inclusive design
 */
class A11yHelper
{
    /**
     * Generate skip link
     */
    public static function skipLink(string $target = '#main', string $text = 'Skip to main content'): string
    {
        return '<a href="' . htmlspecialchars($target) . '" class="skip-link" style="position:absolute;left:-9999px;z-index:999;padding:1em;background:#000;color:#fff;text-decoration:none;">' . 
               htmlspecialchars($text) . '</a>' .
               '<script>document.querySelector(".skip-link")?.addEventListener("focus",function(e){e.target.style.left="auto"});document.querySelector(".skip-link")?.addEventListener("blur",function(e){e.target.style.left="-9999px"});</script>';
    }

    /**
     * Generate ARIA label attribute
     */
    public static function ariaLabel(string $label): string
    {
        return 'aria-label="' . htmlspecialchars($label) . '"';
    }

    /**
     * Generate ARIA described by attribute
     */
    public static function ariaDescribedBy(string $id): string
    {
        return 'aria-describedby="' . htmlspecialchars($id) . '"';
    }

    /**
     * Generate ARIA live region
     */
    public static function liveRegion(string $id, string $level = 'polite'): string
    {
        return '<div id="' . htmlspecialchars($id) . '" aria-live="' . htmlspecialchars($level) . '" aria-atomic="true" class="sr-only"></div>';
    }

    /**
     * Generate screen reader only text
     */
    public static function srOnly(string $text): string
    {
        return '<span class="sr-only" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border-width:0;">' . 
               htmlspecialchars($text) . '</span>';
    }

    /**
     * Generate accessible button with ARIA
     */
    public static function button(string $text, array $attributes = []): string
    {
        $attrs = array_merge([
            'type' => 'button',
            'aria-label' => $text,
        ], $attributes);

        $attrString = '';
        foreach ($attrs as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        return '<button' . $attrString . '>' . htmlspecialchars($text) . '</button>';
    }

    /**
     * Generate accessible link with ARIA
     */
    public static function link(string $url, string $text, array $attributes = []): string
    {
        $attrs = array_merge([
            'href' => $url,
            'aria-label' => $text,
        ], $attributes);

        $attrString = '';
        foreach ($attrs as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        return '<a' . $attrString . '>' . htmlspecialchars($text) . '</a>';
    }

    /**
     * Generate accessible form field with label and error
     */
    public static function formField(string $type, string $name, string $label, array $options = []): string
    {
        $id = $options['id'] ?? 'field-' . $name;
        $required = $options['required'] ?? false;
        $error = $options['error'] ?? null;
        $help = $options['help'] ?? null;
        $value = $options['value'] ?? '';

        $html = '<div class="form-field">';
        
        // Label
        $labelAttrs = 'for="' . htmlspecialchars($id) . '"';
        if ($required) {
            $labelAttrs .= ' aria-required="true"';
        }
        $html .= '<label ' . $labelAttrs . '>' . htmlspecialchars($label);
        if ($required) {
            $html .= ' <span aria-label="required">*</span>';
        }
        $html .= '</label>';

        // Input
        $inputAttrs = [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'value' => $value,
        ];

        if ($required) {
            $inputAttrs['required'] = 'required';
            $inputAttrs['aria-required'] = 'true';
        }

        if ($error) {
            $inputAttrs['aria-invalid'] = 'true';
            $inputAttrs['aria-describedby'] = $id . '-error';
        } elseif ($help) {
            $inputAttrs['aria-describedby'] = $id . '-help';
        }

        $attrString = '';
        foreach ($inputAttrs as $key => $val) {
            if ($val !== '' && $val !== null) {
                $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
            }
        }

        $html .= '<input' . $attrString . '>';

        // Error message
        if ($error) {
            $html .= '<div id="' . htmlspecialchars($id) . '-error" class="error" role="alert" aria-live="polite">' . 
                     htmlspecialchars($error) . '</div>';
        }

        // Help text
        if ($help && !$error) {
            $html .= '<div id="' . htmlspecialchars($id) . '-help" class="help-text">' . 
                     htmlspecialchars($help) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate landmark regions
     */
    public static function landmark(string $type, string $content, array $attributes = []): string
    {
        $landmarks = [
            'banner' => 'header',
            'navigation' => 'nav',
            'main' => 'main',
            'complementary' => 'aside',
            'contentinfo' => 'footer',
            'search' => 'form',
            'form' => 'form',
        ];

        $tag = $landmarks[$type] ?? 'div';
        $role = $tag === 'div' ? ' role="' . htmlspecialchars($type) . '"' : '';

        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        return '<' . $tag . $role . $attrString . '>' . $content . '</' . $tag . '>';
    }
}


<?php

namespace Frontend\Palm;

use Frontend\Palm\ComponentRenderer;

/**
 * Static Render Helper
 */
class Render
{
    /**
     * Render a component and echo output
     */
    public static function component(string $name, array $props = [], array $slots = []): void
    {
        if (!class_exists(ComponentRenderer::class)) {
            require_once __DIR__ . '/ComponentRenderer.php';
        }

        ComponentRenderer::init();
        echo ComponentRenderer::render($name, $props, $slots);
    }
}

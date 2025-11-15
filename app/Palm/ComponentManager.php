<?php

namespace Frontend\Palm;

class ComponentManager
{
    protected static ?ComponentContext $current = null;
    protected static int $counter = 0;

    public static function hasContext(): bool
    {
        return self::$current !== null;
    }

    public static function current(): ?ComponentContext
    {
        return self::$current;
    }

    public static function render(callable $renderer): array
    {
        $context = new ComponentContext('cmp_' . (++self::$counter));
        self::$current = $context;

        ob_start();
        $renderer();
        $html = ob_get_clean() ?: '';

        $componentPayload = $context->buildPayload();
        if ($componentPayload !== null) {
            $html = $context->finalizeHtml($html);
        }

        $scripts = PalmScriptRegistry::flush();

        self::$current = null;

        return [
            'html' => $html,
            'component' => $componentPayload,
            'scripts' => $scripts,
        ];
    }
}


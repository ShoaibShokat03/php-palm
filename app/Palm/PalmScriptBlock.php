<?php

namespace Frontend\Palm;

class PalmScriptBlock
{
    /**
     * @var array<int, array{options:array<string,mixed>}>
     */
    protected static array $stack = [];

    public static function start(array $options = []): void
    {
        $defaults = [
            'target' => 'head',
            'once' => true,
        ];

        self::$stack[] = [
            'options' => $options + $defaults,
        ];

        ob_start();
    }

    public static function end(): void
    {
        if (empty(self::$stack)) {
            throw new \RuntimeException('endPalmScript() called without matching startPalmScript().');
        }

        $context = array_pop(self::$stack);
        $buffer = ob_get_clean();

        if ($buffer === false) {
            return;
        }

        $code = trim($buffer);
        if ($code === '') {
            return;
        }

        PalmScriptRegistry::addPhp($code, $context['options']);
    }
}



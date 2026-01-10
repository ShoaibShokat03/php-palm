<?php

namespace Frontend\Palm;

class PalmScriptRegistry
{
    /**
     * @var array<string, array{hash:string, code:string, target:string, attrs:array<string,mixed>, once:bool}>
     */
    protected static array $scripts = [];

    protected static ?PHPToJSCompiler $compiler = null;

    public static function addPhp(string $phpCode, array $options = []): void
    {
        $phpCode = trim($phpCode);
        if ($phpCode === '') {
            return;
        }

        $compiler = self::getCompiler();
        $js = trim($compiler->compile($phpCode));
        if ($js === '') {
            return;
        }

        self::addJs($js, $options);
    }

    public static function addJs(string $jsCode, array $options = []): void
    {
        $jsCode = trim($jsCode);
        if ($jsCode === '') {
            return;
        }

        $hash = $options['hash'] ?? substr(sha1($jsCode), 0, 16);
        $target = in_array($options['target'] ?? 'body', ['head', 'body'], true)
            ? $options['target']
            : 'body';
        $attrs = array_filter(
            (array)($options['attrs'] ?? []),
            static fn($value, $key) => is_string($key),
            ARRAY_FILTER_USE_BOTH
        );
        $once = array_key_exists('once', $options) ? (bool)$options['once'] : true;

        self::$scripts[$hash] = [
            'hash' => $hash,
            'code' => $jsCode,
            'target' => $target,
            'attrs' => $attrs,
            'once' => $once,
        ];
    }

    /**
     * @return array<int, array{hash:string, code:string, target:string, attrs:array<string,mixed>, once:bool}>
     */
    public static function flush(): array
    {
        $scripts = array_values(self::$scripts);
        self::$scripts = [];
        return $scripts;
    }

    public static function getCompiler(): PHPToJSCompiler
    {
        if (!self::$compiler) {
            self::$compiler = new PHPToJSCompiler();
        }

        return self::$compiler;
    }

    public static function reset(): void
    {
        self::$scripts = [];
        self::$compiler = null;
    }
}



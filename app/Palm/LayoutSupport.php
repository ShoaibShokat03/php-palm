<?php

namespace Frontend\Palm;

class LayoutSupport
{
    public static function buildContext(array $clientViews, ?string $currentSlug, array $initialScripts): array
    {
        return [
            'headScripts' => self::filterScriptsByTarget($initialScripts, 'head'),
            'bodyScripts' => self::filterScriptsByTarget($initialScripts, 'body', true),
            'bootComponents' => self::resolveBootComponents($clientViews, $currentSlug),
            'globalState' => self::collectGlobalState($clientViews),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $scripts
     */
    public static function renderScripts(array $scripts): string
    {
        $output = '';
        foreach ($scripts as $script) {
            $code = $script['code'] ?? '';
            if ($code === '') {
                continue;
            }

            $attrString = self::buildAttributeString($script);
            $output .= '<script' . $attrString . '>' . $code . '</script>' . PHP_EOL;
        }
        return $output;
    }

    /**
     * @param array<int, array<string, mixed>> $scripts
     * @return array<int, array<string, mixed>>
     */
    protected static function filterScriptsByTarget(array $scripts, string $target, bool $defaultFallback = false): array
    {
        return array_values(array_filter(
            $scripts,
            static function ($script) use ($target, $defaultFallback) {
                $candidate = $script['target'] ?? 'body';
                return $defaultFallback
                    ? $candidate !== 'head'
                    : $candidate === $target;
            }
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function resolveBootComponents(array $clientViews, ?string $currentSlug): array
    {
        if (!$currentSlug) {
            return [];
        }

        $component = $clientViews[$currentSlug]['component'] ?? null;
        if (!$component) {
            return [];
        }

        return [$component];
    }

    protected static function collectGlobalState(array $clientViews): array
    {
        $globalState = [];
        foreach ($clientViews as $viewPayload) {
            $componentMeta = $viewPayload['component'] ?? null;
            if (!$componentMeta || empty($componentMeta['states'])) {
                continue;
            }

            foreach ($componentMeta['states'] as $stateMeta) {
                $isGlobal = !empty($stateMeta['global']) && !empty($stateMeta['key']);
                if (!$isGlobal) {
                    continue;
                }
                $key = (string)$stateMeta['key'];
                if ($key === '' || array_key_exists($key, $globalState)) {
                    continue;
                }
                $globalState[$key] = $stateMeta['value'];
            }
        }

        return $globalState;
    }

    protected static function buildAttributeString(array $script): string
    {
        $attrString = '';
        $attrs = $script['attrs'] ?? [];

        foreach ($attrs as $attr => $value) {
            if (!is_string($attr) || $attr === '') {
                continue;
            }

            $attrName = htmlspecialchars($attr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if (is_bool($value)) {
                if ($value) {
                    $attrString .= ' ' . $attrName . '="' . $attrName . '"';
                }
                continue;
            }

            $attrString .= ' ' . $attrName . '="' . htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        $once = array_key_exists('once', $script) ? (bool)$script['once'] : true;
        $attrString .= ' data-palm-once="' . ($once ? '1' : '0') . '"';

        $hash = isset($script['hash']) ? (string)$script['hash'] : '';
        if ($hash !== '') {
            $attrString .= ' data-palm-script="' . htmlspecialchars($hash, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $attrString;
    }
}



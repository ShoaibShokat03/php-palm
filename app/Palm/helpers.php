<?php

namespace Frontend\Palm {
    require_once __DIR__ . '/ActionArgument.php';
    require_once __DIR__ . '/StateSlot.php';
    require_once __DIR__ . '/ComponentContext.php';
    require_once __DIR__ . '/ComponentManager.php';
    require_once __DIR__ . '/PHPToJSCompiler.php';
    require_once __DIR__ . '/PalmScriptRegistry.php';
    require_once __DIR__ . '/PalmScriptBlock.php';

    if (!function_exists(__NAMESPACE__ . '\State')) {
        function State(mixed $initial = null)
        {
            $context = ComponentManager::current();
            if (!$context) {
                return $initial;
            }

            return $context->createState($initial);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\PalmState')) {
        function PalmState(string $key, mixed $initial = null)
        {
            $context = ComponentManager::current();
            if (!$context) {
                return $initial;
            }

            return $context->createGlobalState($key, $initial);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\Action')) {
        function Action(string $name, callable $callback): void
        {
            $context = ComponentManager::current();
            if (!$context) {
                return;
            }

            $context->registerAction($name, $callback);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\PalmScript')) {
        /**
         * @param callable|string $code
         */
        function PalmScript(callable|string $code, array $options = []): void
        {
            $phpCode = '';

            if (is_callable($code)) {
                ob_start();
                $result = $code();
                $buffered = ob_get_clean();
                if (is_string($buffered)) {
                    $phpCode .= $buffered;
                }
                if (is_string($result)) {
                    $phpCode .= $result;
                }
            } else {
                $phpCode = (string)$code;
            }

            PalmScriptRegistry::addPhp($phpCode, $options);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\PalmScriptRaw')) {
        function PalmScriptRaw(string $jsCode, array $options = []): void
        {
            PalmScriptRegistry::addJs($jsCode, $options);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\startPalmScript')) {
        function startPalmScript(array $options = []): void
        {
            PalmScriptBlock::start($options);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\endPalmScript')) {
        function endPalmScript(): void
        {
            PalmScriptBlock::end();
        }
    }

    if (!function_exists(__NAMESPACE__ . '\startPalmScirpt')) {
        function startPalmScirpt(array $options = []): void
        {
            startPalmScript($options);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\endPalmScirpt')) {
        function endPalmScirpt(): void
        {
            endPalmScript();
        }
    }
}

namespace {
    if (!function_exists('State')) {
        function State(mixed $initial = null)
        {
            return \Frontend\Palm\State($initial);
        }
    }

    if (!function_exists('PalmState')) {
        function PalmState(string $key, mixed $initial = null)
        {
            return \Frontend\Palm\PalmState($key, $initial);
        }
    }

    if (!function_exists('Action')) {
        function Action(string $name, callable $callback): void
        {
            \Frontend\Palm\Action($name, $callback);
        }
    }

    if (!function_exists('PalmScript')) {
        function PalmScript(callable|string $code, array $options = []): void
        {
            \Frontend\Palm\PalmScript($code, $options);
        }
    }

    if (!function_exists('PalmScriptRaw')) {
        function PalmScriptRaw(string $jsCode, array $options = []): void
        {
            \Frontend\Palm\PalmScriptRaw($jsCode, $options);
        }
    }

    if (!function_exists('startPalmScript')) {
        function startPalmScript(array $options = []): void
        {
            \Frontend\Palm\startPalmScript($options);
        }
    }

    if (!function_exists('endPalmScript')) {
        function endPalmScript(): void
        {
            \Frontend\Palm\endPalmScript();
        }
    }

    if (!function_exists('startPalmScirpt')) {
        function startPalmScirpt(array $options = []): void
        {
            startPalmScript($options);
        }
    }

    if (!function_exists('endPalmScirpt')) {
        function endPalmScirpt(): void
        {
            endPalmScript();
        }
    }
}


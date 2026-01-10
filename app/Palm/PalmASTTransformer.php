<?php

namespace Frontend\Palm;

/**
 * AST Transformer for .palm.php files
 * Transforms AST into SSR-ready PHP and hydration JS
 */
class PalmASTTransformer
{
    protected array $ast;
    protected string $filePath;
    protected array $stateMap = []; // Maps variable names to slot IDs
    protected array $metadata = [
        'bindings' => [],
        'stateSlots' => [],
        'actions' => [],
    ];

    public function __construct(array $ast, string $filePath)
    {
        $this->ast = $ast;
        $this->filePath = $filePath;
    }

    /**
     * Transform AST to SSR-ready PHP
     */
    public function toPhp(): string
    {
        $output = [];
        $output[] = '<?php';
        $output[] = '// Auto-generated from .palm.php file';
        $output[] = '// Source: ' . basename($this->filePath);
        $output[] = '';

        // helpers.php is loaded before including compiled PHP files in Route.php using PALM_ROOT
        // No need to include it here as it's already available in the eval context
        // The PHP code block from the source file may have its own require_once
        $output[] = '';

        $this->transformNode($this->ast, $output);

        return implode("\n", $output);
    }

    /**
     * Transform AST to hydration JS module
     */
    public function toJs(): string
    {
        $output = [];
        $output[] = '// Auto-generated from .palm.php file';
        $output[] = '// Source: ' . basename($this->filePath);
        $output[] = '';
        $output[] = 'export function mount(root, initial) {';
        $output[] = '  const state = {};';
        $output[] = '';

        // Initialize states
        foreach ($this->metadata['stateSlots'] as $slotId => $state) {
            $initialValue = json_encode($state['value'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $output[] = "  state['{$slotId}'] = createState({$initialValue});";
        }
        $output[] = '';

        // Bind state to DOM
        foreach ($this->metadata['bindings'] as $binding) {
            $slotId = $binding['slotId'];
            $selector = json_encode($binding['selector'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $output[] = "  root.querySelectorAll({$selector}).forEach(el => {";
            $output[] = "    state['{$slotId}'].subscribe(value => {";
            $output[] = "      el.textContent = value;";
            $output[] = "    });";
            $output[] = "  });";
            $output[] = '';
        }

        $output[] = '  return { state, unmount: () => {} };';
        $output[] = '}';
        $output[] = '';

        /* Helper function */
        $output[] = 'function createState(initial) {';
        $output[] = '  let value = initial;';
        $output[] = '  const subscribers = new Set();';
        $output[] = '  return {';
        $output[] = '    get() { return value; },';
        $output[] = '    set(newValue) {';
        $output[] = '      if (value !== newValue) {';
        $output[] = '        value = newValue;';
        $output[] = '        subscribers.forEach(fn => fn(value));';
        $output[] = '      }';
        $output[] = '    },';
        $output[] = '    subscribe(fn) {';
        $output[] = '      subscribers.add(fn);';
        $output[] = '      return () => subscribers.delete(fn);';
        $output[] = '    },';
        $output[] = '  };';
        $output[] = '}';

        return implode("\n", $output);
    }

    /**
     * Get metadata for hydration
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    protected function transformNode(array $node, array &$output, ?string $parentTag = null): void
    {
        switch ($node['type']) {
            case 'Document':
                foreach ($node['children'] ?? [] as $child) {
                    $this->transformNode($child, $output, $parentTag);
                }
                break;

            case 'Text':
                /* DOCTYPE declarations should not be escaped */
                if (preg_match('/^<!DOCTYPE\s+/i', $node['value'])) {
                    $output[] = "echo " . var_export($node['value'], true) . ";";
                } elseif (in_array(strtolower($parentTag ?? ''), ['script', 'style'])) {
                    /* Text inside script/style tags should not be HTML-escaped */
                    $output[] = "echo " . var_export($node['value'], true) . ";";
                } else {
                    $value = htmlspecialchars($node['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $output[] = "echo " . var_export($value, true) . ";";
                }
                break;

            case 'Element':
                $this->transformElement($node, $output, $parentTag);
                break;

            case 'Php':
                $phpCode = $node['code'];
                if ($node['isEcho']) {
                    /* PHP echo code (<?= ... ?>) - auto-bind to state if it's a state variable */
                    $phpCode = $this->transformEchoExpression($phpCode);
                    $output[] = "echo " . $phpCode . ";";
                } else {
                    /* PHP code block - transform require_once paths to use PALM_ROOT */
                    $phpCode = $this->transformPhpCode($phpCode);
                    $output[] = $phpCode;
                }
                break;

            case 'Expression':
                /* DEPRECATED: {expression} syntax - use <?= expression ?> instead */
                /* Keeping for backward compatibility, but will be removed in future versions */
                $code = $node['code'];
                /* The expression will be evaluated and escaped - code is already valid PHP */
                $output[] = "echo htmlspecialchars((string)(" . $code . "), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');";
                break;

            default:
                /* Unknown node type, skip it */
                break;
        }
    }

    protected function transformElement(array $node, array &$output, ?string $parentTag = null): void
    {
        $tag = $node['tag'];
        $attributes = $node['attributes'];
        $children = $node['children'] ?? [];
        $isSelfClosing = $node['isSelfClosing'] ?? false;

        /* Build opening tag as a single echo statement */
        $tagStr = var_export($tag, true);
        $parts = ["echo '<' . " . $tagStr];

        foreach ($attributes as $name => $value) {
            if ($value === true) {
                /* Boolean attribute */
                $parts[] = " . ' {$name}'";
            } elseif (is_array($value) && isset($value['type']) && $value['type'] === 'Expression') {
                /* Expression attribute: class={$active ? 'active' : ''} */
                $exprCode = $value['code'];
                if ($name === '') {
                    /* Expression-only attribute: {$loading->get() ? 'disabled' : ''} */
                    /* Output the expression result as the attribute name if non-empty */
                    /* Don't escape - it's an attribute name, not content */
                    /* Evaluate the expression once and output with space prefix if non-empty */
                    /* The expression returns empty string if false, so just add space prefix if truthy */
                    /* Use a temp variable to avoid evaluating twice - escape $ properly */
                    $tempVarName = '__expr_' . bin2hex(random_bytes(4));
                    $parts[] = " . ((\$" . $tempVarName . " = ({$exprCode})) ? ' ' . (string)\$" . $tempVarName . " : '')";
                } else {
                    /* Expression in attribute value - escape the result for HTML attributes */
                    $parts[] = " . ' {$name}=\"' . htmlspecialchars((string)({$exprCode}), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '\"'";
                }
            } else {
                /* Static attribute value */
                $escaped = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $escapedStr = var_export($escaped, true);
                $parts[] = " . ' {$name}=\"' . " . $escapedStr . " . '\"'";
            }
        }

        if (!$isSelfClosing) {
            $parts[] = " . '>';";
            $output[] = implode('', $parts);
            /* Transform children - pass current tag as parent */
            foreach ($children as $child) {
                $this->transformNode($child, $output, $tag);
            }
            $tagClose = var_export($tag, true);
            $output[] = "echo '</' . " . $tagClose . " . '>';";
        } else {
            $parts[] = " . ' />';";
            $output[] = implode('', $parts);
        }
    }

    /**
     * Transform PHP code to fix require_once paths and state variable operations for eval context
     * Uses AST-based transformation for maximum accuracy
     */
    protected function transformPhpCode(string $code): string
    {
        // Replace require_once statements that use dirname(__DIR__, N) with PALM_ROOT
        // Pattern: require_once dirname(__DIR__, N) . '/app/Palm/helpers.php';
        $code = preg_replace(
            "/require_once\s+dirname\(__DIR__,\s*\d+\)\s*\.\s*['\"]\/app\/Palm\/helpers\.php['\"]\s*;/i",
            "// helpers.php already loaded via PALM_ROOT before eval",
            $code
        );

        // Replace any other dirname(__DIR__, N) patterns with PALM_ROOT
        // This handles cases like: require_once dirname(__DIR__, 3) . '/some/path.php';
        $code = preg_replace_callback(
            "/dirname\(__DIR__,\s*(\d+)\)/",
            function ($matches) {
                // For now, just replace with PALM_ROOT
                // If we need more sophisticated path resolution, we can calculate it
                return "PALM_ROOT";
            },
            $code
        );

        // Use AST-based transformer for state variable operations
        $transformer = new PHPCodeTransformer();
        $code = $transformer->transform($code);

        return $code;
    }

    protected function generateSetExpression(string $varName, string $expression): string
    {
        $encodedExpr = base64_encode($expression);
        $tempVar = '$__palmExprValue_' . bin2hex(random_bytes(3));
        $exprRefClass = '\\Frontend\\Palm\\ExpressionReference';

        return "\$GLOBALS['__PALM_EXPR__'] = '{$encodedExpr}'; {$tempVar} = {$expression}; {$varName}->set(new {$exprRefClass}({$tempVar}, '{$encodedExpr}', true)); if (isset(\$GLOBALS['__PALM_EXPR__'])) unset(\$GLOBALS['__PALM_EXPR__']);";
    }

    /**
     * Transform echo expressions to auto-bind state variables
     * Detects state variables and wraps them in reactive bindings
     */
    protected function transformEchoExpression(string $code): string
    {
        // Check if PhpParser is available for AST-based detection
        if (!class_exists(\PhpParser\ParserFactory::class)) {
            return $code; // Fallback: return as-is
        }

        try {
            $parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
            $wrappedCode = '<?php ' . $code;
            $stmts = $parser->parse($wrappedCode);

            if ($stmts === null || empty($stmts)) {
                return $code;
            }

            // Find all State()/PalmState() declarations in the file context
            // We'll need to track state variables across the entire template
            // For now, use a simple pattern match approach

            /*Pattern: detect simple state variable output like <?= $count ?>*/
            // We need to wrap it: StateSlot::output($count) which will generate binding
            if (preg_match('/^\s*\$(\w+)\s*$/', trim($code), $matches)) {
                $varName = $matches[1];
                // Check if this might be a state variable (we can't know for sure without full context)
                // The StateSlot class will handle this at runtime
                // For now, keep as-is - the runtime will detect if it's a StateSlot
                return $code;
            }

            return $code;
        } catch (\Throwable $e) {
            return $code;
        }
    }
}

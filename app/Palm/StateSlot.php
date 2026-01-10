<?php

namespace Frontend\Palm;

class StateSlot
{
    protected ComponentContext $context;
    protected string $slotId;
    protected mixed $value;
    protected bool $global;
    protected ?string $globalKey;
    protected ?string $varName = null;
    protected bool $isComputed = false;
    /** @var callable|null */
    protected $computeFunction = null;
    protected array $dependencies = [];

    public function __construct(ComponentContext $context, string $slotId, mixed $initial = null, bool $global = false, ?string $globalKey = null, ?string $varName = null)
    {
        $this->context = $context;
        $this->slotId = $slotId;
        $this->value = $initial;
        $this->global = $global;
        $this->globalKey = $global ? ($globalKey ?? $slotId) : null;
        if ($varName !== null) {
            $this->setVarName($varName);
        }
    }

    public function getSlotId(): string
    {
        return $this->slotId;
    }
    
    public function getVarName(): ?string
    {
        return $this->varName;
    }
    
    public function setVarName(string $varName): void
    {
        $this->varName = ltrim($varName, '$');
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isGlobal(): bool
    {
        return $this->global;
    }

    public function getGlobalKey(): ?string
    {
        return $this->globalKey;
    }

    public function __invoke(mixed $value = null): mixed
    {
        if (func_num_args() === 0) {
            return $this->get();
        }

        $this->set($value);
        return $this->value;
    }

    protected function normalizeRecordedValue(mixed $value): mixed
    {
        if ($value instanceof self) {
            $value = $value->get();
        }

        if ($value instanceof ActionArgument) {
            return [
                'type' => 'arg',
                'index' => $value->getIndex(),
            ];
        }

        return $value;
    }

    public function set(mixed $value): void
    {
        $expressionFromValue = null;
        if ($value instanceof ExpressionReference) {
            $expressionFromValue = $value->getExpression();
            $value = $value->getValue();
        }

        if ($this->context->isRecording()) {
            $isFromReference = $expressionFromValue !== null;
            // Prefer expression bundled with the value (ExpressionReference)
            $expression = $expressionFromValue ?? $this->extractExpressionFromStack();
            
            if ($expression !== null) {
                // The expression from ActionRewriter is base64-encoded
                // Decode it first
                if (
                    !$isFromReference &&
                    preg_match('/^[A-Za-z0-9+\/]+=*$/', $expression) &&
                    base64_decode($expression, true) !== false
                ) {
                    $decoded = base64_decode($expression, true);
                    if ($decoded !== false && $decoded !== $expression) {
                        $expression = $decoded;
                    }
                }
                
                // The expression is now in PHP format like: $count->get() + 1
                // We need to convert it to JavaScript format
                
                // Check for common patterns that can be converted to specific operations
                $patternOp = $this->detectPatternOperation($expression);
                if ($patternOp !== null) {
                    $this->context->recordOperation($patternOp);
                    return;
                }
                
                // This is an expression operation - record it with the expression
                // Map variable names to slot IDs in the expression
                // Convert PHP expression to JS
                // Replace $var->get() with state['slotId'].get()
                $jsExpr = $this->convertExpressionToJs($expression);
                
                // Validate the converted expression
                if (empty($jsExpr) || trim($jsExpr) === '') {
                    // Fallback: use the original expression pattern
                    error_log('Warning: Empty JavaScript expression from: ' . substr($expression, 0, 100));
                    $jsExpr = "state['{$this->slotId}'].get()";
                }
                
                $this->context->recordOperation([
                    'type' => 'expr',
                    'slot' => $this->slotId,
                    'expr' => $jsExpr,
                    'operation' => 'set',
                ]);
                return;
            }
            
            // Try to extract expression from call stack by parsing source code
            $expression = $this->extractExpressionFromSource();
            if ($expression !== null) {
                $patternOp = $this->detectPatternOperation($expression);
                if ($patternOp !== null) {
                    $this->context->recordOperation($patternOp);
                    return;
                }
                
                $jsExpr = $this->convertExpressionToJs($expression);
                $this->context->recordOperation([
                    'type' => 'expr',
                    'slot' => $this->slotId,
                    'expr' => $jsExpr,
                    'operation' => 'set',
                ]);
                return;
            }
            
            // Normal recording
            $this->context->recordOperation([
                'type' => 'set',
                'slot' => $this->slotId,
                'value' => $this->normalizeRecordedValue($value),
            ]);
            return;
        }

        if ($value instanceof self) {
            $value = $value->get();
        }

        $this->value = $value;
    }
    
    protected function extractExpressionFromStack(): ?string
    {
        // Check if ActionRewriter set the expression in a variable before set() call
        // ActionRewriter sets $GLOBALS['__PALM_EXPR__'] before calling set()
        // We need to read it BEFORE unsetting, and only unset after we've read it
        if (isset($GLOBALS['__PALM_EXPR__'])) {
            $expr = $GLOBALS['__PALM_EXPR__'];
            // Don't unset here - let ActionRewriter clean it up after set() completes
            // This ensures the expression is available during the entire set() call
            return $expr;
        }
        
        return null;
    }
    
    /**
     * Extract expression from source code by parsing the call stack
     */
    protected function extractExpressionFromSource(): ?string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        // Look for the action callback in the stack
        foreach ($backtrace as $frame) {
            if (isset($frame['file']) && isset($frame['line'])) {
                $file = $frame['file'];
                $line = $frame['line'];
                
                // Check if this is a .palm.php file
                if (str_ends_with($file, '.palm.php') || str_ends_with($file, '.php')) {
                    // Try to read the line and parse the assignment
                    $lines = @file($file);
                    if ($lines && isset($lines[$line - 1])) {
                        $codeLine = trim($lines[$line - 1]);
                        
                        // Look for assignment patterns: $var = $var + 1, $var = !$var, etc.
                        $varName = $this->getVarName();
                        if ($varName !== null) {
                            // Pattern: $var = $var + N
                            if (preg_match('/\$' . preg_quote($varName, '/') . '\s*=\s*\$' . preg_quote($varName, '/') . '\s*\+\s*(\d+(?:\.\d+)?)/', $codeLine, $matches)) {
                                return '$' . $varName . '->get() + ' . $matches[1];
                            }
                            
                            // Pattern: $var = $var - N
                            if (preg_match('/\$' . preg_quote($varName, '/') . '\s*=\s*\$' . preg_quote($varName, '/') . '\s*-\s*(\d+(?:\.\d+)?)/', $codeLine, $matches)) {
                                return '$' . $varName . '->get() - ' . $matches[1];
                            }
                            
                            // Pattern: $var = !$var
                            if (preg_match('/\$' . preg_quote($varName, '/') . '\s*=\s*!\s*\$' . preg_quote($varName, '/') . '/', $codeLine)) {
                                return '!$' . $varName . '->get()';
                            }
                            
                            // Pattern: $var = $var * N, $var = $var / N, etc.
                            if (preg_match('/\$' . preg_quote($varName, '/') . '\s*=\s*\$' . preg_quote($varName, '/') . '\s*([*\/])\s*(\d+(?:\.\d+)?)/', $codeLine, $matches)) {
                                return '$' . $varName . '->get() ' . $matches[1] . ' ' . $matches[2];
                            }
                        }
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Detect common patterns in expressions and convert them to specific operations
     */
    protected function detectPatternOperation(string $expression): ?array
    {
        // Normalize expression - remove whitespace
        $expr = preg_replace('/\s+/', '', $expression);
        
        // Pattern: $var->get() + N -> increment(N)
        // Match: any variable name followed by ->get() + number
        if (preg_match('/\$(\w+)->get\(\)\+(\d+(?:\.\d+)?)$/', $expr, $matches)) {
            return [
                'type' => 'increment',
                'slot' => $this->slotId,
                'value' => (float)$matches[2],
            ];
        }
        
        // Pattern: $var->get() - N -> decrement(N)  
        // Match: any variable name followed by ->get() - number
        if (preg_match('/\$(\w+)->get\(\)-(\d+(?:\.\d+)?)$/', $expr, $matches)) {
            return [
                'type' => 'decrement',
                'slot' => $this->slotId,
                'value' => (float)$matches[2],
            ];
        }
        
        // Pattern: !$var->get() -> toggle
        // Match: ! followed by $var->get() where $var is any variable name
        if (preg_match('/^!\$(\w+)->get\(\)$/', $expr, $matches)) {
            return [
                'type' => 'toggle',
                'slot' => $this->slotId,
            ];
        }
        
        // Pattern: $var->get() * N -> multiply operation (record as expr)
        // Pattern: $var->get() / N -> divide operation (record as expr)
        // These are handled as expression operations
        
        return null;
    }
    
    /**
     * Detect patterns from the value itself when expression wasn't captured
     */
    protected function detectValuePattern(mixed $value): ?array
    {
        // This is a fallback - we can't reliably detect patterns from values alone
        // But we can try for simple cases
        return null;
    }
    
    protected function convertExpressionToJs(string $expr): string
    {
        // Build variable-to-slot mapping from context
        $varToSlotMap = $this->context->getVarToSlotMap();
        
        // Use PHPToJSExpressionCompiler for proper AST-based conversion
        if (class_exists(\PhpParser\ParserFactory::class) && !empty($varToSlotMap)) {
            try {
                $compiler = new PHPToJSExpressionCompiler($varToSlotMap);
                $result = $compiler->compile($expr);
                // Validate the result
                if (!empty($result) && trim($result) !== '') {
                    return $result;
                }
            } catch (\Throwable $e) {
                // Fallback to basic pattern matching if AST compilation fails
                error_log('PHPToJSExpressionCompiler error: ' . $e->getMessage() . ' for expression: ' . substr($expr, 0, 100));
            }
        }
        
        // Fallback: pattern-based replacement
        // Replace all $var->get() with state['slotId'].get()
        $jsExpr = $expr;
        foreach ($varToSlotMap as $varName => $slotId) {
            $pattern = '/\$' . preg_quote($varName, '/') . '\s*->\s*get\s*\(\s*\)/';
            $replacement = "state['{$slotId}'].get()";
            $jsExpr = preg_replace($pattern, $replacement, $jsExpr);
        }
        
        // If no mapping found, try to match any state variable pattern
        if ($jsExpr === $expr) {
            foreach ($this->context->getStates() as $slotId => $slot) {
                // Replace first occurrence of $var->get() pattern
                $pattern = '/\$(\w+)\s*->\s*get\s*\(\s*\)/';
                if (preg_match($pattern, $jsExpr)) {
                    $jsExpr = preg_replace($pattern, "state['{$slotId}'].get()", $jsExpr, 1);
                    break;
                }
            }
        }
        
        // Final validation - ensure the expression is not empty
        if (empty($jsExpr) || trim($jsExpr) === '') {
            error_log('Warning: Empty JavaScript expression generated from: ' . substr($expr, 0, 100));
            // Return a safe fallback
            return "state['{$this->slotId}'].get()";
        }
        
        return $jsExpr;
    }

    public function increment(int|float $step = 1): void
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'increment',
                'slot' => $this->slotId,
                'value' => $step,
            ]);
            // Still update the value during recording so subsequent operations see the updated value
            $this->value = ($this->value ?? 0) + $step;
            return;
        }

        $this->value = ($this->value ?? 0) + $step;
    }

    public function decrement(int|float $step = 1): void
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'decrement',
                'slot' => $this->slotId,
                'value' => $step,
            ]);
            // Still update the value during recording so subsequent operations see the updated value
            $this->value = ($this->value ?? 0) - $step;
            return;
        }

        $this->value = ($this->value ?? 0) - $step;
    }

    public function toggle(): void
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'toggle',
                'slot' => $this->slotId,
            ]);
            return;
        }

        $this->value = !$this->value;
    }

    /**
     * Push value to array state
     */
    public function push(mixed $value): void
    {
        if ($this->context->isRecording()) {
            if ($value instanceof ExpressionReference) {
                $jsExpr = $this->convertExpressionToJs($value->getExpression());
                $this->context->recordOperation([
                    'type' => 'push_expr',
                    'slot' => $this->slotId,
                    'expr' => $jsExpr,
                ]);
            } else {
                $this->context->recordOperation([
                    'type' => 'push',
                    'slot' => $this->slotId,
                    'value' => $this->normalizeRecordedValue($value),
                ]);
            }
            return;
        }

        if (!is_array($this->value)) {
            $this->value = [];
        }
        $this->value[] = $value;
    }

    /**
     * Pop value from array state
     */
    public function pop(): mixed
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'pop',
                'slot' => $this->slotId,
            ]);
            return null;
        }

        if (!is_array($this->value)) {
            return null;
        }
        return array_pop($this->value);
    }

    /**
     * Update array/object property
     */
    public function update(string|int $key, mixed $value): void
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'update',
                'slot' => $this->slotId,
                'key' => $key,
                'value' => $this->normalizeRecordedValue($value),
            ]);
            return;
        }

        if (!is_array($this->value) && !is_object($this->value)) {
            $this->value = [];
        }
        
        if (is_array($this->value)) {
            $this->value[$key] = $value;
        } elseif (is_object($this->value)) {
            $this->value->$key = $value;
        }
    }

    /**
     * Remove key from array/object
     */
    public function remove(string|int $key): void
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'remove',
                'slot' => $this->slotId,
                'key' => $key,
            ]);
            return;
        }

        if (is_array($this->value)) {
            unset($this->value[$key]);
        } elseif (is_object($this->value)) {
            unset($this->value->$key);
        }
    }

    /**
     * Merge array/object with new values
     */
    public function merge(array|object $values): void
    {
        if ($this->context->isRecording()) {
            $this->context->recordOperation([
                'type' => 'merge',
                'slot' => $this->slotId,
                'value' => $this->normalizeRecordedValue($values),
            ]);
            return;
        }

        if (!is_array($this->value) && !is_object($this->value)) {
            $this->value = [];
        }

        if (is_array($this->value) && is_array($values)) {
            $this->value = array_merge($this->value, $values);
        } elseif (is_object($this->value) && is_object($values)) {
            $this->value = (object)array_merge((array)$this->value, (array)$values);
        } elseif (is_array($this->value) && is_object($values)) {
            $this->value = array_merge($this->value, (array)$values);
        }
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function __toString(): string
    {
        // Ensure we have a value to display - convert null to empty string, but preserve 0
        $displayValue = $this->value;
        if ($displayValue === null) {
            $displayValue = '';
        }
        // Convert to string - important: 0 should display as "0", not empty
        $escaped = htmlspecialchars((string)$displayValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Use both prefixes for compatibility (psr = Palm Server Runtime)
        $componentId = $this->context->getId();
        $bindValue = $componentId . '::' . $this->slotId;
        $attributes = 'data-psr-bind="' . htmlspecialchars($bindValue, ENT_QUOTES, 'UTF-8') . '" data-palm-bind="' . htmlspecialchars($bindValue, ENT_QUOTES, 'UTF-8') . '"';
        if ($this->global && $this->globalKey) {
            $safeKey = htmlspecialchars($this->globalKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $attributes .= ' data-psr-scope="global" data-palm-scope="global" data-psr-key="' . $safeKey . '" data-palm-key="' . $safeKey . '"';
        }

        return '<span ' . $attributes . '>' . $escaped . '</span>';
    }

    public function token(): string
    {
        return $this->context->getId() . '::' . $this->slotId;
    }

    public function isComputed(): bool
    {
        return $this->isComputed;
    }

    public function setComputed(bool $computed): void
    {
        $this->isComputed = $computed;
    }

    /**
     * @param callable $fn
     */
    public function setComputeFunction($fn): void
    {
        $this->computeFunction = $fn;
    }

    /**
     * @return callable|null
     */
    public function getComputeFunction()
    {
        return $this->computeFunction;
    }

    public function setDependencies(array $deps): void
    {
        $this->dependencies = $deps;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Recompute computed state
     */
    public function recompute(): void
    {
        if ($this->isComputed && $this->computeFunction) {
            $newValue = ($this->computeFunction)();
            if ($this->value !== $newValue) {
                $this->value = $newValue;
            }
        }
    }
}


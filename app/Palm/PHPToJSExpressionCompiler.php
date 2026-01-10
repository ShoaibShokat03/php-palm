<?php

namespace Frontend\Palm;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\UnaryOp;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Compiles PHP expressions to JavaScript without using eval
 * Uses nikic/php-parser for AST parsing and transformation
 */
class PHPToJSExpressionCompiler
{
    protected array $stateVarMap = []; // Maps PHP variable names to JS slot IDs: ['$count' => 's0', ...]
    protected array $varToSlotMap = []; // Maps variable names (without $) to slot IDs: ['count' => 's0', ...]

    public function __construct(array $stateVarMap = [])
    {
        // Build both maps for efficient lookup
        foreach ($stateVarMap as $varName => $slotId) {
            $cleanVarName = ltrim($varName, '$');
            $this->stateVarMap['$' . $cleanVarName] = $slotId;
            $this->varToSlotMap[$cleanVarName] = $slotId;
        }
    }

    /**
     * Compile a PHP expression string to JavaScript
     * 
     * @param string $phpExpr PHP expression code (e.g., "$count->get() + 1" or "$count = $count + 20")
     * @return string JavaScript expression
     */
    public function compile(string $phpExpr): string
    {
        if (trim($phpExpr) === '') {
            return '';
        }

        // Check if PhpParser is available
        if (!class_exists(ParserFactory::class)) {
            return $this->fallbackCompile($phpExpr);
        }

        try {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            
            // Wrap expression in a statement context for parsing
            // Try different wrapping strategies
            $wrapped = $phpExpr;
            if (!preg_match('/;\s*$/', $phpExpr)) {
                $wrapped = $phpExpr . ';';
            }
            
            // Try parsing as expression statement
            $code = '<?php ' . $wrapped;
            $stmts = $parser->parse($code);
            
            if ($stmts === null || empty($stmts)) {
                // Fallback: try parsing as pure expression
                $code = '<?php return ' . $phpExpr . ';';
                $stmts = $parser->parse($code);
            }
            
            if ($stmts === null || empty($stmts)) {
                // Last resort: return original expression with basic replacements
                return $this->fallbackCompile($phpExpr);
            }

            $traverser = new NodeTraverser();
            $visitor = new ExpressionVisitor($this->varToSlotMap);
            $traverser->addVisitor($visitor);
            $transformed = $traverser->traverse($stmts);

            if (empty($transformed)) {
                return $this->fallbackCompile($phpExpr);
            }

            // Extract expression from statement
            $firstStmt = $transformed[0];
            if ($firstStmt instanceof Node\Stmt\Return_) {
                return $this->nodeToJs($firstStmt->expr);
            } elseif ($firstStmt instanceof Node\Stmt\Expression) {
                return $this->nodeToJs($firstStmt->expr);
            }

            return $this->fallbackCompile($phpExpr);
        } catch (\Throwable $e) {
            // On any error, fallback to basic pattern matching
            error_log('PHPToJSExpressionCompiler error: ' . $e->getMessage() . ' for expression: ' . substr($phpExpr, 0, 100));
            return $this->fallbackCompile($phpExpr);
        }
    }

    /**
     * Convert AST node to JavaScript code
     */
    protected function nodeToJs(?Node $node): string
    {
        if ($node === null) {
            return '';
        }

        if ($node instanceof Scalar\LNumber) {
            return (string)$node->value;
        }

        if ($node instanceof Scalar\DNumber) {
            return (string)$node->value;
        }

        if ($node instanceof Scalar\String_) {
            return json_encode($node->value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($node instanceof Scalar\String_) {
            return json_encode($node->value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($node instanceof Node\Expr\ConstFetch) {
            $name = $node->name->toString();
            if ($name === 'true') {
                return 'true';
            }
            if ($name === 'false') {
                return 'false';
            }
            if ($name === 'null') {
                return 'null';
            }
            return $name;
        }

        if ($node instanceof Node\Expr\Variable) {
            $varName = $node->name;
            if (is_string($varName)) {
                if ($varName === 'state') {
                    return 'state';
                }
                // Check if this is a state variable
                if (isset($this->varToSlotMap[$varName])) {
                    $slotId = $this->varToSlotMap[$varName];
                    return "state['{$slotId}'].get()";
                }
                // Regular variable - in JS context, this would be a parameter or local
                // For now, assume it's a parameter (action argument)
                return '$' . $varName;
            }
            // Dynamic variable name - can't compile statically
            return 'null';
        }

        if ($node instanceof Node\Expr\PropertyFetch) {
            // $obj->prop becomes obj.prop
            $obj = $this->nodeToJs($node->var);
            $prop = $node->name instanceof Node\Identifier ? $node->name->name : $this->nodeToJs($node->name);
            return "({$obj}).{$prop}";
        }

        if ($node instanceof Node\Expr\MethodCall) {
            // $obj->method() becomes obj.method()
            $obj = $this->nodeToJs($node->var);
            $method = $node->name instanceof Node\Identifier ? $node->name->name : $this->nodeToJs($node->name);
            $args = array_map([$this, 'nodeToJs'], $node->args);
            $argsStr = implode(', ', $args);
            
            // Special handling for StateSlot methods
            if ($method === 'get' && strpos($obj, "state[") === 0) {
                return "{$obj}.get()";
            }
            
            return "({$obj}).{$method}({$argsStr})";
        }

        if ($node instanceof BinaryOp\Plus) {
            return '(' . $this->nodeToJs($node->left) . ' + ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\Minus) {
            return '(' . $this->nodeToJs($node->left) . ' - ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\Mul) {
            return '(' . $this->nodeToJs($node->left) . ' * ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\Div) {
            return '(' . $this->nodeToJs($node->left) . ' / ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\Mod) {
            return '(' . $this->nodeToJs($node->left) . ' % ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\Concat) {
            return '(' . $this->nodeToJs($node->left) . ' + ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\Equal) {
            return '(' . $this->nodeToJs($node->left) . ' === ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\NotEqual) {
            return '(' . $this->nodeToJs($node->left) . ' !== ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\Identical) {
            return '(' . $this->nodeToJs($node->left) . ' === ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\NotIdentical) {
            return '(' . $this->nodeToJs($node->left) . ' !== ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\Smaller) {
            return '(' . $this->nodeToJs($node->left) . ' < ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\SmallerOrEqual) {
            return '(' . $this->nodeToJs($node->left) . ' <= ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\Greater) {
            return '(' . $this->nodeToJs($node->left) . ' > ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\GreaterOrEqual) {
            return '(' . $this->nodeToJs($node->left) . ' >= ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\BooleanAnd) {
            return '(' . $this->nodeToJs($node->left) . ' && ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof BinaryOp\BooleanOr) {
            return '(' . $this->nodeToJs($node->left) . ' || ' . $this->nodeToJs($node->right) . ')';
        }

        if ($node instanceof UnaryOp\Plus) {
            return '+' . $this->nodeToJs($node->expr);
        }

        if ($node instanceof UnaryOp\Minus) {
            return '-' . $this->nodeToJs($node->expr);
        }

        if ($node instanceof UnaryOp\Not) {
            return '!' . $this->nodeToJs($node->expr);
        }

        if ($node instanceof Node\Expr\Assign) {
            // Assignment: $var = expr
            $var = $node->var;
            if ($var instanceof Node\Expr\Variable && is_string($var->name)) {
                if (isset($this->varToSlotMap[$var->name])) {
                    $slotId = $this->varToSlotMap[$var->name];
                    $expr = $this->nodeToJs($node->expr);
                    return "state['{$slotId}'].set({$expr})";
                }
            }
            // Non-state assignment - convert to JS assignment
            $varJs = $this->nodeToJs($var);
            $exprJs = $this->nodeToJs($node->expr);
            return "({$varJs} = {$exprJs})";
        }

        if ($node instanceof AssignOp\Plus) {
            return $this->handleCompoundAssign($node, '+');
        }

        if ($node instanceof AssignOp\Minus) {
            return $this->handleCompoundAssign($node, '-');
        }

        if ($node instanceof AssignOp\Mul) {
            return $this->handleCompoundAssign($node, '*');
        }

        if ($node instanceof AssignOp\Div) {
            return $this->handleCompoundAssign($node, '/');
        }

        if ($node instanceof AssignOp\Mod) {
            return $this->handleCompoundAssign($node, '%');
        }

        if ($node instanceof AssignOp\Concat) {
            return $this->handleCompoundAssign($node, '+');
        }

        if ($node instanceof PostInc) {
            return $this->handleIncrement($node->var, 'post', 'inc');
        }

        if ($node instanceof PostDec) {
            return $this->handleIncrement($node->var, 'post', 'dec');
        }

        if ($node instanceof PreInc) {
            return $this->handleIncrement($node->var, 'pre', 'inc');
        }

        if ($node instanceof PreDec) {
            return $this->handleIncrement($node->var, 'pre', 'dec');
        }

        if ($node instanceof Node\Expr\Ternary) {
            $cond = $this->nodeToJs($node->cond);
            $ifTrue = $this->nodeToJs($node->if);
            $ifFalse = $this->nodeToJs($node->else);
            return "({$cond} ? {$ifTrue} : {$ifFalse})";
        }

        if ($node instanceof Node\Expr\NullsafeMethodCall) {
            // Nullsafe operator: $obj?->method() -> obj?.method()
            $obj = $this->nodeToJs($node->var);
            $method = $node->name instanceof Node\Identifier ? $node->name->name : $this->nodeToJs($node->name);
            $args = array_map([$this, 'nodeToJs'], $node->args);
            $argsStr = implode(', ', $args);
            return "({$obj})?.{$method}({$argsStr})";
        }

        if ($node instanceof Node\Expr\NullsafePropertyFetch) {
            // Nullsafe property: $obj?->prop -> obj?.prop
            $obj = $this->nodeToJs($node->var);
            $prop = $node->name instanceof Node\Identifier ? $node->name->name : $this->nodeToJs($node->name);
            return "({$obj})?.{$prop}";
        }

        if ($node instanceof Node\Expr\Coalesce) {
            // Null coalescing: $a ?? $b -> a ?? b
            $left = $this->nodeToJs($node->left);
            $right = $this->nodeToJs($node->right);
            return "({$left} ?? {$right})";
        }

        if ($node instanceof Node\Expr\Array_) {
            $items = [];
            foreach ($node->items as $item) {
                if ($item->key !== null) {
                    $key = $this->nodeToJs($item->key);
                    $value = $this->nodeToJs($item->value);
                    $items[] = "{$key}: {$value}";
                } else {
                    $items[] = $this->nodeToJs($item->value);
                }
            }
            return '{' . implode(', ', $items) . '}';
        }

        if ($node instanceof Node\Expr\ArrayDimFetch) {
            $var = $this->nodeToJs($node->var);
            $dim = $node->dim !== null ? $this->nodeToJs($node->dim) : '';
            return "({$var})[{$dim}]";
        }

        // Fallback for unknown node types
        return 'null';
    }

    protected function handleCompoundAssign(Node $node, string $op): string
    {
        $var = $node->var;
        if ($var instanceof Node\Expr\Variable && is_string($var->name)) {
            if (isset($this->varToSlotMap[$var->name])) {
                $slotId = $this->varToSlotMap[$var->name];
                $expr = $this->nodeToJs($node->expr);
                return "state['{$slotId}'].set(state['{$slotId}'].get() {$op} ({$expr}))";
            }
        }
        // Non-state compound assignment
        $varJs = $this->nodeToJs($var);
        $exprJs = $this->nodeToJs($node->expr);
        return "({$varJs} = {$varJs} {$op} {$exprJs})";
    }

    protected function handleIncrement(Node $var, string $position, string $type): string
    {
        if ($var instanceof Node\Expr\Variable && is_string($var->name)) {
            if (isset($this->varToSlotMap[$var->name])) {
                $slotId = $this->varToSlotMap[$var->name];
                $method = $type === 'inc' ? 'increment' : 'decrement';
                // For pre-increment/decrement: increment first, then return new value
                // For post-increment/decrement: get old value, increment, return old value
                if ($position === 'pre') {
                    return "(state['{$slotId}'].{$method}(), state['{$slotId}'].get())";
                } else {
                    // Post: get value, then increment, return old value
                    // Simplified to avoid syntax errors
                    return "(function() { const old = state['{$slotId}'].get(); state['{$slotId}'].{$method}(); return old; })()";
                }
            }
        }
        // Fallback for non-state variables
        $varJs = $this->nodeToJs($var);
        $op = $type === 'inc' ? '++' : '--';
        if ($position === 'pre') {
            return "({$op}{$varJs})";
        } else {
            return "({$varJs}{$op})";
        }
        // Non-state increment - convert to JS
        $varJs = $this->nodeToJs($var);
        if ($position === 'pre') {
            return $type === 'inc' ? "(++{$varJs})" : "(--{$varJs})";
        } else {
            return $type === 'inc' ? "({$varJs}++)" : "({$varJs}--)";
        }
    }

    /**
     * Fallback compilation using pattern matching when AST parsing fails
     */
    protected function fallbackCompile(string $phpExpr): string
    {
        $js = $phpExpr;
        
        // Replace state variable references: $var->get() with state['slotId'].get()
        foreach ($this->varToSlotMap as $varName => $slotId) {
            // Pattern: $var->get() - most common pattern
            $pattern = '/\$' . preg_quote($varName, '/') . '\s*->\s*get\s*\(\s*\)/';
            $replacement = "state['{$slotId}'].get()";
            $js = preg_replace($pattern, $replacement, $js);
            
            // Pattern: $var (standalone, not in method call or string)
            // More sophisticated: only replace if it's a variable reference, not in a string
            // Use word boundary to ensure we match complete variable names
            // Don't match if followed by ->, [, word char, or quote
            $pattern = '/(?<!\$)\$' . preg_quote($varName, '/') . '(?![\w\-\>\[\'"])/';
            $replacement = "state['{$slotId}'].get()";
            $js = preg_replace($pattern, $replacement, $js);
        }
        
        // Convert PHP operators to JS
        $js = str_replace(['===', '!=='], ['===', '!=='], $js); // Already JS-compatible
        $js = str_replace(['==', '!='], ['===', '!=='], $js); // PHP loose equality to JS strict
        
        // Handle PHP-specific operators
        $js = str_replace(['&&', '||'], ['&&', '||'], $js); // Already compatible
        $js = str_replace(['and', 'or'], ['&&', '||'], $js); // PHP logical operators
        
        // Handle null coalescing operator
        $js = preg_replace('/\?\?/', '??', $js); // Already compatible in modern JS
        
        return $js;
    }
}

/**
 * Node visitor to transform state variable references in expressions
 */
class ExpressionVisitor extends \PhpParser\NodeVisitorAbstract
{
    protected array $varToSlotMap;

    public function __construct(array $varToSlotMap)
    {
        $this->varToSlotMap = $varToSlotMap;
    }

    public function leaveNode(Node $node)
    {
        // Transform method calls like $var->get() to state['slotId'].get()
        if ($node instanceof Node\Expr\MethodCall) {
            if ($node->var instanceof Node\Expr\Variable && 
                $node->name instanceof Node\Identifier && 
                $node->name->name === 'get') {
                
                $varName = is_string($node->var->name) ? $node->var->name : null;
                if ($varName && isset($this->varToSlotMap[$varName])) {
                    $slotId = $this->varToSlotMap[$varName];
                    return new Node\Expr\MethodCall(
                        new Node\Expr\ArrayDimFetch(
                            new Node\Expr\Variable('state'),
                            new Scalar\String_($slotId)
                        ),
                        new Node\Identifier('get')
                    );
                }
            }
        }
        
        return null;
    }
}


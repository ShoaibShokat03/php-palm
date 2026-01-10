<?php

namespace Frontend\Palm;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter\Standard;

/**
 * AST-based PHP code transformer for .palm.php files
 * Transforms natural PHP into reactive Palm code using AST parsing
 */
class PHPCodeTransformer
{
    protected array $stateVars = []; // Track state variable names
    protected array $stateVarMap = []; // Map var name => slot ID
    protected bool $inAction = false;
    
    /**
     * Transform PHP code block to reactive Palm code
     * 
     * @param string $code Original PHP code
     * @return string Transformed PHP code
     */
    public function transform(string $code): string
    {
        if (trim($code) === '') {
            return $code;
        }
        
        // Check if PhpParser is available
        if (!class_exists(ParserFactory::class)) {
            // Fallback to regex-based transformation
            return $this->fallbackTransform($code);
        }
        
        try {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            
            // Wrap code to ensure it's parseable
            $wrappedCode = '<?php ' . $code;
            
            $stmts = $parser->parse($wrappedCode);
            if ($stmts === null || empty($stmts)) {
                return $this->fallbackTransform($code);
            }
            
            // First pass: detect State() and PalmState() declarations
            $this->detectStateDeclarations($stmts);
            
            if (empty($this->stateVars)) {
                // No state variables found, return as-is
                return $code;
            }
            
            // Second pass: transform the AST (includes closures)
            $traverser = new NodeTraverser();
            $visitor = new PHPTransformVisitor($this->stateVars);
            $traverser->addVisitor($visitor);
            // Also add ExpressionTransformVisitor to transform variable reads to ->get()
            $exprVisitor = new ExpressionTransformVisitor($this->stateVars);
            $traverser->addVisitor($exprVisitor);
            $transformed = $traverser->traverse($stmts);
            
            // Third pass: ensure closures are recursively transformed
            // The NodeTraverser should handle this, but we need to make sure
            // state variables from use clauses are available in closure bodies
            
            // Generate PHP code from transformed AST
            $printer = new Standard(['shortArraySyntax' => true]);
            $output = $printer->prettyPrint($transformed);
            
            // Remove the <?php prefix if we added it
            if (strpos($output, '<?php') === 0) {
                $output = substr($output, 5);
                $output = ltrim($output);
            }
            
            // Apply regex-based transformations as a safety net for any missed cases
            // This ensures closures and edge cases are transformed
            $output = $this->applyRegexTransformations($output, $this->stateVars);
            
            return $output;
        } catch (\Throwable $e) {
            error_log('PHPCodeTransformer error: ' . $e->getMessage() . ' for code: ' . substr($code, 0, 200));
            return $this->fallbackTransform($code);
        }
    }
    
    /**
     * First pass: detect all State() and PalmState() declarations
     */
    protected function detectStateDeclarations(array $stmts): void
    {
        $traverser = new NodeTraverser();
        $detector = new StateDeclarationDetector($this->stateVars);
        $traverser->addVisitor($detector);
        $traverser->traverse($stmts);
        $this->stateVars = $detector->getStateVars();
    }
    
    protected function getFunctionName(Expr\FuncCall $call): ?string
    {
        if ($call->name instanceof Node\Name) {
            return $call->name->getLast();
        }
        return null;
    }
    
    /**
     * Apply regex-based transformations as a safety net
     * This catches cases that AST transformation might miss (especially in closures)
     */
    protected function applyRegexTransformations(string $code, array $stateVars): string
    {
        if (empty($stateVars)) {
            return $code;
        }
        
        // Filter to only string/integer values for array_flip
        $filteredStateVars = array_filter($stateVars, function($var) {
            return is_string($var) || is_int($var);
        });
        
        if (empty($filteredStateVars)) {
            return $code;
        }
        
        $stateVarMap = array_flip($filteredStateVars);
        
        // Transform increment/decrement operators (these must happen first)
        foreach ($stateVarMap as $stateVar => $_) {
            // Transform $var++; or $var++ (with or without semicolon)
            $code = preg_replace(
                '/\$' . preg_quote($stateVar, '/') . '\s*\+\+(?=\s*;|$)/',
                '$' . $stateVar . '->increment()',
                $code
            );
            // Transform $var--; or $var--
            $code = preg_replace(
                '/\$' . preg_quote($stateVar, '/') . '\s*--(?=\s*;|$)/',
                '$' . $stateVar . '->decrement()',
                $code
            );
            // Transform ++$var (prefix, no semicolon needed)
            $code = preg_replace(
                '/\+\+\s*\$' . preg_quote($stateVar, '/') . '/',
                '$' . $stateVar . '->increment()',
                $code
            );
            // Transform --$var (prefix)
            $code = preg_replace(
                '/--\s*\$' . preg_quote($stateVar, '/') . '/',
                '$' . $stateVar . '->decrement()',
                $code
            );
            
            // Transform array append: $var[] = value -> $var->push(value)
            // Simple pattern: match $var[] = ... until semicolon
            // This will work for most cases, including complex expressions
            $code = preg_replace_callback(
                '/\$' . preg_quote($stateVar, '/') . '\s*\[\s*\]\s*=\s*([^;]+);/',
                function ($m) use ($stateVar) {
                    $value = trim($m[1]);
                    // Don't transform if already using ->push()
                    if (strpos($value, '->push(') !== false) {
                        return $m[0];
                    }
                    // The value expression is already correct (may contain ->get() calls)
                    return '$' . $stateVar . '->push(' . $value . ');';
                },
                $code
            );
        }
        
        return $code;
    }
    
    /**
     * Fallback regex-based transformation when AST parsing fails
     */
    protected function fallbackTransform(string $code): string
    {
        // Detect state variable declarations
        if (preg_match_all('/\$(\w+)\s*=\s*(?:State|PalmState)\(/', $code, $matches)) {
            $stateVars = array_unique($matches[1]);
            
            if (!empty($stateVars)) {
                // Apply regex transformations
                $code = $this->applyRegexTransformations($code, $stateVars);
                
                // Filter to only string/integer values for array_flip
                $filteredStateVars = array_filter($stateVars, function($var) {
                    return is_string($var) || is_int($var);
                });
                
                if (empty($filteredStateVars)) {
                    return $code;
                }
                
                $stateVarMap = array_flip($filteredStateVars);
                
                // Transform assignments to state variables
                $code = preg_replace_callback(
                    '/\$(\w+)\s*=\s*([^;]+);/',
                    function ($m) use ($stateVarMap) {
                        $var = $m[1];
                        $expr = trim($m[2]);
                        
                        if (!isset($stateVarMap[$var])) {
                            return $m[0];
                        }
                        
                        // CRITICAL: Skip initial State() or PalmState() assignments - these should NEVER be transformed
                        // Check for both simple and namespaced calls: State(), \State(), \Frontend\Palm\State(), etc.
                        // Also check for use function imports: when using 'use function Frontend\Palm\State;', it's just 'State'
                        if (preg_match('/^\s*(?:\\\\?[\\\\\w]*\\\\?)?(?:State|PalmState)\s*\(/', $expr) ||
                            preg_match('/\bState\s*\(/', $expr) ||
                            preg_match('/\bPalmState\s*\(/', $expr)) {
                            return $m[0]; // Keep original assignment unchanged
                        }
                        
                        // Skip if already transformed
                        if (strpos($expr, '->set(') !== false || strpos($expr, '->increment(') !== false || strpos($expr, '->decrement(') !== false) {
                            return $m[0];
                        }
                        
                        // Replace state variable references in expression with ->get()
                        // IMPORTANT: Only replace variables in the expression (right-hand side), never the left-hand side
                        foreach ($stateVarMap as $stateVar => $_) {
                            // Replace all occurrences of $stateVar in the expression with $stateVar->get()
                            // This includes the same variable if it appears on the right-hand side
                            $expr = preg_replace(
                                '/\$' . preg_quote($stateVar, '/') . '(?![\w\-\>\[\'"])/',
                                '$' . $stateVar . '->get()',
                                $expr
                            );
                        }
                        
                        return '$' . $var . '->set(' . $expr . ');';
                    },
                    $code
                );
            }
        }
        
        return $code;
    }
}

/**
 * AST Visitor to transform state variable operations
 */
class PHPTransformVisitor extends NodeVisitorAbstract
{
    protected array $stateVars;
    
    public function __construct(array $stateVars)
    {
        $this->stateVars = $stateVars;
    }
    
    public function leaveNode(Node $node)
    {
        // Transform assignments: $var = expr (where $var is a state variable)
        if ($node instanceof Expr\Assign) {
            if ($node->var instanceof Expr\Variable && 
                is_string($node->var->name) &&
                isset($this->stateVars[$node->var->name])) {
                
                // Skip if right side is State() or PalmState() call (including namespaced)
                // This is critical - we must NOT transform $var = State(...) assignments
                if ($node->expr instanceof Expr\FuncCall) {
                    // Check the function name directly
                    if ($node->expr->name instanceof Node\Name) {
                        $last = $node->expr->name->getLast();
                        $full = $node->expr->name->toString();
                        // Check if it's State or PalmState in any form (including imported functions)
                        // When using 'use function Frontend\Palm\State;', the name might be just 'State'
                        if ($last === 'State' || $last === 'PalmState' || 
                            $full === 'State' || $full === 'PalmState' ||
                            str_ends_with($full, '\\State') || str_ends_with($full, '\\PalmState') ||
                            $full === '\\Frontend\\Palm\\State' || $full === '\\Frontend\\Palm\\PalmState' ||
                            $full === 'Frontend\\Palm\\State' || $full === 'Frontend\\Palm\\PalmState' ||
                            preg_match('/State$/', $last) || preg_match('/PalmState$/', $last)) {
                            return null; // Don't transform initial declarations - return null to keep original
                        }
                    }
                }
                
                // Also check for StaticCall (namespaced function calls like \Frontend\Palm\State())
                if ($node->expr instanceof Expr\StaticCall) {
                    $method = $node->expr->name;
                    if (($method instanceof Node\Identifier && ($method->name === 'State' || $method->name === 'PalmState')) ||
                        (is_string($method) && ($method === 'State' || $method === 'PalmState'))) {
                        return null; // Don't transform initial declarations
                    }
                }
                
                // Additional safety: if the expression is a simple function call with 'State' or 'PalmState' in the name
                // This catches edge cases where the AST representation might be different
                if ($node->expr instanceof Expr\FuncCall) {
                    $exprStr = (string)$node->expr;
                    if (preg_match('/State\s*\(|PalmState\s*\(/', $exprStr)) {
                        return null; // Don't transform - likely a State() or PalmState() call
                    }
                }
                
                // Transform expression: replace state variable reads with ->get()
                $transformedExpr = $this->transformExpression($node->expr);
                
                // Create: $var->set(expr)
                return new Expr\MethodCall(
                    new Expr\Variable($node->var->name),
                    new Node\Identifier('set'),
                    [new Node\Arg($transformedExpr)]
                );
            }
        }
        
        // Transform compound assignments: $var += expr, $var -= expr, etc.
        if ($node instanceof Node\Expr\AssignOp\Plus ||
            $node instanceof Node\Expr\AssignOp\Minus ||
            $node instanceof Node\Expr\AssignOp\Mul ||
            $node instanceof Node\Expr\AssignOp\Div ||
            $node instanceof Node\Expr\AssignOp\Mod ||
            $node instanceof Node\Expr\AssignOp\Concat) {
            
            if ($node->var instanceof Expr\Variable &&
                is_string($node->var->name) &&
                isset($this->stateVars[$node->var->name])) {
                
                $opClass = $this->getAssignOpClass($node);
                $transformedExpr = $this->transformExpression($node->expr);
                $var = $node->var->name;
                $getCall = new Expr\MethodCall(
                    new Expr\Variable($var),
                    new Node\Identifier('get')
                );
                
                // Create: $var->set($var->get() op expr)
                $binaryOp = new $opClass($getCall, $transformedExpr);
                return new Expr\MethodCall(
                    new Expr\Variable($var),
                    new Node\Identifier('set'),
                    [new Node\Arg($binaryOp)]
                );
            }
        }
        
        // Transform increment/decrement: $var++, $var--, ++$var, --$var
        if ($node instanceof Expr\PostInc || $node instanceof Expr\PreInc) {
            if ($node->var instanceof Expr\Variable &&
                is_string($node->var->name) &&
                isset($this->stateVars[$node->var->name])) {
                
                return new Expr\MethodCall(
                    new Expr\Variable($node->var->name),
                    new Node\Identifier('increment')
                );
            }
        }
        
        if ($node instanceof Expr\PostDec || $node instanceof Expr\PreDec) {
            if ($node->var instanceof Expr\Variable &&
                is_string($node->var->name) &&
                isset($this->stateVars[$node->var->name])) {
                
                return new Expr\MethodCall(
                    new Expr\Variable($node->var->name),
                    new Node\Identifier('decrement')
                );
            }
        }
        
        // Transform array append: $items[] = value -> $items->push(value)
        if ($node instanceof Expr\Assign &&
            $node->var instanceof Expr\ArrayDimFetch &&
            $node->var->dim === null &&
            $node->var->var instanceof Expr\Variable &&
            is_string($node->var->var->name) &&
            isset($this->stateVars[$node->var->var->name])) {
            
            $varName = $node->var->var->name;
            $value = $this->transformExpression($node->expr);
            
            // Use push() method for array append
            return new Expr\MethodCall(
                new Expr\Variable($varName),
                new Node\Identifier('push'),
                [new Node\Arg($value)]
            );
        }
        
        // Transform array key assignment: $items[$key] = value -> $items->update($key, $value)
        if ($node instanceof Expr\Assign &&
            $node->var instanceof Expr\ArrayDimFetch &&
            $node->var->dim !== null &&
            $node->var->var instanceof Expr\Variable &&
            is_string($node->var->var->name) &&
            isset($this->stateVars[$node->var->var->name])) {
            
            $varName = $node->var->var->name;
            $key = $this->transformExpression($node->var->dim);
            $value = $this->transformExpression($node->expr);
            
            // Use update() method for array key assignment
            return new Expr\MethodCall(
                new Expr\Variable($varName),
                new Node\Identifier('update'),
                [
                    new Node\Arg($key),
                    new Node\Arg($value)
                ]
            );
        }
        
        // Transform closures to recursively transform their bodies
        // This handles both regular closures and arrow functions (fn() => expr)
        if ($node instanceof Expr\Closure) {
            // Create a new traverser with both this visitor (for assignments/operations)
            // and an ExpressionTransformVisitor (for transforming variable reads to ->get())
            $traverser = new NodeTraverser();
            $traverser->addVisitor($this); // Re-use this visitor recursively for assignments
            $exprVisitor = new ExpressionTransformVisitor($this->stateVars);
            $traverser->addVisitor($exprVisitor); // Transform variable reads to ->get()
            
            // Transform closure body (statements for regular closures, expressions for arrow functions)
            $transformedStmts = $traverser->traverse($node->stmts ?? []);
            
            // Create new closure with transformed statements
            $newClosure = clone $node;
            $newClosure->stmts = $transformedStmts;
            
            // For arrow functions (fn() => expr), the body is a single Expr node
            // The NodeTraverser will automatically handle it via ExpressionTransformVisitor
            // No special handling needed here - the expression will be transformed
            
            return $newClosure;
        }
        
        // Transform standalone state variable references to ->get()
        if ($node instanceof Expr\Variable &&
            is_string($node->name) &&
            isset($this->stateVars[$node->name])) {
            
            // Check if we're on the left-hand side of an assignment
            // If so, don't transform (it will be transformed by the assignment handler)
            $parent = $node->getAttribute('parent');
            if (!($parent instanceof Expr\Assign && $parent->var === $node) &&
                !($parent instanceof Node\Expr\AssignOp && $parent->var === $node)) {
                // This is a read, transform to ->get()
                return new Expr\MethodCall(
                    new Expr\Variable($node->name),
                    new Node\Identifier('get')
                );
            }
        }
        
        return null;
    }
    
    /**
     * Transform an expression: replace state variable reads with ->get()
     */
    protected function transformExpression(Expr $expr): Expr
    {
        $traverser = new NodeTraverser();
        $visitor = new ExpressionTransformVisitor($this->stateVars);
        $traverser->addVisitor($visitor);
        $transformed = $traverser->traverse([$expr]);
        return $transformed[0] ?? $expr;
    }
    
    protected function getAssignOpClass(Node\Expr\AssignOp $node): string
    {
        $class = get_class($node);
        if ($class === Node\Expr\AssignOp\Plus::class) return Expr\BinaryOp\Plus::class;
        if ($class === Node\Expr\AssignOp\Minus::class) return Expr\BinaryOp\Minus::class;
        if ($class === Node\Expr\AssignOp\Mul::class) return Expr\BinaryOp\Mul::class;
        if ($class === Node\Expr\AssignOp\Div::class) return Expr\BinaryOp\Div::class;
        if ($class === Node\Expr\AssignOp\Mod::class) return Expr\BinaryOp\Mod::class;
        if ($class === Node\Expr\AssignOp\Concat::class) return Expr\BinaryOp\Concat::class;
        return Expr\BinaryOp\Plus::class;
    }
    
    protected function getFunctionName(Expr\FuncCall $call): ?string
    {
        if ($call->name instanceof Node\Name) {
            return $call->name->getLast();
        }
        return null;
    }
}

/**
 * Visitor to detect State() and PalmState() declarations, including in closures
 */
class StateDeclarationDetector extends NodeVisitorAbstract
{
    protected array $stateVars = [];
    
    public function __construct(array $existingStateVars = [])
    {
        $this->stateVars = $existingStateVars;
    }
    
    public function getStateVars(): array
    {
        return $this->stateVars;
    }
    
    public function leaveNode(Node $node)
    {
        // Detect State() and PalmState() declarations
        if ($node instanceof Stmt\Expression && 
            $node->expr instanceof Expr\Assign &&
            $node->expr->var instanceof Expr\Variable &&
            is_string($node->expr->var->name)) {
            
            $right = $node->expr->expr;
            if ($right instanceof Expr\FuncCall) {
                // Check function name directly
                if ($right->name instanceof Node\Name) {
                    $last = $right->name->getLast();
                    $full = $right->name->toString();
                    if ($last === 'State' || $last === 'PalmState' || 
                        $full === 'State' || $full === 'PalmState' ||
                        str_ends_with($full, '\\State') || str_ends_with($full, '\\PalmState') ||
                        $full === '\\Frontend\\Palm\\State' || $full === '\\Frontend\\Palm\\PalmState' ||
                        $full === 'Frontend\\Palm\\State' || $full === 'Frontend\\Palm\\PalmState') {
                        $varName = $node->expr->var->name;
                        if (is_string($varName)) {
                            $this->stateVars[$varName] = true;
                        }
                    }
                }
            }
        }
        
        // When we encounter a closure, variables in use clauses should be available
        // in the closure body. If they're already detected as state vars, they'll
        // be transformed automatically by PHPTransformVisitor.
        // No special handling needed here since the visitor already has access to stateVars
        
        return null;
    }
    
    protected function getFunctionName(Expr\FuncCall $call): ?string
    {
        if ($call->name instanceof Node\Name) {
            return $call->name->getLast();
        }
        return null;
    }
}

/**
 * Visitor to transform state variable reads in expressions
 */
class ExpressionTransformVisitor extends NodeVisitorAbstract
{
    protected array $stateVars;
    
    public function __construct(array $stateVars)
    {
        $this->stateVars = $stateVars;
    }
    
    public function leaveNode(Node $node)
    {
        // Transform state variable reads to ->get() (but not on LHS of assignment)
        if ($node instanceof Expr\Variable &&
            is_string($node->name) &&
            isset($this->stateVars[$node->name])) {
            
            $parent = $node->getAttribute('parent');
            if (!($parent instanceof Expr\Assign && $parent->var === $node) &&
                !($parent instanceof Node\Expr\AssignOp && $parent->var === $node)) {
                return new Expr\MethodCall(
                    new Expr\Variable($node->name),
                    new Node\Identifier('get')
                );
            }
        }
        
        return null;
    }
}


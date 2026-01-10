<?php

namespace Frontend\Palm;

class ActionRewriter
{
    public static function rewrite(callable $callback): callable
    {
        if (!$callback instanceof \Closure) {
            return $callback;
        }

        $reflection = new \ReflectionFunction($callback);
        $staticVars = $reflection->getStaticVariables();

        $stateVarNames = [];
        foreach ($staticVars as $name => $value) {
            if ($value instanceof StateSlot) {
                $stateVarNames[] = $name;
            }
        }

        if (empty($stateVarNames)) {
            return $callback;
        }

        $code = self::extractClosureCode($reflection);
        if ($code === null) {
            // Check if code is already transformed (contains ->set() or ->increment() calls)
            // If so, the compilation already handled it and we can use the callback as-is
            $file = $reflection->getFileName();
            if ($file && is_file($file)) {
                $start = $reflection->getStartLine();
                $end = $reflection->getEndLine();
                if ($start && $end) {
                    $lines = file($file);
                    if ($lines) {
                        $snippet = array_slice($lines, $start - 1, $end - $start + 1);
                        $snippetCode = implode('', $snippet);
                        // Check if code appears to already be transformed
                        if (strpos($snippetCode, '->set(') !== false || 
                            strpos($snippetCode, '->increment(') !== false || 
                            strpos($snippetCode, '->decrement(') !== false ||
                            strpos($snippetCode, '->get()') !== false) {
                            // Code appears transformed, use as-is
                            return $callback;
                        }
                    }
                }
            }
            
            error_log('Palm ActionRewriter: Failed to extract closure code from file: ' . ($reflection->getFileName() ?: 'unknown') . ' at lines ' . ($reflection->getStartLine() ?: '?') . '-' . ($reflection->getEndLine() ?: '?'));
            
            // If we get here, extraction failed
            // Check if code appears to already be transformed before returning original
            return $callback;
        }
        
        // Check if code is already transformed
        if (strpos($code, '->set(') !== false || 
            strpos($code, '->increment(') !== false || 
            strpos($code, '->decrement(') !== false) {
            // Code is already transformed, return callback as-is
            return $callback;
        }

        $transformed = self::transformCode($code, $stateVarNames);
        if ($transformed === $code) {
            // Check if code actually needs transformation (contains operators on state vars)
            $needsTransform = false;
            foreach ($stateVarNames as $varName) {
                // Check for operators that need transformation
                $patterns = [
                    '/\$' . preg_quote($varName, '/') . '\s*(\+\+|--)/',  // ++ or --
                    '/\$' . preg_quote($varName, '/') . '\s*(\+=|-=|\*=|\/=)/',  // compound operators
                    '/\$' . preg_quote($varName, '/') . '\s*=\s*\$' . preg_quote($varName, '/') . '\s*[+\-*\/]/',  // $var = $var + 1
                ];
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $code)) {
                        $needsTransform = true;
                        break 2;
                    }
                }
            }
            
            if ($needsTransform && strpos($code, '->set(') === false && strpos($code, '->increment(') === false && strpos($code, '->decrement(') === false) {
                error_log('Palm ActionRewriter: Code needs transformation but transformation produced no changes!');
                error_log('Original code snippet: ' . substr($code, 0, 300));
                error_log('State variables: ' . implode(', ', $stateVarNames));
                // Don't return callback - it will fail. Instead try to manually transform
                // For now, return callback and let it fail with clear error
                return $callback;
            }
            return $callback;
        }

        // The transformation converts $count = $count + 1 to $count->set($count->get() + 1)
        // We need to actually use the transformed code at runtime
        // Use eval() to create a new closure from the transformed code
        try {
            // Extract the use clause variables - these are the StateSlot objects
            $useVars = [];
            $useVarValues = [];
            foreach ($staticVars as $name => $value) {
                $useVars[] = "\${$name}";
                $useVarValues[$name] = $value;
            }
            $useClause = !empty($useVars) ? 'use (' . implode(', ', $useVars) . ') ' : '';
            
            // Extract function parameters
            $params = [];
            foreach ($reflection->getParameters() as $param) {
                $paramStr = '';
                if ($param->hasType()) {
                    $type = $param->getType();
                    if ($type instanceof \ReflectionNamedType) {
                        $paramStr .= $type->getName() . ' ';
                    } elseif (is_string($type)) {
                        $paramStr .= $type . ' ';
                    }
                }
                $paramStr .= '$' . $param->getName();
                if ($param->isDefaultValueAvailable()) {
                    try {
                        $default = var_export($param->getDefaultValue(), true);
                        $paramStr .= ' = ' . $default;
                    } catch (\Throwable $e) {
                        // Skip default value if it can't be exported
                    }
                }
                $params[] = $paramStr;
            }
            $paramsStr = implode(', ', $params);
            
            // Extract the function body from transformed code
            // The transformed code should be: function(...) { transformed_body }
            $bodyStart = strpos($transformed, '{');
            $bodyEnd = strrpos($transformed, '}');
            if ($bodyStart !== false && $bodyEnd !== false && $bodyEnd > $bodyStart) {
                $body = substr($transformed, $bodyStart + 1, $bodyEnd - $bodyStart - 1);
                $body = trim($body);
                
                // Create new closure with transformed code
                // We need to bind the use variables properly
                $newCode = "return function({$paramsStr}) {$useClause}{ {$body} };";
                
                // Create a temporary function to evaluate the code with the use variables in scope
                $createClosure = function() use ($newCode, $useVarValues) {
                    // Import use variables into local scope
                    extract($useVarValues, EXTR_SKIP);
                    return eval($newCode);
                };
                
                $newClosure = $createClosure();
                
                if ($newClosure instanceof \Closure) {
                    return $newClosure;
                }
            }
        } catch (\Throwable $e) {
            // If eval fails, fall back to original callback
            error_log('Palm ActionRewriter eval failed: ' . $e->getMessage());
            error_log('Transformed code (first 500 chars): ' . substr($transformed, 0, 500));
            error_log('Original code (first 200 chars): ' . substr($code ?? '', 0, 200));
        }
        
        // Fallback: wrap the original callback to intercept assignments at runtime
        // Since eval() failed, we need to wrap the callback to catch assignments
        // and transform them on-the-fly during execution
        return self::wrapCallbackForRuntimeTransformation($callback, $stateVarNames, $staticVars);
    }

    protected static function extractClosureCode(\ReflectionFunction $reflection): ?string
    {
        $file = $reflection->getFileName();
        if ($file === false || !is_file($file)) {
            return null;
        }

        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();
        if ($start === false || $end === false) {
            return null;
        }

        // Check if this is eval'd code - if the file is Route.php or a cache file
        // For eval'd/compiled code, we can still try to extract from the compiled file
        $isEvalCode = (basename($file) === 'Route.php' && $start > 100) || 
                      strpos($file, 'cache') !== false ||
                      strpos($file, 'storage') !== false;
        
        // Even for compiled files, we can try to extract the code
        // The compiled PHP should have similar structure

        $lines = file($file);
        if ($lines === false) {
            return null;
        }

        $snippet = array_slice($lines, $start - 1, $end - $start + 1);
        $code = trim(implode('', $snippet));

        $position = strpos($code, 'function');
        if ($position === false) {
            return null;
        }

        $code = substr($code, $position);
        $firstBrace = strpos($code, '{');
        if ($firstBrace === false) {
            return null;
        }

        $depth = 0;
        $length = strlen($code);
        $endIndex = null;

        for ($i = $firstBrace; $i < $length; $i++) {
            $char = $code[$i];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $endIndex = $i;
                    break;
                }
            }
        }

        if ($endIndex === null) {
            return null;
        }

        return substr($code, 0, $endIndex + 1);
    }

    protected static function transformCode(string $code, array $stateVarNames): string
    {
        if (empty($stateVarNames)) {
            return $code;
        }

        $transformer = new ActionCodeTransformer($code, $stateVarNames);
        return $transformer->transform();
    }

    /**
     * Wrap callback for runtime transformation when code extraction/eval fails
     * This handles cases where the closure is in compiled/eval'd code
     * WARNING: This should not be called for code with operators on state variables
     */
    protected static function wrapCallbackForRuntimeTransformation(callable $callback, array $stateVarNames, array $staticVars): callable
    {
        // This fallback cannot handle operators like ++, --, +=, etc.
        // If we reach here, it means transformation failed
        // We should return the original callback and let it fail with a clear error
        error_log('Palm ActionRewriter: Code transformation failed. Operators on StateSlot objects (++, --, +=, etc.) will cause errors. Please ensure code extraction works or fix the source code.');
        
        // Return original callback - it will fail with operators but at least the error will be clear
        return $callback;
    }
}

class ActionCodeTransformer
{
    protected array $stateNames;
    protected array $tokens;
    protected string $output = '';
    protected int $index = 0;
    protected int $tokenCount = 0;
    protected int $braceDepth = 0;
    protected bool $bodyEntered = false;

    public function __construct(string $code, array $stateVarNames)
    {
        $this->stateNames = array_fill_keys($stateVarNames, true);
        $this->tokens = token_get_all('<?php ' . $code);
        if (!empty($this->tokens) && isset($this->tokens[0][0]) && $this->tokens[0][0] === T_OPEN_TAG) {
            array_shift($this->tokens);
        }
        $this->tokenCount = count($this->tokens);
    }

    public function transform(): string
    {
        for ($this->index = 0; $this->index < $this->tokenCount; $this->index++) {
            $token = $this->tokens[$this->index];
            $text = $this->tokenToString($token);

            if ($text === '{') {
                $this->braceDepth++;
                if (!$this->bodyEntered) {
                    $this->bodyEntered = true;
                }
                $this->output .= $text;
                continue;
            }

            if ($text === '}') {
                $this->braceDepth = max(0, $this->braceDepth - 1);
                $this->output .= $text;
                continue;
            }

            $inBody = $this->bodyEntered && $this->braceDepth > 0;

            if ($inBody) {
                if ($this->handlePrePostOperators()) {
                    continue;
                }
                
                if ($this->handleArrayOperations()) {
                    continue;
                }

                if ($this->handleMethodCalls()) {
                    continue;
                }

                if ($this->handleAssignments()) {
                    continue;
                }

                if ($this->handleStandaloneValue()) {
                    continue;
                }
            }

            $this->output .= $this->tokenToString($token);
        }

        return $this->output;
    }

    protected function handlePrePostOperators(): bool
    {
        $token = $this->tokens[$this->index];

        // Handle prefix operators: ++$var or --$var
        if ($this->isIncrementToken($token)) {
            $varIndex = $this->nextMeaningfulIndex($this->index + 1);
            if ($varIndex !== null && ($varName = $this->stateVariableName($this->tokens[$varIndex]))) {
                $op = $this->tokenToString($token) === '++' ? 'increment' : 'decrement';
                $this->output .= $varName . '->' . $op . '();';
                $this->index = $varIndex;
                return true;
            }
        }

        // Handle postfix operators: $var++ or $var--
        if (($varName = $this->stateVariableName($token))) {
            $next = $this->nextMeaningfulIndex($this->index + 1);
            if ($next !== null && $this->isIncrementToken($this->tokens[$next])) {
                $op = $this->tokenToString($this->tokens[$next]) === '++' ? 'increment' : 'decrement';
                $this->output .= $varName . '->' . $op . '();';
                $this->index = $next;
                return true;
            }
        }

        return false;
    }

    protected function handleAssignments(): bool
    {
        $token = $this->tokens[$this->index];
        if (!($varName = $this->stateVariableName($token))) {
            return false;
        }

        $operatorIndex = $this->nextMeaningfulIndex($this->index + 1);
        if ($operatorIndex === null) {
            return false;
        }

        $operatorToken = $this->tokens[$operatorIndex];
        $operator = $this->tokenToString($operatorToken);

        $compoundMap = [
            '+=' => fn(string $expr) => $varName . '->set(' . $varName . '->get() + (' . $expr . '));',
            '-=' => fn(string $expr) => $varName . '->set(' . $varName . '->get() - (' . $expr . '));',
            '*=' => fn(string $expr) => $varName . '->set(' . $varName . '->get() * (' . $expr . '));',
            '/=' => fn(string $expr) => $varName . '->set(' . $varName . '->get() / (' . $expr . '));',
            '%=' => fn(string $expr) => $varName . '->set(' . $varName . '->get() % (' . $expr . '));',
            '.=' => fn(string $expr) => $varName . '->set(' . $varName . '->get() . (' . $expr . '));',
        ];

        if ($operator === '=') {
            $replacement = $this->buildAssignment($operatorIndex + 1, $varName);
            if ($replacement !== null) {
                $this->output .= $replacement;
                return true;
            }
        } elseif (isset($compoundMap[$operator])) {
            $exprInfo = $this->collectExpressionTokens($operatorIndex + 1);
            if ($exprInfo !== null) {
                [$exprTokens, $endIndex] = $exprInfo;
                $expression = $this->renderExpression($exprTokens);
                $this->output .= $compoundMap[$operator]($expression);
                $this->index = $endIndex;
                return true;
            }
        }

        return false;
    }

    protected function handleMethodCalls(): bool
    {
        $token = $this->tokens[$this->index];
        $varName = $this->stateVariableName($token);
        if (!$varName) {
            return false;
        }

        // Check if next token is -> followed by a method call
        $arrowIndex = $this->nextMeaningfulIndex($this->index + 1);
        if ($arrowIndex === null) {
            return false;
        }

        $arrowToken = $this->tokens[$arrowIndex];
        if ($this->tokenToString($arrowToken) !== '->') {
            return false;
        }

        // Check for method name
        $methodIndex = $this->nextMeaningfulIndex($arrowIndex + 1);
        if ($methodIndex === null) {
            return false;
        }

        $methodToken = $this->tokens[$methodIndex];
        if (!is_array($methodToken) || $methodToken[0] !== T_STRING) {
            return false;
        }

        $methodName = $methodToken[1];
        // Only handle specific methods that need expression wrapping
        $methodsNeedingExpr = ['push', 'update', 'merge'];
        if (!in_array($methodName, $methodsNeedingExpr, true)) {
            return false;
        }

        // Check for opening parenthesis
        $parenIndex = $this->nextMeaningfulIndex($methodIndex + 1);
        if ($parenIndex === null) {
            return false;
        }

        $parenToken = $this->tokens[$parenIndex];
        if ($this->tokenToString($parenToken) !== '(') {
            return false;
        }

        // Collect argument expression until closing parenthesis
        $argTokens = [];
        $depth = 1; // Start at 1 because we're already inside the opening (
        $endIndex = $parenIndex + 1;
        
        for ($i = $parenIndex + 1; $i < $this->tokenCount; $i++) {
            $token = $this->tokens[$i];
            $text = $this->tokenToString($token);
            
            if ($text === '(' || $text === '[' || $text === '{') {
                $depth++;
            } elseif ($text === ')' || $text === ']' || $text === '}') {
                $depth--;
                if ($depth === 0) {
                    // Found the matching closing parenthesis
                    $endIndex = $i;
                    break;
                }
            }
            
            if ($depth > 0) {
                $argTokens[] = $token;
            }
        }
        
        if (empty($argTokens)) {
            // No arguments or empty arguments
            $argExpression = '';
        } else {
            $argExpression = $this->renderExpression($argTokens);
        }

        // Build replacement: wrap argument in ExpressionReference
        $encodedExpr = base64_encode($argExpression);
        $tempVar = '$__palmExprValue_' . bin2hex(random_bytes(3));
        $exprRefClass = '\\Frontend\\Palm\\ExpressionReference';
        
        // Evaluate expression for PHP runtime, but pass expression string for JS
        if (empty($argExpression)) {
            $replacement = "{$varName}->{$methodName}(new {$exprRefClass}(null, '', true))";
        } else {
            $replacement = "{$tempVar} = {$argExpression}; {$varName}->{$methodName}(new {$exprRefClass}({$tempVar}, '{$encodedExpr}', true))";
        }
        
        $this->output .= $replacement;
        // Skip the closing parenthesis and semicolon if present
        $this->index = $endIndex;
        return true;
    }

    protected function handleArrayOperations(): bool
    {
        $token = $this->tokens[$this->index];
        
        // Handle array_push($items, value)
        if (is_array($token) && $token[0] === T_STRING && $token[1] === 'array_push') {
            $parenIndex = $this->nextMeaningfulIndex($this->index + 1);
            if ($parenIndex !== null && $this->tokenToString($this->tokens[$parenIndex]) === '(') {
                $argIndex = $this->nextMeaningfulIndex($parenIndex + 1);
                if ($argIndex !== null) {
                    $varName = $this->stateVariableName($this->tokens[$argIndex]);
                    if ($varName) {
                        // Collect arguments: array_push($var, ...args)
                        $args = [];
                        $depth = 1;
                        $i = $parenIndex + 1;
                        $currentArg = [];
                        
                        while ($i < $this->tokenCount && $depth > 0) {
                            $t = $this->tokens[$i];
                            $text = $this->tokenToString($t);
                            
                            if ($text === '(') {
                                $depth++;
                                if ($depth > 1) {
                                    $currentArg[] = $t;
                                }
                            } elseif ($text === ')') {
                                $depth--;
                                if ($depth === 0) {
                                    break;
                                }
                                $currentArg[] = $t;
                            } elseif ($text === ',' && $depth === 1) {
                                if (!empty($currentArg)) {
                                    $args[] = $currentArg;
                                    $currentArg = [];
                                }
                            } else {
                                if ($depth === 1 && $text === $varName) {
                                    // Skip the state variable argument
                                    $i++;
                                    continue;
                                }
                                $currentArg[] = $t;
                            }
                            $i++;
                        }
                        
                        if (!empty($currentArg)) {
                            $args[] = $currentArg;
                        }
                        
                        // Transform to: $var->push(value)
                        $this->output .= $varName . '->push(';
                        foreach ($args as $argIdx => $argTokens) {
                            if ($argIdx > 0) {
                                $this->output .= ', ';
                            }
                            $this->output .= $this->renderExpression($argTokens);
                        }
                        $this->output .= ')';
                        
                        // Skip to the closing parenthesis
                        while ($i < $this->tokenCount) {
                            if ($this->tokenToString($this->tokens[$i]) === ';') {
                                $this->output .= ';';
                                $this->index = $i;
                                return true;
                            }
                            $i++;
                        }
                        $this->index = $i - 1;
                        return true;
                    }
                }
            }
        }
        
        // Handle unset($items[$key])
        if (is_array($token) && $token[0] === T_STRING && $token[1] === 'unset') {
            $parenIndex = $this->nextMeaningfulIndex($this->index + 1);
            if ($parenIndex !== null && $this->tokenToString($this->tokens[$parenIndex]) === '(') {
                $nextIndex = $this->nextMeaningfulIndex($parenIndex + 1);
                if ($nextIndex !== null) {
                    $varToken = $this->tokens[$nextIndex];
                    if ($this->stateVariableName($varToken)) {
                        // Check if it's array access: $var[$key]
                        $bracketIndex = $this->nextMeaningfulIndex($nextIndex + 1);
                        if ($bracketIndex !== null && $this->tokenToString($this->tokens[$bracketIndex]) === '[') {
                            // Get the key
                            $keyIndex = $this->nextMeaningfulIndex($bracketIndex + 1);
                            $closeBracketIndex = null;
                            $keyTokens = [];
                            $depth = 1;
                            
                            for ($i = $bracketIndex + 1; $i < $this->tokenCount; $i++) {
                                $t = $this->tokens[$i];
                                $text = $this->tokenToString($t);
                                
                                if ($text === '[') {
                                    $depth++;
                                    $keyTokens[] = $t;
                                } elseif ($text === ']') {
                                    $depth--;
                                    if ($depth === 0) {
                                        $closeBracketIndex = $i;
                                        break;
                                    }
                                    $keyTokens[] = $t;
                                } else {
                                    $keyTokens[] = $t;
                                }
                            }
                            
                            if ($closeBracketIndex !== null) {
                                $varName = $this->stateVariableName($varToken);
                                $keyExpr = $this->renderExpression($keyTokens);
                                
                                // Transform to: $var->remove($key)
                                $this->output .= $varName . '->remove(' . $keyExpr . ')';
                                
                                // Find closing parenthesis and semicolon
                                $closeParenIndex = $this->nextMeaningfulIndex($closeBracketIndex + 1);
                                if ($closeParenIndex !== null && $this->tokenToString($this->tokens[$closeParenIndex]) === ')') {
                                    $semicolonIndex = $this->nextMeaningfulIndex($closeParenIndex + 1);
                                    if ($semicolonIndex !== null && $this->tokenToString($this->tokens[$semicolonIndex]) === ';') {
                                        $this->output .= ';';
                                        $this->index = $semicolonIndex;
                                        return true;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return false;
    }

    protected function handleStandaloneValue(): bool
    {
        $token = $this->tokens[$this->index];
        $varName = $this->stateVariableName($token);
        if (!$varName) {
            return false;
        }

        $prev = $this->previousMeaningfulToken($this->index - 1);
        $next = $this->nextMeaningfulToken($this->index + 1);

        if ($this->isUseClause($prev)) {
            return false;
        }

        if ($this->isAccessContext($prev, $next)) {
            return false;
        }

        $this->output .= $varName . '->get()';
        return true;
    }

    protected function buildAssignment(int $startIndex, string $varName): ?string
    {
        $exprInfo = $this->collectExpressionTokens($startIndex);
        if ($exprInfo === null) {
            return null;
        }

        [$exprTokens, $endIndex] = $exprInfo;
        $expression = $this->renderExpression($exprTokens);

        if (trim($expression) === '') {
            $expression = 'null';
        }

        $this->index = $endIndex;
        
        $encodedExpr = base64_encode($expression);
        $tempVar = '$__palmExprValue_' . bin2hex(random_bytes(3));
        $exprRefClass = '\\Frontend\\Palm\\ExpressionReference';

        // Evaluate the expression once so PHP state stays accurate, but also pass the
        // expression string (encoded) for the recorder to build client-side ops.
        return "\$GLOBALS['__PALM_EXPR__'] = '{$encodedExpr}'; {$tempVar} = {$expression}; {$varName}->set(new {$exprRefClass}({$tempVar}, '{$encodedExpr}', true)); if (isset(\$GLOBALS['__PALM_EXPR__'])) unset(\$GLOBALS['__PALM_EXPR__']);";
    }

    /**
     * @return array{0:array<int, mixed>,1:int}|null
     */
    protected function collectExpressionTokens(int $startIndex): ?array
    {
        $tokens = [];
        $depth = 0;

        for ($i = $startIndex; $i < $this->tokenCount; $i++) {
            $token = $this->tokens[$i];
            $text = $this->tokenToString($token);

            if ($text === ';' && $depth === 0) {
                return [$tokens, $i];
            }

            $tokens[] = $token;

            if (in_array($text, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($text, [')', ']', '}'], true)) {
                $depth--;
            }
        }

        return null;
    }

    protected function renderExpression(array $exprTokens): string
    {
        $result = '';
        $count = count($exprTokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $exprTokens[$i];
            $varName = $this->stateVariableName($token);
            if (!$varName) {
                $result .= $this->tokenToString($token);
                continue;
            }

            $prev = $this->previousMeaningfulTokenInList($exprTokens, $i - 1);
            $next = $this->nextMeaningfulTokenInList($exprTokens, $i + 1);

            if ($this->isAccessContext($prev, $next)) {
                $result .= $varName;
                continue;
            }

            $result .= $varName . '->get()';
        }

        return trim($result);
    }

    /**
     * @param array|string|null $prev
     * @param array|string|null $next
     */
    protected function isAccessContext($prev, $next): bool
    {
        $prevText = $this->tokenToString($prev);
        $nextText = $this->tokenToString($next);

        if (in_array($prevText, ['->', '::'], true) || in_array($nextText, ['->', '::'], true)) {
            return true;
        }

        if ($nextText === '(') {
            return true;
        }

        if ($prevText === '$') {
            return true;
        }

        if ($nextText === '[') {
            return true;
        }

        return false;
    }

    /**
     * @param array|string|null $previous
     */
    protected function isUseClause($previous): bool
    {
        if (!is_array($previous)) {
            return false;
        }

        return $previous[0] === T_USE;
    }

    /**
     * @param array|string $token
     */
    protected function isIncrementToken($token): bool
    {
        $text = $this->tokenToString($token);
        return $text === '++' || $text === '--';
    }

    /**
     * @param array|string $token
     */
    protected function stateVariableName($token): ?string
    {
        if (!is_array($token) || $token[0] !== T_VARIABLE) {
            return null;
        }

        $name = substr($token[1], 1);
        if (!isset($this->stateNames[$name])) {
            return null;
        }

        return $token[1];
    }

    /**
     * @param array|string|null $token
     */
    protected function tokenToString($token): string
    {
        if ($token === null) {
            return '';
        }

        return is_array($token) ? $token[1] : $token;
    }

    protected function nextMeaningfulIndex(int $start): ?int
    {
        for ($i = $start; $i < $this->tokenCount; $i++) {
            $token = $this->tokens[$i];
            if ($this->isMeaningful($token)) {
                return $i;
            }
        }
        return null;
    }

    /**
     * @return array|string|null
     */
    protected function nextMeaningfulToken(int $start)
    {
        $index = $this->nextMeaningfulIndex($start);
        return $index === null ? null : $this->tokens[$index];
    }

    /**
     * @return array|string|null
     */
    protected function previousMeaningfulToken(int $start)
    {
        for ($i = $start; $i >= 0; $i--) {
            $token = $this->tokens[$i];
            if ($this->isMeaningful($token)) {
                return $token;
            }
        }
        return null;
    }

    /**
     * @param array<int, mixed> $tokens
     * @return array|string|null
     */
    protected function previousMeaningfulTokenInList(array $tokens, int $start)
    {
        for ($i = $start; $i >= 0; $i--) {
            if ($this->isMeaningful($tokens[$i])) {
                return $tokens[$i];
            }
        }
        return null;
    }

    /**
     * @param array<int, mixed> $tokens
     * @return array|string|null
     */
    protected function nextMeaningfulTokenInList(array $tokens, int $start)
    {
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            if ($this->isMeaningful($tokens[$i])) {
                return $tokens[$i];
            }
        }
        return null;
    }

    /**
     * @param array|string $token
     */
    protected function isMeaningful($token): bool
    {
        if (!is_array($token)) {
            return trim((string)$token) !== '';
        }

        return !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }

}


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
            return $callback;
        }

        $transformed = self::transformCode($code, $stateVarNames);
        if ($transformed === $code) {
            return $callback;
        }

        foreach ($staticVars as $varName => $varValue) {
            ${$varName} = $varValue;
        }

        try {
            /** @var callable $rewritten */
            $rewritten = eval('return ' . $transformed . ';');
            if ($rewritten instanceof \Closure) {
                return $rewritten;
            }
        } catch (\Throwable $exception) {
            // Ignore and fallback
        }

        return $callback;
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

        if ($this->isIncrementToken($token)) {
            $varIndex = $this->nextMeaningfulIndex($this->index + 1);
            if ($varIndex !== null && ($varName = $this->stateVariableName($this->tokens[$varIndex]))) {
                $this->output .= $varName . '->' . ($this->tokenToString($token) === '++' ? 'increment();' : 'decrement();');
                $this->index = $varIndex;
                return true;
            }
        }

        if (($varName = $this->stateVariableName($token))) {
            $next = $this->nextMeaningfulIndex($this->index + 1);
            if ($next !== null && $this->isIncrementToken($this->tokens[$next])) {
                $this->output .= $varName . '->' . ($this->tokenToString($this->tokens[$next]) === '++' ? 'increment();' : 'decrement();');
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
        return $varName . '->set(' . $expression . ');';
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


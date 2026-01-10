<?php

namespace Frontend\Palm;

/**
 * Lexer for .palm.php files
 * Tokenizes JSX-like PHP syntax into tokens for parsing
 */
class PalmLexer
{
    protected string $source;
    protected int $position = 0;
    protected int $line = 1;
    protected int $column = 1;
    protected array $tokens = [];

    public function __construct(string $source)
    {
        $this->source = $source;
    }

    public function tokenize(): array
    {
        $this->tokens = [];
        $this->position = 0;
        $this->line = 1;
        $this->column = 1;

        while ($this->position < strlen($this->source)) {
            $this->skipWhitespace();

            if ($this->position >= strlen($this->source)) {
                break;
            }

            $char = $this->source[$this->position];

            /* PHP tags - check for <?php, <?=, or <? */
            if ($char === '<' && $this->position + 1 < strlen($this->source)) {
                $nextChar = $this->source[$this->position + 1];
                if ($nextChar === '?') {
                    $this->tokenizePhpTag();
                    continue;
                }
                /* Check for closing tag </ before checking for opening tags */
                if ($nextChar === '/') {
                    $this->tokenizeClosingTag();
                    continue;
                }
                /* Check for DOCTYPE declaration <!DOCTYPE */
                if ($nextChar === '!' && $this->peek(9) === '<!DOCTYPE') {
                    $this->tokenizeDoctype();
                    continue;
                }
            }

            /* Opening tags */
            if ($char === '<' && $this->position + 1 < strlen($this->source) && preg_match('/^[a-zA-Z]/', $this->source[$this->position + 1])) {
                $this->tokenizeOpeningTag();
                continue;
            }

            // Expressions { } - but not inside style/script tags
            if ($char === '{' && !$this->isInsideStyleOrScript()) {
                $this->tokens[] = $this->createToken('LBRACE', '{');
                $this->advance();
                continue;
            }

            if ($char === '}' && !$this->isInsideStyleOrScript()) {
                $this->tokens[] = $this->createToken('RBRACE', '}');
                $this->advance();
                continue;
            }

            // Text content
            $oldPosition = $this->position;
            $this->tokenizeText();
            
            // Safety check: ensure we always advance
            if ($this->position === $oldPosition) {
                // If we didn't advance, skip this character to prevent infinite loop
                $this->advance();
            }
        }

        $this->tokens[] = $this->createToken('EOF', '');
        return $this->tokens;
    }

    protected function tokenizePhpTag(): void
    {
        $start = $this->position;
        
        // We're already at '<', check what comes after
        $peek5 = $this->peek(5);
        if ($peek5 === '<?php') {
            /* Full PHP tag: <?php */
            $this->advance(5);
            $this->tokens[] = $this->createToken('PHP_OPEN', '<?php', $start);
        } elseif ($this->peek(3) === '<?=') {
            /* Echo tag: <?= */
            $this->advance(3);
            $this->tokens[] = $this->createToken('PHP_ECHO', '<?=', $start);
        } elseif ($this->peek(2) === '<?') {
            /* Short tag: <? */
            $this->advance(2);
            $this->tokens[] = $this->createToken('PHP_OPEN', '<?', $start);
        } else {
            /* Fallback: just advance past '<' and treat as PHP_OPEN */
            $this->advance(1);
            $this->tokens[] = $this->createToken('PHP_OPEN', '<?', $start);
        }

        /* Read PHP code until closing tag */
        /* Need to handle ?> inside strings correctly */
        $code = '';
        $closePos = null;
        $inString = false;
        $stringChar = null;
        $escaped = false;
        
        while ($this->position < strlen($this->source)) {
            $char = $this->source[$this->position];
            
            if ($escaped) {
                $code .= $char;
                $this->advance();
                $escaped = false;
                continue;
            }
            
            if ($char === '\\' && $inString) {
                $escaped = true;
                $code .= $char;
                $this->advance();
                continue;
            }
            
            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
                $code .= $char;
                $this->advance();
                continue;
            }
            
            if ($inString && $char === $stringChar) {
                $inString = false;
                $stringChar = null;
                $code .= $char;
                $this->advance();
                continue;
            }
            
            /* Only treat ?> as closing tag if we're not inside a string */
            if (!$inString) {
                $peek = $this->peek(2);
                if ($peek === '?>') {
                    $closePos = $this->position;
                    $this->advance(2);
                    break;
                }
            }
            
            $code .= $char;
            $this->advance();
        }

        /*Always create PHP_CODE token, even if empty (for <?= ?> syntax)*/
        $this->tokens[] = $this->createToken('PHP_CODE', trim($code), $start);
        if ($closePos !== null) {
            $this->tokens[] = $this->createToken('PHP_CLOSE', '?>', $closePos);
        }
    }

    protected function tokenizeOpeningTag(): void
    {
        $start = $this->position;
        $this->advance(); // Skip <

        // Tag name
        $name = $this->readIdentifier();
        if ($name === '') {
            return;
        }

        $this->tokens[] = $this->createToken('TAG_OPEN', '<', $start);
        $this->tokens[] = $this->createToken('TAG_NAME', $name);

        // Attributes
        $this->skipWhitespace();
        while ($this->position < strlen($this->source) && $this->source[$this->position] !== '>' && $this->peek(1) !== '/') {
            $oldPos = $this->position;
            $this->tokenizeAttribute();
            $this->skipWhitespace();
            
            // Safety check: ensure we always advance
            if ($this->position === $oldPos) {
                // If we didn't advance, skip this character to prevent infinite loop
                $this->advance();
                $this->skipWhitespace();
            }
        }

        // Self-closing or closing
        if ($this->peek(1) === '/') {
            $this->advance(2); // Skip />
            $this->tokens[] = $this->createToken('TAG_SELF_CLOSE', '/>');
        } else {
            $this->advance(); // Skip >
            $this->tokens[] = $this->createToken('TAG_CLOSE', '>');
        }
    }

    protected function tokenizeClosingTag(): void
    {
        $start = $this->position;
        $this->advance(2); /* Skip </ */

        $name = $this->readIdentifier();
        if ($name === '') {
            return;
        }

        $this->skipWhitespace();
        $this->advance(); /* Skip > */

        $this->tokens[] = $this->createToken('TAG_CLOSE_OPEN', '</', $start);
        $this->tokens[] = $this->createToken('TAG_NAME', $name);
        $this->tokens[] = $this->createToken('TAG_CLOSE', '>');
    }

    protected function tokenizeDoctype(): void
    {
        /* Handle <!DOCTYPE html> declaration */
        $start = $this->position;
        /* Read until > */
        $doctype = '';
        while ($this->position < strlen($this->source)) {
            $char = $this->source[$this->position];
            $doctype .= $char;
            $this->advance();
            if ($char === '>') {
                break;
            }
        }
        /* Store as text token (DOCTYPE is special HTML, not a regular tag) */
        $this->tokens[] = $this->createToken('TEXT', $doctype, $start);
    }

    protected function tokenizeAttribute(): void
    {
        /* Skip whitespace at the start */
        $this->skipWhitespace();
        
        /* Check if we're at the end of tag */
        if ($this->position >= strlen($this->source) || $this->source[$this->position] === '>' || $this->peek(1) === '/') {
            return;
        }
        
        /* Check if attribute starts with <? (PHP tag - expression-only attribute) */
        if ($this->peek(2) === '<?') {
            /* Read PHP tag as expression-only attribute */
            $this->advance(2); /* Skip <? */
            
            /* Determine if it's <?= or <?php or <? */
            $phpCode = '';
            if ($this->source[$this->position] === '=') {
                /* Echo tag: <?= */
                $this->advance(1); /* Skip = */
                /* Read PHP code until ?> */
                while ($this->position < strlen($this->source)) {
                    $peek = $this->peek(2);
                    if ($peek === '?>') {
                        $this->advance(2);
                        break;
                    }
                    $phpCode .= $this->source[$this->position];
                    $this->advance();
                }
                /* Store as expression-only attribute */
                $this->tokens[] = $this->createToken('ATTR_NAME', '');
                $this->tokens[] = $this->createToken('ATTR_VALUE', '{' . trim($phpCode) . '}');
            } else {
                /* Regular PHP block - read until ?> */
                while ($this->position < strlen($this->source)) {
                    $peek = $this->peek(2);
                    if ($peek === '?>') {
                        $this->advance(2);
                        break;
                    }
                    $phpCode .= $this->source[$this->position];
                    $this->advance();
                }
                $this->tokens[] = $this->createToken('ATTR_NAME', '');
                $this->tokens[] = $this->createToken('ATTR_VALUE', '{' . trim($phpCode) . '}');
            }
            return;
        }
        
        /* Check if attribute starts with { (expression-only attribute, no name) */
        if ($this->source[$this->position] === '{') {
            $value = $this->readUnquotedExpression();
            $this->tokens[] = $this->createToken('ATTR_NAME', '');
            $this->tokens[] = $this->createToken('ATTR_VALUE', $value);
            return;
        }
        
        $name = $this->readIdentifier();
        if ($name === '') {
            return;
        }

        $this->tokens[] = $this->createToken('ATTR_NAME', $name);
        $this->skipWhitespace();

        if ($this->position < strlen($this->source) && $this->source[$this->position] === '=') {
            $this->advance(); // Skip =
            $this->skipWhitespace();

            if ($this->position < strlen($this->source)) {
                $quote = $this->source[$this->position];
                if ($quote === '"' || $quote === "'") {
                    $this->advance(); // Skip opening quote
                    $value = $this->readQuotedString($quote);
                    $this->advance(); // Skip closing quote
                    $this->tokens[] = $this->createToken('ATTR_VALUE', $value);
                } else {
                    /* Unquoted attribute value (e.g., in PHP expressions) */
                    /* Check if it starts with <? (PHP tag) */
                    if ($this->peek(2) === '<?') {
                        /* Read PHP tag as attribute value */
                        $this->advance(2); /* Skip <? */
                        
                        /* Determine if it's <?= or <?php or <? */
                        $phpCode = '';
                        if ($this->source[$this->position] === '=') {
                            /* Echo tag: <?= */
                            $this->advance(1); /* Skip = */
                            /* Read PHP code until ?> */
                            while ($this->position < strlen($this->source)) {
                                $peek = $this->peek(2);
                                if ($peek === '?>') {
                                    $this->advance(2);
                                    break;
                                }
                                $phpCode .= $this->source[$this->position];
                                $this->advance();
                            }
                            /* Store as expression attribute - if no name was set, this is expression-only */
                            if ($name === '') {
                                $this->tokens[] = $this->createToken('ATTR_NAME', '');
                            }
                            $this->tokens[] = $this->createToken('ATTR_VALUE', '{' . trim($phpCode) . '}');
                        } else {
                            /* Regular PHP block - read until ?> */
                            while ($this->position < strlen($this->source)) {
                                $peek = $this->peek(2);
                                if ($peek === '?>') {
                                    $this->advance(2);
                                    break;
                                }
                                $phpCode .= $this->source[$this->position];
                                $this->advance();
                            }
                            $this->tokens[] = $this->createToken('ATTR_VALUE', '{' . trim($phpCode) . '}');
                        }
                    } elseif ($this->source[$this->position] === '{') {
                        $value = $this->readUnquotedExpression();
                        $this->tokens[] = $this->createToken('ATTR_VALUE', $value);
                    } else {
                        $value = $this->readUnquotedAttribute();
                        $this->tokens[] = $this->createToken('ATTR_VALUE', $value);
                    }
                }
            }
        }
    }

    protected function tokenizeText(): void
    {
        $start = $this->position;
        $text = '';
        
        // Check if we're inside a style or script tag by looking back at recent tokens
        $isInStyleOrScript = $this->isInsideStyleOrScript();

        while ($this->position < strlen($this->source)) {
            $char = $this->source[$this->position];

            /* Check for PHP tags first (before treating as text) */
            /* Must check for PHP opening tags before treating as text */
            if ($char === '<' && $this->position + 1 < strlen($this->source)) {
                $nextChar = $this->source[$this->position + 1];
                if ($nextChar === '?') {
                    /* Found a PHP tag, stop reading text */
                    break;
                }
                /* Check for closing tag </ */
                if ($nextChar === '/') {
                    /* Found a closing tag, stop reading text (don't include < in text) */
                    break;
                }
            }

            /* Inside style/script tags, only stop at < (for closing tag) */
            /* Outside, stop at <, {, or } (for expressions) */
            if ($char === '<') {
                break;
            }
            
            if (!$isInStyleOrScript && ($char === '{' || $char === '}')) {
                break;
            }

            $text .= $char;
            $this->advance();
        }

        // Always advance at least one character if we're not at the end
        if ($text === '' && $this->position < strlen($this->source)) {
            $text = $this->source[$this->position];
            $this->advance();
        }

        if ($text !== '') {
            $this->tokens[] = $this->createToken('TEXT', $text, $start);
        }
    }
    
    /**
     * Check if we're currently inside a <style> or <script> tag
     * by looking at recent tokens
     */
    protected function isInsideStyleOrScript(): bool
    {
        // Look back through tokens to find the most recent opening tag
        $count = count($this->tokens);
        for ($i = $count - 1; $i >= 0; $i--) {
            $token = $this->tokens[$i];
            if ($token['type'] === 'TAG_NAME') {
                $tagName = strtolower($token['value']);
                if ($tagName === 'style' || $tagName === 'script') {
                    // Check if this was an opening tag (not closing)
                    // Look for TAG_CLOSE after this tag name
                    for ($j = $i + 1; $j < $count; $j++) {
                        $nextToken = $this->tokens[$j];
                        if ($nextToken['type'] === 'TAG_CLOSE') {
                            return true; // We're inside a style/script tag
                        }
                        if ($nextToken['type'] === 'TAG_OPEN' || $nextToken['type'] === 'TAG_SELF_CLOSE') {
                            break; // Found another tag, stop looking
                        }
                    }
                }
                break; // Found a tag name, stop looking back
            }
        }
        return false;
    }

    protected function readIdentifier(): string
    {
        $ident = '';
        
        while ($this->position < strlen($this->source)) {
            $char = $this->source[$this->position];
            
            if (preg_match('/[a-zA-Z0-9_\-:]/', $char)) {
                $ident .= $char;
                $this->advance();
            } else {
                break;
            }
        }

        return $ident;
    }

    protected function readQuotedString(string $quote): string
    {
        /* Read quoted string, handling PHP tags inside the string */
        $value = '';
        $escaped = false;
        
        while ($this->position < strlen($this->source)) {
            $char = $this->source[$this->position];
            
            if ($escaped) {
                $value .= $char;
                $this->advance();
                $escaped = false;
                continue;
            }
            
            if ($char === '\\') {
                $escaped = true;
                $value .= $char;
                $this->advance();
                continue;
            }
            
            /* Check for PHP tags inside the quoted string */
            if ($char === '<' && $this->position + 1 < strlen($this->source) && $this->source[$this->position + 1] === '?') {
                /* Found PHP tag inside quoted string - read it as part of the value */
                /* Read until ?> */
                $phpStart = $this->position;
                $this->advance(2); /* Skip <? */
                
                /* Determine if it's <?= or <?php or <? */
                $peek3 = $this->peek(3);
                if ($peek3 === '<?=') {
                    $value .= '<?=';
                    $this->advance(1); /* Skip = */
                } elseif ($this->peek(5) === '<?php') {
                    $value .= '<?php';
                    $this->advance(3); /* Skip php */
                } else {
                    $value .= '<?';
                }
                
                /* Read PHP code until ?> */
                while ($this->position < strlen($this->source)) {
                    $peek = $this->peek(2);
                    if ($peek === '?>') {
                        $value .= '?>';
                        $this->advance(2);
                        break;
                    }
                    $value .= $this->source[$this->position];
                    $this->advance();
                }
                continue;
            }
            
            if ($char === $quote) {
                break;
            }
            
            $value .= $char;
            $this->advance();
        }

        return $value;
    }

    protected function readUnquotedAttribute(): string
    {
        $value = '';
        
        while ($this->position < strlen($this->source)) {
            $char = $this->source[$this->position];
            
            if (in_array($char, [' ', "\t", "\n", "\r", '>', '/'], true)) {
                break;
            }
            
            $value .= $char;
            $this->advance();
        }

        return trim($value);
    }

    protected function readUnquotedExpression(): string
    {
        $value = '';
        $depth = 0;
        $started = false;
        
        while ($this->position < strlen($this->source)) {
            $char = $this->source[$this->position];
            
            if ($char === '{') {
                $depth++;
                $started = true;
            } elseif ($char === '}') {
                $depth--;
                $value .= $char;
                $this->advance();
                if ($depth === 0 && $started) {
                    break;
                }
                continue;
            } elseif ($depth === 0 && in_array($char, [' ', "\t", "\n", "\r", '>', '/'], true)) {
                // If we haven't started an expression yet, stop at whitespace
                if (!$started) {
                    break;
                }
            }
            
            $value .= $char;
            $this->advance();
        }

        return trim($value);
    }

    protected function skipWhitespace(): void
    {
        while ($this->position < strlen($this->source)) {
            $char = $this->source[$this->position];
            
            if ($char === "\n") {
                $this->line++;
                $this->column = 1;
                $this->advance();
            } elseif (in_array($char, [" ", "\t", "\r"], true)) {
                $this->column++;
                $this->advance();
            } else {
                break;
            }
        }
    }

    protected function peek(int $length = 1): string
    {
        if ($length === 0) {
            return '';
        }
        
        $pos = $this->position;
        if ($pos >= strlen($this->source)) {
            return '';
        }
        
        if ($pos + $length > strlen($this->source)) {
            return substr($this->source, $pos);
        }
        
        return substr($this->source, $pos, $length);
    }

    protected function advance(int $count = 1): void
    {
        $this->position += $count;
        $this->column += $count;
    }

    protected function createToken(string $type, string $value, ?int $start = null): array
    {
        return [
            'type' => $type,
            'value' => $value,
            'line' => $this->line,
            'column' => $start !== null ? ($start - $this->position + $this->column) : $this->column,
            'start' => $start ?? $this->position,
            'end' => $this->position,
        ];
    }
}

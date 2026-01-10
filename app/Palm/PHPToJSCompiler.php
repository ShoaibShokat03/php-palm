<?php

namespace Frontend\Palm;

class PHPToJSCompiler
{
    private $jsCode = '';
    private $indentLevel = 0;
    private $variables = [];

    // Built-in PHP browser functions that compile to JS
    private $builtinFunctions = [
        'alert' => 'alert',
        'console_log' => 'console.log',
        'prompt' => 'prompt',
        'confirm' => 'confirm',
        'set_timeout' => 'setTimeout',
        'set_interval' => 'setInterval',
        'clear_timeout' => 'clearTimeout',
        'clear_interval' => 'clearInterval',
        'parse_int' => 'parseInt',
        'parse_float' => 'parseFloat',
        'json_encode' => 'JSON.stringify',
        'json_decode' => 'JSON.parse',
    ];

    // DOM manipulation functions
    private $domFunctions = [
        'get_element_by_id' => 'document.getElementById',
        'get_elements_by_class' => 'document.getElementsByClassName',
        'get_elements_by_tag' => 'document.getElementsByTagName',
        'query_selector' => 'document.querySelector',
        'query_selector_all' => 'document.querySelectorAll',
        'create_element' => 'document.createElement',
    ];

    public function compile($phpCode)
    {
        $this->jsCode = '';
        $this->indentLevel = 0;
        $this->variables = [];

        // Remove PHP tags
        $phpCode = preg_replace('/<\?php\s*/', '', $phpCode);
        $phpCode = preg_replace('/\?>\s*$/', '', $phpCode);

        // Tokenize and parse
        $lines = explode("\n", $phpCode);
        $i = 0;
        while ($i < count($lines)) {
            $line = trim($lines[$i]);
            if (!empty($line)) {
                $this->compileLine($line);
            }
            $i++;
        }

        return $this->jsCode;
    }

    private function indent()
    {
        return str_repeat('  ', $this->indentLevel);
    }

    private function addLine($code)
    {
        $this->jsCode .= $this->indent() . $code . "\n";
    }

    private function compileLine($line)
    {
        // Skip empty lines and comments
        if (empty($line)) return;

        // Single-line comments
        if (preg_match('/^\/\//', $line)) {
            $this->addLine($line);
            return;
        }

        // Multi-line comments
        if (preg_match('/^\/\*/', $line)) {
            $this->addLine($line);
            return;
        }

        // Function declaration
        if (preg_match('/^function\s+(\w+)\s*\((.*?)\)\s*\{?\s*$/', $line, $matches)) {
            $funcName = $matches[1];
            $params = $this->convertParameters($matches[2]);
            $this->addLine("function $funcName($params) {");
            $this->indentLevel++;
            return;
        }

        // Closing brace
        if (preg_match('/^\}\s*$/', $line)) {
            $this->indentLevel--;
            $this->addLine("}");
            return;
        }

        // Variable assignment with function call
        if (preg_match('/^\$(\w+)\s*=\s*(\w+)\s*\((.*?)\)\s*;/', $line, $matches)) {
            $varName = $matches[1];
            $funcName = $matches[2];
            $args = $matches[3];

            // Check if it's a DOM function
            if (isset($this->domFunctions[$funcName])) {
                $jsFunc = $this->domFunctions[$funcName];
                $convertedArgs = $this->convertArguments($args);
                $this->addLine("let $varName = $jsFunc($convertedArgs);");
                $this->variables[$varName] = true;
                return;
            }

            // Check if it's a built-in function
            if (isset($this->builtinFunctions[$funcName])) {
                $jsFunc = $this->builtinFunctions[$funcName];
                $convertedArgs = $this->convertArguments($args);
                $this->addLine("let $varName = $jsFunc($convertedArgs);");
                $this->variables[$varName] = true;
                return;
            }

            // Regular function call
            $convertedArgs = $this->convertArguments($args);
            $this->addLine("let $varName = $funcName($convertedArgs);");
            $this->variables[$varName] = true;
            return;
        }

        // Variable assignment with property access
        if (preg_match('/^\$(\w+)\s*=\s*\$(\w+)->(\w+)\s*;/', $line, $matches)) {
            $varName = $matches[1];
            $objName = $matches[2];
            $property = $matches[3];
            $this->addLine("let $varName = $objName.$property;");
            $this->variables[$varName] = true;
            return;
        }

        // Simple variable assignment
        if (preg_match('/^\$(\w+)\s*=\s*(.+?);/', $line, $matches)) {
            $varName = $matches[1];
            $value = $this->convertValue($matches[2]);
            $this->addLine("let $varName = $value;");
            $this->variables[$varName] = true;
            return;
        }

        // Property assignment ($obj->prop = value)
        if (preg_match('/^\$(\w+)->(\w+)\s*=\s*(.+?);/', $line, $matches)) {
            $varName = $matches[1];
            $property = $matches[2];
            $value = $this->convertValue($matches[3]);
            $this->addLine("$varName.$property = $value;");
            return;
        }

        // Method call on object ($obj->method(args))
        if (preg_match('/^\$(\w+)->(\w+)\s*\((.*?)\);/', $line, $matches)) {
            $varName = $matches[1];
            $method = $matches[2];
            $args = $matches[3];
            $convertedArgs = $this->convertArguments($args);
            $this->addLine("$varName.$method($convertedArgs);");
            return;
        }

        // Standalone function call
        if (preg_match('/^(\w+)\s*\((.*?)\);/', $line, $matches)) {
            $funcName = $matches[1];
            $args = $matches[2];

            // Check if it's a built-in function
            if (isset($this->builtinFunctions[$funcName])) {
                $jsFunc = $this->builtinFunctions[$funcName];
                $convertedArgs = $this->convertArguments($args);
                $this->addLine("$jsFunc($convertedArgs);");
                return;
            }

            // Check if it's a DOM function
            if (isset($this->domFunctions[$funcName])) {
                $jsFunc = $this->domFunctions[$funcName];
                $convertedArgs = $this->convertArguments($args);
                $this->addLine("$jsFunc($convertedArgs);");
                return;
            }

            // Regular function call
            $convertedArgs = $this->convertArguments($args);
            $this->addLine("$funcName($convertedArgs);");
            return;
        }

        // If statement
        if (preg_match('/^if\s*\((.*?)\)\s*\{?\s*$/', $line, $matches)) {
            $condition = $this->convertCondition($matches[1]);
            $this->addLine("if ($condition) {");
            $this->indentLevel++;
            return;
        }

        // Else if
        if (preg_match('/^}\s*else\s+if\s*\((.*?)\)\s*\{?\s*$/', $line, $matches)) {
            $this->indentLevel--;
            $condition = $this->convertCondition($matches[1]);
            $this->addLine("} else if ($condition) {");
            $this->indentLevel++;
            return;
        }

        // Else
        if (preg_match('/^}\s*else\s*\{?\s*$/', $line)) {
            $this->indentLevel--;
            $this->addLine("} else {");
            $this->indentLevel++;
            return;
        }

        // For loop
        if (preg_match('/^for\s*\((.*?)\)\s*\{?\s*$/', $line, $matches)) {
            $loopParts = $this->convertForLoop($matches[1]);
            $this->addLine("for ($loopParts) {");
            $this->indentLevel++;
            return;
        }

        // While loop
        if (preg_match('/^while\s*\((.*?)\)\s*\{?\s*$/', $line, $matches)) {
            $condition = $this->convertCondition($matches[1]);
            $this->addLine("while ($condition) {");
            $this->indentLevel++;
            return;
        }

        // Return statement
        if (preg_match('/^return\s+(.+?);/', $line, $matches)) {
            $value = $this->convertValue($matches[1]);
            $this->addLine("return $value;");
            return;
        }

        // Return without value
        if (preg_match('/^return\s*;/', $line)) {
            $this->addLine("return;");
            return;
        }
    }

    private function convertParameters($params)
    {
        if (empty($params)) return '';

        // Remove $ from parameter names
        $params = preg_replace('/\$(\w+)/', '$1', $params);
        return $params;
    }

    private function convertValue($value)
    {
        $value = trim($value);

        // Convert PHP variables to JS variables (remove $)
        $value = preg_replace('/\$(\w+)/', '$1', $value);

        // Convert string concatenation (. to +)
        // But be careful with decimals
        $value = preg_replace_callback('/("[^"]*"|\'[^\']*\'|\w+)\s*\.\s*("[^"]*"|\'[^\']*\'|\w+)/', function ($matches) {
            return $matches[1] . ' + ' . $matches[2];
        }, $value);

        // Convert array() to []
        $value = preg_replace('/array\s*\((.*?)\)/', '[$1]', $value);

        // Convert true/false
        $value = preg_replace('/\btrue\b/', 'true', $value);
        $value = preg_replace('/\bfalse\b/', 'false', $value);

        return $value;
    }

    private function convertArguments($args)
    {
        if (empty(trim($args))) return '';

        // Handle function expressions
        if (preg_match('/function\s*\(/', $args)) {
            // It's a function expression, handle specially
            return $this->convertFunctionExpression($args);
        }

        // Split arguments carefully
        $parts = $this->smartSplit($args);
        $converted = [];

        foreach ($parts as $part) {
            $converted[] = $this->convertValue($part);
        }

        return implode(', ', $converted);
    }

    private function convertFunctionExpression($expr)
    {
        // Convert function() to function()
        $expr = preg_replace('/\$(\w+)/', '$1', $expr);
        return $expr;
    }

    private function smartSplit($str)
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inQuote = false;
        $quoteChar = '';

        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];

            // Handle quotes
            if (($char === '"' || $char === "'") && ($i === 0 || $str[$i - 1] !== '\\')) {
                if (!$inQuote) {
                    $inQuote = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuote = false;
                }
            }

            if (!$inQuote) {
                if ($char === '(' || $char === '[' || $char === '{') $depth++;
                if ($char === ')' || $char === ']' || $char === '}') $depth--;

                if ($char === ',' && $depth === 0) {
                    $parts[] = trim($current);
                    $current = '';
                    continue;
                }
            }

            $current .= $char;
        }

        if (!empty(trim($current))) {
            $parts[] = trim($current);
        }

        return $parts;
    }

    private function convertCondition($condition)
    {
        // Convert PHP variables to JS
        $condition = preg_replace('/\$(\w+)/', '$1', $condition);

        // Convert PHP comparison operators
        $condition = preg_replace('/\s+==\s+/', ' === ', $condition);
        $condition = preg_replace('/\s+!=\s+/', ' !== ', $condition);

        // Handle empty string comparison
        $condition = str_replace('""', '""', $condition);

        return $condition;
    }

    private function convertForLoop($loop)
    {
        // Convert $i to i in for loop
        $loop = preg_replace('/\$(\w+)/', '$1', $loop);

        // Handle initialization with let
        $parts = explode(';', $loop);
        if (count($parts) >= 1) {
            $init = trim($parts[0]);
            if (preg_match('/^(\w+)\s*=/', $init, $matches)) {
                $parts[0] = 'let ' . $init;
            }
        }

        return implode('; ', $parts);
    }

    public function getJavaScript()
    {
        return $this->jsCode;
    }
}

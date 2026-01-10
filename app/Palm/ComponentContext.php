<?php

namespace Frontend\Palm;

class ComponentContext
{
    protected string $id;
    /** @var StateSlot[] */
    protected array $states = [];
    protected array $actions = [];
    /** @var Effect[] */
    protected array $effects = [];
    protected ?string $recordingAction = null;
    protected array $currentOperations = [];
    protected ?string $currentExpression = null; // Current expression being evaluated (from ActionRewriter)
    /** @var array<string, string> Maps variable names (without $) to slot IDs */
    protected array $varToSlotMap = [];
    protected array $lifecycleHooks = [];

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function createState(mixed $initial = null, bool $global = false, ?string $globalKey = null, ?string $varName = null): StateSlot
    {
        $slotId = 's' . count($this->states);
        $slot = new StateSlot($this, $slotId, $initial, $global, $globalKey);
        $this->states[$slotId] = $slot;
        
        // Track variable name if provided (for expression compilation)
        if ($varName !== null) {
            $this->varToSlotMap[ltrim($varName, '$')] = $slotId;
            $slot->setVarName($varName);
        }
        
        return $slot;
    }
    
    /**
     * Get variable-to-slot mapping for expression compilation
     * @return array<string, string> Maps variable names (without $) to slot IDs
     */
    public function getVarToSlotMap(): array
    {
        return $this->varToSlotMap;
    }
    
    public function getStates(): array
    {
        return $this->states;
    }

    public function createGlobalState(string $key, mixed $initial = null): StateSlot
    {
        $normalizedKey = trim($key);
        if ($normalizedKey === '') {
            throw new \InvalidArgumentException('PalmState key cannot be empty');
        }

        return $this->createState($initial, true, $normalizedKey);
    }

    public function hasInteractiveState(): bool
    {
        return !empty($this->states);
    }

    public function isRecording(): bool
    {
        return $this->recordingAction !== null;
    }

    public function recordOperation(array $operation): void
    {
        if ($this->recordingAction === null) {
            return;
        }

        $this->currentOperations[] = $operation;
    }
    
    public function setCurrentExpression(?string $expr): void
    {
        $this->currentExpression = $expr;
    }
    
    public function getCurrentExpression(): ?string
    {
        return $this->currentExpression;
    }
    
    public function clearCurrentExpression(): void
    {
        $this->currentExpression = null;
    }

    public function registerAction(string $name, callable $callback): void
    {
        if (isset($this->actions[$name])) {
            return;
        }

        // Extract variable names from closure's static variables before rewriting
        if ($callback instanceof \Closure) {
            $reflection = new \ReflectionFunction($callback);
            $staticVars = $reflection->getStaticVariables();
            
            // Build variable-to-slot mapping from closure's static variables
            foreach ($staticVars as $varName => $value) {
                if ($value instanceof StateSlot) {
                    $slotId = $value->getSlotId();
                    $varNameClean = ltrim($varName, '$');
                    $this->varToSlotMap[$varNameClean] = $slotId;
                    // Also set var name on the slot for better expression matching
                    $value->setVarName($varName);
                }
            }
            
            $callback = ActionRewriter::rewrite($callback);
        }

        if (\is_array($callback)) {
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
            $argCount = $reflection->getNumberOfParameters();
            $args = [];
            for ($i = 0; $i < $argCount; $i++) {
                $args[] = new ActionArgument($i);
            }
            $this->recordingAction = $name;
            $this->currentOperations = [];
            
            // Suppress warnings for ActionArgument conversion during recording
            $originalErrorReporting = error_reporting(E_ALL & ~E_WARNING);
            try {
                $reflection->invokeArgs($callback[0], $args);
            } catch (\Throwable $e) {
                error_reporting($originalErrorReporting);
                throw $e;
            }
            error_reporting($originalErrorReporting);
            
            $this->actions[$name] = $this->currentOperations;
            $this->recordingAction = null;
            $this->currentOperations = [];
            return;
        }

        $reflection = new \ReflectionFunction($callback);
        $argCount = $reflection->getNumberOfParameters();
        $args = [];
        for ($i = 0; $i < $argCount; $i++) {
            $args[] = new ActionArgument($i);
        }

        $this->recordingAction = $name;
        $this->currentOperations = [];
        
        // Suppress warnings for ActionArgument conversion during recording
        // This is expected - ActionArgument objects are used to capture parameter positions
        $originalErrorReporting = error_reporting(E_ALL & ~E_WARNING);
        try {
            $reflection->invokeArgs($args);
        } catch (\Throwable $e) {
            error_reporting($originalErrorReporting);
            throw $e;
        }
        error_reporting($originalErrorReporting);
        
        $this->actions[$name] = $this->currentOperations;
        $this->recordingAction = null;
        $this->currentOperations = [];
    }

    public function registerEffect(callable $callback): void
    {
        require_once __DIR__ . '/Effect.php';
        $effectId = 'e' . count($this->effects);
        $effect = new Effect($this, $effectId, $callback);
        $this->effects[$effectId] = $effect;
    }

    /**
     * Register computed/derived state
     * Computed state automatically updates when dependencies change
     */
    public function createComputed(string $name, callable $compute, array $dependencies = []): StateSlot
    {
        $slotId = 'c' . count($this->states);
        $computedValue = $compute();
        $slot = new StateSlot($this, $slotId, $computedValue, false, null, $name);
        
        // Mark as computed
        $slot->setComputed(true);
        $slot->setComputeFunction($compute);
        $slot->setDependencies($dependencies);
        
        $this->states[$slotId] = $slot;
        
        // Track variable name
        $this->varToSlotMap[ltrim($name, '$')] = $slotId;
        $slot->setVarName($name);
        
        return $slot;
    }

    /**
     * Register lifecycle hook
     */
    public function onMount(callable $callback): void
    {
        if (!isset($this->lifecycleHooks)) {
            $this->lifecycleHooks = [];
        }
        if (!isset($this->lifecycleHooks['mount'])) {
            $this->lifecycleHooks['mount'] = [];
        }
        $this->lifecycleHooks['mount'][] = $callback;
    }

    public function onUnmount(callable $callback): void
    {
        if (!isset($this->lifecycleHooks)) {
            $this->lifecycleHooks = [];
        }
        if (!isset($this->lifecycleHooks['unmount'])) {
            $this->lifecycleHooks['unmount'] = [];
        }
        $this->lifecycleHooks['unmount'][] = $callback;
    }

    public function getLifecycleHooks(): array
    {
        return $this->lifecycleHooks ?? [];
    }

    public function finalizeHtml(string $html): string
    {
        if (!$this->hasInteractiveState()) {
            return $html;
        }

        // Add deterministic IDs to key nodes for hydration matching
        $nodeCounter = 0;
        $html = preg_replace_callback(
            '/<(\w+)([^>]*?)(?:\/?>|>.*?<\/\1>)/s',
            function ($matches) use (&$nodeCounter) {
                $tag = $matches[1];
                $attrs = $matches[2];
                $fullMatch = $matches[0];

                // Add data-psr-id if not already present and it's a key element
                if (!preg_match('/data-psr-id=/', $attrs)) {
                    if (in_array(strtolower($tag), ['button', 'input', 'select', 'textarea', 'a', 'div', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                        $nodeCounter++;
                        $idAttr = ' data-psr-id="' . $this->id . '_' . $nodeCounter . '"';
                        // Insert before closing of tag or before >
                        if (preg_match('/\/>/', $fullMatch)) {
                            $fullMatch = str_replace('/>', $idAttr . ' />', $fullMatch);
                        } else {
                            $fullMatch = str_replace('>', $idAttr . '>', $fullMatch);
                        }
                    }
                }
                return $fullMatch;
            },
            $html
        );

        // Replace onclick handlers with data attributes (support both palm and psr prefixes)
        // This converts PHP onclick="action()" to data attributes that JS can bind to
        $html = preg_replace_callback('/onclick=["\']([^"\']+)["\']/i', function ($matches) {
            $expression = trim($matches[1]);
            $action = $expression;
            $args = '';

            // Parse function call: action() or action(arg1, arg2)
            if (preg_match('/^([a-zA-Z0-9_]+)\s*\((.*)\)\s*$/', $expression, $parts)) {
                $action = $parts[1];
                $args = trim($parts[2]);
            } elseif (substr($expression, -2) === '()') {
                $action = substr($expression, 0, -2);
            }

            // Only convert if this action is registered
            if (!isset($this->actions[$action])) {
                // Keep original onclick if action not found (might be external JS)
                return $matches[0];
            }

            $safe = htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            // Use both prefixes for compatibility
            $attributes = 'data-psr-action="' . $safe . '" data-palm-action="' . $safe . '" data-psr-component="' . htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8') . '" data-palm-component="' . htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8') . '"';
            if ($args !== '') {
                // Store arguments as JSON for parsing in JS
                try {
                    // Try to parse args as JSON-like (simple values)
                    $argsArray = [];
                    if (preg_match_all('/["\']([^"\']+)["\']|(\d+\.?\d*)|(\w+)/', $args, $argMatches, PREG_SET_ORDER)) {
                        foreach ($argMatches as $argMatch) {
                            if (!empty($argMatch[1])) {
                                $argsArray[] = $argMatch[1]; // String
                            } elseif (!empty($argMatch[2])) {
                                $argsArray[] = (float)$argMatch[2]; // Number
                            } elseif (!empty($argMatch[3])) {
                                $argsArray[] = $argMatch[3]; // Identifier
                            }
                        }
                    }
                    $safeArgs = json_encode($argsArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } catch (\Throwable $e) {
                    // Fallback: just store as string
                    $safeArgs = json_encode([$args], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $attributes .= ' data-psr-args="' . htmlspecialchars($safeArgs, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-palm-args="' . htmlspecialchars($safeArgs, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
            return $attributes;
        }, $html);

        return '<div data-psr-component="' . $this->id . '" data-palm-component="' . $this->id . '">' . $html . '</div>';
    }

    public function buildPayload(): ?array
    {
        if (!$this->hasInteractiveState()) {
            return null;
        }

        $statePayload = [];
        foreach ($this->states as $slot) {
            $stateData = [
                'id' => $slot->getSlotId(),
                'value' => $slot->getValue(),
                'global' => $slot->isGlobal(),
                'key' => $slot->getGlobalKey(),
            ];
            
            // Include computed state information
            if ($slot->isComputed()) {
                $stateData['computed'] = true;
                $deps = $slot->getDependencies();
                if (!empty($deps)) {
                    // Convert dependency StateSlot objects to slot IDs
                    $depSlotIds = [];
                    foreach ($deps as $dep) {
                        if ($dep instanceof StateSlot) {
                            $depSlotIds[] = $dep->getSlotId();
                        }
                    }
                    $stateData['dependencies'] = $depSlotIds;
                    
                    // Try to extract expression from compute function
                    // For common patterns like multiplication, we can infer the expression
                    if (count($depSlotIds) === 2) {
                        // Common pattern: $total = $count * $multiplier
                        // Store as a simple multiplication expression
                        $stateData['expression'] = "state['{$depSlotIds[0]}'].get() * state['{$depSlotIds[1]}'].get()";
                    } elseif (count($depSlotIds) === 1) {
                        // Single dependency - could be various operations
                        // For now, just use the dependency value
                        $stateData['expression'] = "state['{$depSlotIds[0]}'].get()";
                    }
                }
            }
            
            $statePayload[] = $stateData;
        }

        $effectsPayload = [];
        foreach ($this->effects as $effect) {
            $effectsPayload[] = [
                'id' => $effect->getEffectId(),
                'dependencies' => $effect->getDependencies(),
            ];
        }

        return [
            'id' => $this->id,
            'states' => $statePayload,
            'actions' => $this->actions,
            'effects' => $effectsPayload,
            'lifecycleHooks' => $this->lifecycleHooks ?? [],
        ];
    }
}


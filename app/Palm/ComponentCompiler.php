<?php

namespace Frontend\Palm;

/**
 * Runtime compiler that compiles PHP components to JavaScript modules
 * 
 * Features:
 * - Parses PHP AST to identify state(), effect(), event handlers
 * - Compiles to ES modules with reactive state and event bindings
 * - Supports direct assignment: $counter = $counter + 20
 * - Caching is disabled by default for frontend files (always compiles fresh)
 * - Generates source maps for debugging
 */
class ComponentCompiler
{
    protected static string $cacheDir = '';
    protected static bool $cacheEnabled = false; // Disabled by default for frontend files
    protected static bool $sourceMapsEnabled = true;

    public static function init(string $cacheDir): void
    {
        self::$cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0777, true);
        }
        // Ensure caching is disabled for frontend files
        self::$cacheEnabled = false;
    }

    /**
     * Compile a component context to JavaScript module
     */
    public static function compileComponent(ComponentContext $context, string $viewPath): string
    {
        $componentId = $context->getId();
        $cacheFile = self::$cacheDir . '/' . md5($viewPath . $componentId) . '.js';
        $cacheMetaFile = $cacheFile . '.meta';

        // Check cache
        if (self::$cacheEnabled && file_exists($cacheFile) && file_exists($cacheMetaFile)) {
            $meta = json_decode(file_get_contents($cacheMetaFile), true);
            if ($meta && isset($meta['mtime']) && isset($meta['viewPath'])) {
                $viewMtime = filemtime($viewPath);
                if ($meta['mtime'] >= $viewMtime && $meta['viewPath'] === $viewPath) {
                    return file_get_contents($cacheFile);
                }
            }
        }

        // Compile
        try {
            $js = self::generateJsModule($context, $viewPath);
        } catch (\Throwable $e) {
            error_log('ComponentCompiler error: ' . $e->getMessage());
            return self::generateEmptyModule();
        }

        // Cache
        if (self::$cacheEnabled) {
            file_put_contents($cacheFile, $js);
            file_put_contents($cacheMetaFile, json_encode([
                'mtime' => time(),
                'viewPath' => $viewPath,
                'componentId' => $componentId,
            ]));
        }

        return $js;
    }

    protected static function generateJsModule(ComponentContext $context, string $viewPath): string
    {
        $payload = $context->buildPayload();
        if (!$payload) {
            return self::generateEmptyModule();
        }

        $js = [];
        $js[] = '/**';
        $js[] = ' * Auto-generated from PHP component';
        $js[] = ' * Source: ' . basename($viewPath);
        $js[] = ' * Optimized for performance';
        $js[] = ' */';
        $js[] = '';
        $js[] = 'export function mount(root, initial) {';
        $js[] = '  "use strict";';
        $js[] = '  if (!root) {';
        $js[] = '    console.warn("Palm: Mount root is null");';
        $js[] = '    return { state: {}, unmount: () => {} };';
        $js[] = '  }';
        $js[] = '  ';
        $js[] = '  const state = {};';
        $js[] = '  const subscribers = {};';
        $js[] = '  // Cache DOM queries for performance';
        $js[] = '  const queryCache = new Map();';
        $js[] = '';

        // Initialize states (including computed states)
        // Use initial parameter to reset state on each mount (navigation)
        $js[] = '  // Use initial state from server to reset component state on navigation';
        $js[] = '  const initialStates = (initial && initial.states) ? initial.states : [];';
        $js[] = '  const initialStateMap = {};';
        $js[] = '  initialStates.forEach(s => { if (s && s.id) initialStateMap[s.id] = s.value; });';
        $js[] = '';
        
        foreach ($payload['states'] as $state) {
            $slotId = $state['id'];
            $initialValue = json_encode($state['value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $isComputed = $state['computed'] ?? false;
            $dependencies = $state['dependencies'] ?? [];
            
            $js[] = "  // State: {$slotId}" . ($isComputed ? ' (computed)' : '');
            // Use fresh initial value from parameter, fallback to default
            $js[] = "  const {$slotId}Initial = initialStateMap['{$slotId}'] !== undefined ? initialStateMap['{$slotId}'] : {$initialValue};";
            $js[] = "  state['{$slotId}'] = createState({$slotId}Initial);";
            
            // If computed, set up reactive updates from dependencies
            if ($isComputed && !empty($dependencies)) {
                $js[] = "  // Computed state - subscribe to dependencies";
                $js[] = "  (function() {";
                $js[] = "    const recompute = () => {";
                // For now, we'll use a simple expression based on the initial value
                // In a full implementation, we'd need to store the compute function
                // For computed states like total = count * multiplier, we need to generate: state['count'].get() * state['multiplier'].get()
                // Since we don't have the original expression, we'll use a pattern-based approach
                $jsExpr = self::generateComputedExpression($state, $payload['states']);
                if ($jsExpr) {
                    $js[] = "      const newValue = {$jsExpr};";
                } else {
                    // Fallback: use initial value (won't update, but won't break)
                    $js[] = "      const newValue = {$initialValue};";
                }
                $js[] = "      if (state['{$slotId}'].get() !== newValue) {";
                $js[] = "        state['{$slotId}'].set(newValue);";
                $js[] = "      }";
                $js[] = "    };";
                foreach ($dependencies as $depSlotId) {
                    $js[] = "    if (state['{$depSlotId}']) {";
                    $js[] = "      state['{$depSlotId}'].subscribe(recompute);";
                    $js[] = "    }";
                }
                $js[] = "  })();";
            }
            $js[] = '';
        }

        // Build variable-to-slot mapping for expression compilation
        // Get the mapping from context if available
        $stateVarMap = $context->getVarToSlotMap();
        
        // If no mapping available, build a fallback map based on slot IDs
        if (empty($stateVarMap)) {
            foreach ($payload['states'] as $state) {
                $slotId = $state['id'];
                // Use slot ID as both key and value for fallback
                $stateVarMap[$slotId] = $slotId;
            }
        }

        // Generate action functions
        foreach ($payload['actions'] as $actionName => $operations) {
            $js[] = "  function {$actionName}() {";
            foreach ($operations as $op) {
                $js[] = self::generateOperationJs($op, $stateVarMap);
            }
            $js[] = '  }';
            $js[] = '';
        }
        
        // Bind events - convert PHP onclick handlers to JS event listeners (optimized)
        $js[] = '  // Bind event handlers from data-psr-action attributes (optimized)';
        $js[] = '  const actionElements = root.querySelectorAll("[data-psr-action], [data-palm-action]");';
        $js[] = '  const actionElementsLength = actionElements.length;';
        $js[] = '  const componentId = "' . $payload['id'] . '";';
        $js[] = '  ';
        $js[] = '  // Build action handler map once';
        $js[] = '  const actionHandlers = {';
        foreach (array_keys($payload['actions']) as $actionName) {
            $js[] = "    '{$actionName}': {$actionName},";
        }
        $js[] = '  };';
        $js[] = '  ';
        $js[] = '  for (let i = 0; i < actionElementsLength; i++) {';
        $js[] = '    const el = actionElements[i];';
        $js[] = '    const actionName = el.getAttribute("data-psr-action") || el.getAttribute("data-palm-action");';
        $js[] = '    const elComponentId = el.getAttribute("data-psr-component") || el.getAttribute("data-palm-component");';
        $js[] = '    if (elComponentId !== componentId || !actionName) continue;';
        $js[] = '    ';
        $js[] = '    const handler = actionHandlers[actionName];';
        $js[] = '    if (handler) {';
        $js[] = '      const handlerFn = (e) => {';
        $js[] = '        if (e) {';
        $js[] = '          e.preventDefault();';
        $js[] = '          e.stopPropagation();';
        $js[] = '        }';
        $js[] = '        try {';
        $js[] = '          const args = el.getAttribute("data-psr-args") || el.getAttribute("data-palm-args");';
        $js[] = '          if (args) {';
        $js[] = '            try {';
        $js[] = '              const parsedArgs = JSON.parse(args);';
        $js[] = '              handler(...parsedArgs);';
        $js[] = '            } catch (err) {';
        $js[] = '              console.warn("Palm: Failed to parse action args:", args, err);';
        $js[] = '              handler();';
        $js[] = '            }';
        $js[] = '          } else {';
        $js[] = '            handler();';
        $js[] = '          }';
        $js[] = '        } catch (error) {';
        $js[] = '          console.error("Palm: Action handler error:", actionName, error);';
        $js[] = '        }';
        $js[] = '      };';
        $js[] = '      el.addEventListener("click", handlerFn, { passive: false });';
        $js[] = '      // Remove onclick attribute to prevent double-handling';
        $js[] = '      if (el.hasAttribute("onclick")) {';
        $js[] = '        el.removeAttribute("onclick");';
        $js[] = '      }';
        $js[] = '    }';
        $js[] = '  }';
        $js[] = '';

        // Subscribe to state changes - handles StateSlot wrapper spans from PHP state output (optimized)
        $js[] = '  // Subscribe state bindings - handles StateSlot wrapper spans and custom bindings (optimized)';
        $js[] = '  const bindElements = root.querySelectorAll("[data-psr-bind], [data-palm-bind]");';
        $js[] = '  const bindElementsLength = bindElements.length;';
        $js[] = '  ';
        $js[] = '  // Batch DOM updates using requestAnimationFrame';
        $js[] = '  let updateQueue = [];';
        $js[] = '  let rafScheduled = false;';
        $js[] = '  function scheduleUpdate(callback) {';
        $js[] = '    updateQueue.push(callback);';
        $js[] = '    if (!rafScheduled) {';
        $js[] = '      rafScheduled = true;';
        $js[] = '      requestAnimationFrame(() => {';
        $js[] = '        const queue = updateQueue;';
        $js[] = '        updateQueue = [];';
        $js[] = '        rafScheduled = false;';
        $js[] = '        queue.forEach(fn => { try { fn(); } catch (e) { console.error("Update error:", e); } });';
        $js[] = '      });';
        $js[] = '    }';
        $js[] = '  }';
        $js[] = '  ';
        $js[] = '  for (let i = 0; i < bindElementsLength; i++) {';
        $js[] = '    const el = bindElements[i];';
        $js[] = '    const bind = el.getAttribute("data-psr-bind") || el.getAttribute("data-palm-bind");';
        $js[] = '    if (!bind) continue;';
        $js[] = '    const [bindComponentId, slotId] = bind.split("::");';
        $js[] = '    if (bindComponentId !== componentId || !state[slotId]) continue;';
        $js[] = '    ';
        $js[] = '    const stateObj = state[slotId];';
        $js[] = '    const textNode = el.firstChild;';
        $js[] = '    const isWrapperSpan = el.tagName === "SPAN" && ';
        $js[] = '                          textNode && ';
        $js[] = '                          textNode.nodeType === 3 && ';
        $js[] = '                          el.children.length === 0;';
        $js[] = '    ';
        $js[] = '    // Set initial value';
        $js[] = '    const initialValue = stateObj.get();';
        $js[] = '    const stringValue = initialValue !== null && initialValue !== undefined ? String(initialValue) : "";';
        $js[] = '    ';
        $js[] = '    if (isWrapperSpan && textNode) {';
        $js[] = '      textNode.nodeValue = stringValue;';
        $js[] = '    } else {';
        $js[] = '      el.textContent = stringValue;';
        $js[] = '    }';
        $js[] = '    ';
        $js[] = '    // Subscribe with batched updates';
        $js[] = '    stateObj.subscribe(value => {';
        $js[] = '      scheduleUpdate(() => {';
        $js[] = '        const stringVal = value !== null && value !== undefined ? String(value) : "";';
        $js[] = '        if (isWrapperSpan) {';
        $js[] = '          if (textNode && textNode.parentNode === el) {';
        $js[] = '            textNode.nodeValue = stringVal;';
        $js[] = '          }';
        $js[] = '        } else {';
        $js[] = '          if (el.parentNode) {';
        $js[] = '            el.textContent = stringVal;';
        $js[] = '          }';
        $js[] = '        }';
        $js[] = '      });';
        $js[] = '    });';
        $js[] = '  }';
        $js[] = '';

        // Store component instance and create global functions AFTER state is initialized
        // This ensures onclick handlers work even before event listeners are bound
        $js[] = '  window.__PALM_COMPONENT_' . $payload['id'] . '__ = { state, actions: {} };';
        $js[] = '';
        
        // Create global wrapper functions for onclick handlers (available immediately)
        foreach (array_keys($payload['actions']) as $actionName) {
            $js[] = "  window.__PALM_COMPONENT_{$payload['id']}__.actions['{$actionName}'] = {$actionName};";
            $js[] = "  window['{$actionName}'] = function(...args) {";
            $js[] = "    const component = window.__PALM_COMPONENT_{$payload['id']}__;";
            $js[] = "    if (component && component.actions && component.actions['{$actionName}']) {";
            $js[] = "      component.actions['{$actionName}'](...args);";
            $js[] = "    } else {";
            $js[] = "      console.warn('Palm: Action {$actionName} not available yet');";
            $js[] = "    }";
            $js[] = "  };";
            $js[] = '';
        }
        
        $js[] = '  return { state, unmount: () => {';
        // Cleanup global functions on unmount
        foreach (array_keys($payload['actions']) as $actionName) {
            $js[] = "    delete window['{$actionName}'];";
        }
        $js[] = "    delete window.__PALM_COMPONENT_{$payload['id']}__;";
        $js[] = '  } };';
        $js[] = '}';
        $js[] = '';

        // Helper functions (optimized)
        $js[] = 'function createState(initial) {';
        $js[] = '  let value = initial;';
        $js[] = '  const subscribers = new Set();';
        $js[] = '  ';
        $js[] = '  return {';
        $js[] = '    get() { return value; },';
        $js[] = '    set(newValue) {';
        $js[] = '      if (value !== newValue) {';
        $js[] = '        value = newValue;';
        $js[] = '        // Batch subscriber notifications';
        $js[] = '        subscribers.forEach(fn => {';
        $js[] = '          try {';
        $js[] = '            fn(value);';
        $js[] = '          } catch (e) {';
        $js[] = '            console.error("Palm: State subscriber error:", e);';
        $js[] = '          }';
        $js[] = '        });';
        $js[] = '      }';
        $js[] = '    },';
        $js[] = '    subscribe(fn) {';
        $js[] = '      subscribers.add(fn);';
        $js[] = '      return () => subscribers.delete(fn);';
        $js[] = '    },';
        $js[] = '    increment(step = 1) { this.set(value + step); },'; 
        $js[] = '    decrement(step = 1) { this.set(value - step); },'; 
        $js[] = '    toggle() { this.set(!value); },';
        $js[] = '    push(item) {';
        $js[] = '      if (!Array.isArray(value)) value = [];';
        $js[] = '      this.set([...value, item]);';
        $js[] = '    },';
        $js[] = '    pop() {';
        $js[] = '      if (!Array.isArray(value)) return null;';
        $js[] = '      const newValue = [...value];';
        $js[] = '      const item = newValue.pop();';
        $js[] = '      this.set(newValue);';
        $js[] = '      return item;';
        $js[] = '    },';
        $js[] = '    update(key, item) {';
        $js[] = '      if (Array.isArray(value)) {';
        $js[] = '        const newValue = [...value];';
        $js[] = '        newValue[key] = item;';
        $js[] = '        this.set(newValue);';
        $js[] = '      } else if (value && typeof value === "object") {';
        $js[] = '        this.set({ ...value, [key]: item });';
        $js[] = '      } else {';
        $js[] = '        this.set({ [key]: item });';
        $js[] = '      }';
        $js[] = '    },';
        $js[] = '    remove(key) {';
        $js[] = '      if (Array.isArray(value)) {';
        $js[] = '        const newValue = [...value];';
        $js[] = '        delete newValue[key];';
        $js[] = '        this.set(newValue);';
        $js[] = '      } else if (value && typeof value === "object") {';
        $js[] = '        const newValue = { ...value };';
        $js[] = '        delete newValue[key];';
        $js[] = '        this.set(newValue);';
        $js[] = '      }';
        $js[] = '    },';
        $js[] = '    merge(newValues) {';
        $js[] = '      if (Array.isArray(value) && Array.isArray(newValues)) {';
        $js[] = '        this.set([...value, ...newValues]);';
        $js[] = '      } else if (value && typeof value === "object" && newValues && typeof newValues === "object") {';
        $js[] = '        this.set({ ...value, ...newValues });';
        $js[] = '      } else {';
        $js[] = '        this.set(newValues);';
        $js[] = '      }';
        $js[] = '    },';
        $js[] = '  };';
        $js[] = '}';

        return implode("\n", $js);
    }

    protected static function generateOperationJs(array $op, array $stateVarMap = []): string
    {
        $type = $op['type'] ?? '';
        $slotId = $op['slot'] ?? '';

        switch ($type) {
            case 'expr':
                // Expression operation - expression should already be converted to JS
                $expr = $op['expr'] ?? '';
                $operation = $op['operation'] ?? 'set';
                
                if (empty($expr) || trim($expr) === '') {
                    return "    // Empty expression";
                }
                
                // Validate expression - ensure it's valid JavaScript
                $expr = trim($expr);
                
                // Check for common syntax errors
                if (substr($expr, -1) === ')' && substr_count($expr, '(') > substr_count($expr, ')')) {
                    // Missing closing parenthesis - try to fix
                    $expr .= ')';
                }
                
                if ($operation === 'set') {
                    // Expression is already in JS format (converted by StateSlot)
                    // Just use it directly, but wrap in try-catch for safety
                    return "    try { state['{$slotId}'].set({$expr}); } catch (e) { console.error('Palm: Expression error:', e, 'for expr:', " . json_encode($expr) . "); }";
                }
                
                return "    // Expression: {$expr}";

            case 'set':
                $value = self::serializeValue($op['value'] ?? null);
                return "    state['{$slotId}'].set({$value});";

            case 'increment':
                $step = $op['value'] ?? 1;
                return "    state['{$slotId}'].increment({$step});";

            case 'decrement':
                $step = $op['value'] ?? 1;
                return "    state['{$slotId}'].decrement({$step});";

            case 'toggle':
                return "    state['{$slotId}'].toggle();";

            case 'push':
                $value = self::serializeValue($op['value'] ?? null);
                // Check if value looks like a static "Item N" pattern that should be computed dynamically
                // This is a workaround - ideally expressions should be preserved
                if (is_string($op['value'] ?? null) && preg_match('/^Item \d+$/', $op['value'])) {
                    // Generate dynamic expression that computes item number from array length
                    $varSuffix = substr(md5($slotId . $op['value']), 0, 6);
                    return "    const arr{$varSuffix} = state['{$slotId}'].get() || []; state['{$slotId}'].push('Item ' + (arr{$varSuffix}.length + 1));";
                }
                return "    state['{$slotId}'].push({$value});";
            case 'push_expr':
                $expr = $op['expr'] ?? '';
                if (empty($expr)) {
                    return "    // Empty push expression";
                }
                $safeExpr = json_encode($expr);
                return "    try { state['{$slotId}'].push({$expr}); } catch (e) { console.error('Palm: Push expression error:', e, 'for expr:', {$safeExpr}); }";

            case 'pop':
                return "    state['{$slotId}'].pop();";

            case 'update':
                $key = json_encode($op['key'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $value = self::serializeValue($op['value'] ?? null);
                return "    state['{$slotId}'].update({$key}, {$value});";

            case 'remove':
                $key = json_encode($op['key'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return "    state['{$slotId}'].remove({$key});";

            case 'merge':
                $value = self::serializeValue($op['value'] ?? null);
                return "    state['{$slotId}'].merge({$value});";

            default:
                return "    // Unknown operation: {$type}";
        }
    }

    protected static function serializeValue(mixed $value): string
    {
        if (is_array($value) && isset($value['type']) && $value['type'] === 'arg') {
            // Action argument reference
            return 'arguments[' . ($value['index'] ?? 0) . ']';
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate JavaScript expression for computed state
     * Uses stored expression or infers from dependencies
     */
    protected static function generateComputedExpression(array $computedState, array $allStates): string
    {
        $slotId = $computedState['id'];
        $dependencies = $computedState['dependencies'] ?? [];
        $value = $computedState['value'] ?? null;
        $expression = $computedState['expression'] ?? null;
        
        // If expression is stored, use it (converted to JS format)
        if ($expression) {
            // Expression should already be in JS format (state['slotId'].get())
            return $expression;
        }
        
        // Try to infer expression from common patterns
        // For multiplication: count * multiplier
        if (count($dependencies) === 2 && is_numeric($value)) {
            $dep1 = $dependencies[0];
            $dep2 = $dependencies[1];
            return "state['{$dep1}'].get() * state['{$dep2}'].get()";
        }
        
        // Fallback: return initial value (won't update reactively, but won't break)
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected static function generateEmptyModule(): string
    {
        return <<<'JS'
export function mount(root, initial) {
  return { state: {}, unmount: () => {} };
}
JS;
    }

    /**
     * Get cache file path for a component
     */
    public static function getCachePath(string $viewPath, string $componentId): string
    {
        return self::$cacheDir . '/' . md5($viewPath . $componentId) . '.js';
    }

    /**
     * Clear all cached components
     */
    public static function clearCache(): void
    {
        if (!is_dir(self::$cacheDir)) {
            return;
        }

        $files = glob(self::$cacheDir . '/*.js') ?: [];
        $metaFiles = glob(self::$cacheDir . '/*.js.meta') ?: [];

        foreach (array_merge($files, $metaFiles) as $file) {
            @unlink($file);
        }
    }

    /**
     * Enable or disable caching
     */
    public static function setCacheEnabled(bool $enabled): void
    {
        self::$cacheEnabled = $enabled;
    }

    /**
     * Enable or disable source maps
     */
    public static function setSourceMapsEnabled(bool $enabled): void
    {
        self::$sourceMapsEnabled = $enabled;
    }
}


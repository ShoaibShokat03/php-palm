<?php

namespace App\Core\Traits;

/**
 * Has Attributes Trait for Models
 * 
 * Provides attribute casting and accessor/mutator support
 * 
 * Features:
 * - Attribute type casting (json, boolean, integer, float, datetime)
 * - Custom accessors: getFooAttribute() 
 * - Custom mutators: setFooAttribute($value)
 * - Hidden attributes for toArray/toJson
 * - Mass assignment protection with fillable/guarded
 * 
 * Usage:
 *   class User extends Model {
 *       use HasAttributes;
 *       
 *       protected array $casts = [
 *           'is_admin' => 'boolean',
 *           'settings' => 'json',
 *           'birthday' => 'date',
 *       ];
 *       
 *       protected array $hidden = ['password', 'remember_token'];
 *       protected array $fillable = ['name', 'email'];
 *   }
 * 
 * @package PhpPalm\ORM
 */
trait HasAttributes
{
    /**
     * Attribute casting configuration
     * Override in model: protected array $casts = ['is_active' => 'boolean'];
     */
    protected array $casts = [];

    /**
     * Attributes hidden from array/JSON output
     */
    protected array $hidden = [];

    /**
     * Attributes visible in array/JSON output (whitelist mode)
     */
    protected array $visible = [];

    /**
     * Attributes that are mass assignable
     */
    protected array $fillable = [];

    /**
     * Attributes that are NOT mass assignable
     */
    protected array $guarded = ['id'];

    /**
     * Cache for accessor method names
     */
    protected static array $accessorCache = [];

    /**
     * Cache for mutator method names
     */
    protected static array $mutatorCache = [];

    /**
     * Get a casted attribute value
     */
    public function getCastAttribute(string $key, $value)
    {
        if (!isset($this->casts[$key]) || $value === null) {
            return $value;
        }

        return $this->castAttribute($key, $value);
    }

    /**
     * Set a casted attribute value
     */
    public function setCastAttribute(string $key, $value)
    {
        if (!isset($this->casts[$key]) || $value === null) {
            return $value;
        }

        return $this->castAttributeForStorage($key, $value);
    }

    /**
     * Cast attribute to its PHP type
     */
    protected function castAttribute(string $key, $value)
    {
        $castType = $this->casts[$key];

        return match ($castType) {
            'int', 'integer' => (int) $value,
            'real', 'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
            'object' => is_string($value) ? json_decode($value) : (object) $value,
            'date' => $value ? date('Y-m-d', strtotime($value)) : null,
            'datetime' => $value ? date('Y-m-d H:i:s', strtotime($value)) : null,
            'timestamp' => $value ? strtotime($value) : null,
            default => $value,
        };
    }

    /**
     * Cast attribute for database storage
     */
    protected function castAttributeForStorage(string $key, $value)
    {
        $castType = $this->casts[$key];

        return match ($castType) {
            'array', 'json', 'object' => is_string($value) ? $value : json_encode($value),
            'bool', 'boolean' => $value ? 1 : 0,
            'date' => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : (is_numeric($value) ? date('Y-m-d', $value) : $value),
            'datetime' => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : (is_numeric($value) ? date('Y-m-d H:i:s', $value) : $value),
            default => $value,
        };
    }

    /**
     * Check if attribute has a custom accessor (getFooAttribute)
     */
    protected function hasAccessor(string $key): bool
    {
        $class = static::class;

        if (!isset(self::$accessorCache[$class])) {
            self::$accessorCache[$class] = [];
        }

        if (!isset(self::$accessorCache[$class][$key])) {
            $method = 'get' . $this->studly($key) . 'Attribute';
            self::$accessorCache[$class][$key] = method_exists($this, $method) ? $method : false;
        }

        return self::$accessorCache[$class][$key] !== false;
    }

    /**
     * Get accessor method name
     */
    protected function getAccessorMethod(string $key): ?string
    {
        if ($this->hasAccessor($key)) {
            return self::$accessorCache[static::class][$key];
        }
        return null;
    }

    /**
     * Check if attribute has a custom mutator (setFooAttribute)
     */
    protected function hasMutator(string $key): bool
    {
        $class = static::class;

        if (!isset(self::$mutatorCache[$class])) {
            self::$mutatorCache[$class] = [];
        }

        if (!isset(self::$mutatorCache[$class][$key])) {
            $method = 'set' . $this->studly($key) . 'Attribute';
            self::$mutatorCache[$class][$key] = method_exists($this, $method) ? $method : false;
        }

        return self::$mutatorCache[$class][$key] !== false;
    }

    /**
     * Get mutator method name
     */
    protected function getMutatorMethod(string $key): ?string
    {
        if ($this->hasMutator($key)) {
            return self::$mutatorCache[static::class][$key];
        }
        return null;
    }

    /**
     * Convert snake_case to StudlyCase
     */
    protected function studly(string $value): string
    {
        $value = ucwords(str_replace(['_', '-'], ' ', $value));
        return str_replace(' ', '', $value);
    }

    /**
     * Get attributes for array/JSON representation (respecting hidden/visible)
     */
    public function attributesToArray(): array
    {
        $attributes = $this->attributes;

        // Apply casting
        foreach ($this->casts as $key => $type) {
            if (isset($attributes[$key])) {
                $attributes[$key] = $this->castAttribute($key, $attributes[$key]);
            }
        }

        // Apply accessors
        foreach (array_keys($attributes) as $key) {
            $accessorMethod = $this->getAccessorMethod($key);
            if ($accessorMethod) {
                $attributes[$key] = $this->$accessorMethod($attributes[$key]);
            }
        }

        // Remove hidden attributes
        if (!empty($this->hidden)) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }

        // Keep only visible attributes (if set)
        if (!empty($this->visible)) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        }

        return $attributes;
    }

    /**
     * Get hidden attributes
     */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    /**
     * Set hidden attributes
     */
    public function setHidden(array $hidden): static
    {
        $this->hidden = $hidden;
        return $this;
    }

    /**
     * Make attributes visible (remove from hidden)
     */
    public function makeVisible(array|string $attributes): static
    {
        $attributes = is_array($attributes) ? $attributes : [$attributes];
        $this->hidden = array_diff($this->hidden, $attributes);
        return $this;
    }

    /**
     * Make attributes hidden
     */
    public function makeHidden(array|string $attributes): static
    {
        $attributes = is_array($attributes) ? $attributes : [$attributes];
        $this->hidden = array_unique(array_merge($this->hidden, $attributes));
        return $this;
    }

    /**
     * Check if an attribute is fillable
     */
    public function isFillable(string $key): bool
    {
        // If fillable array is defined and not empty, key must be in it
        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable);
        }

        // If guarded array contains *, nothing is fillable
        if (in_array('*', $this->guarded)) {
            return false;
        }

        // Otherwise, key must not be in guarded
        return !in_array($key, $this->guarded);
    }

    /**
     * Fill the model with an array of attributes (respecting fillable/guarded)
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    /**
     * Force fill the model (ignores fillable/guarded)
     */
    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    /**
     * Get fillable attributes
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Get guarded attributes
     */
    public function getGuarded(): array
    {
        return $this->guarded;
    }
}

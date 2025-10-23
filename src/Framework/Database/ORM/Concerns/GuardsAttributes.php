<?php

declare(strict_types=1);

namespace Framework\Database\ORM\Concerns;

trait GuardsAttributes
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>
     */
    protected array $guarded = ['*'];

    /**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
    protected static bool $unguarded = false;

    /**
     * Get the fillable attributes for the model.
     *
     * @return array<string>
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Get the guarded attributes for the model.
     *
     * @return array<string>
     */
    public function getGuarded(): array
    {
        return self::$unguarded === true
            ? []
            : $this->guarded;
    }

    /**
     * Determine if the given attribute may be mass assigned.
     *
     * @param  string  $key
     * @return bool
     */
    public function isFillable($key): bool
    {
        if (static::$unguarded) {
            return true;
        }

        // If the key is in the "fillable" array, we can of course assume that it's
        // a fillable attribute. Otherwise, we will check the guarded array when
        // we need to determine if the attribute is black-listed on the model.
        if (in_array($key, $this->getFillable())) {
            return true;
        }

        // If the attribute is explicitly listed in the "guarded" array then we can
        // return false immediately. This means this attribute is definitely not
        // fillable and there is no point in going any further in this method.
        if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->getFillable()) &&
            ! str_contains($key, '.') &&
            ! str_starts_with($key, '_');
    }

    /**
     * Determine if the given key is guarded.
     *
     * @param  string  $key
     * @return bool
     */
    public function isGuarded($key): bool
    {
        if (empty($this->getGuarded())) {
            return false;
        }

        return $this->getGuarded() == ['*'] ||
            ! empty(preg_grep(
                '/^' . preg_quote($key, '/') . '$/i',
                $this->getGuarded()
            ));
    }

    /**
     * Determine if the model is totally guarded.
     *
     * @return bool
     */
    public function totallyGuarded()
    {
        return count($this->getFillable()) === 0 && $this->getGuarded() == ['*'];
    }

    /**
     * Get the fillable attributes of a given array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function fillableFromArray(array $attributes)
    {
        if (count($this->getFillable()) > 0 && ! static::$unguarded) {
            return array_intersect_key($attributes, array_flip($this->getFillable()));
        }

        return $attributes;
    }
}

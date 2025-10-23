<?php

declare(strict_types=1);

namespace Framework\Database\ORM\Concerns;

trait HasAttributes
{
    /**
     * The model's attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [];

    /**
     * The model attribute's original state.
     *
     * @var array<string, mixed>
     */
    protected $original = [];

    /**
     * Convert the model's attributes to an array.
     *
     * @return array<string, mixed>
     */
    public function attributesToArray()
    {
        return $this->getArrayableItems($this->getAttributes());
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute(string $key)
    {
        if (! $key) {
            return;
        }

        return $this->attributes[$key] ?? null;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return array<string, mixed>
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Set the array of model attributes. No checking is done.
     *
     * @param  array  $attributes
     * @param  bool  $sync
     * @return $this
     */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Sync the original attributes with the current.
     *
     * @return $this
     */
    public function syncOriginal()
    {
        $this->original = $this->getAttributes();

        return $this;
    }

    /**
     * Get an attribute array of all arrayable values.
     *
     * @param  array  $values
     * @return array
     */
    protected function getArrayableItems(array $values)
    {
        if (count($this->getVisible()) > 0) {
            $values = array_intersect_key($values, array_flip($this->getVisible()));
        }

        if (count($this->getHidden()) > 0) {
            $values = array_diff_key($values, array_flip($this->getHidden()));
        }

        return $values;
    }
}

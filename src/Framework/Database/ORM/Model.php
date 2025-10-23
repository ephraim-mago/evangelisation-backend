<?php

declare(strict_types=1);

namespace Framework\Database\ORM;

use JsonException;
use ArrayAccess;
use JsonSerializable;
use Framework\Contracts\Support\Jsonable;
use Framework\Contracts\Support\Arrayable;

abstract class Model implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    use Concerns\HasAttributes,
        Concerns\HidesAttributes,
        Concerns\GuardsAttributes;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * Create a new Entity model instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array<string, mixed>  $attributes
     * @return $this
     */
    public function fill(array $attributes): static
    {
        $totallyGuarded = $this->totallyGuarded();

        $fillable = $this->fillableFromArray($attributes);

        foreach ($fillable as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException(sprintf(
                    'Add [%s] to fillable property to allow mass assignment on [%s].',
                    $key,
                    get_class($this)
                ));
            }
        }

        if (
            count($attributes) !== count($fillable)
        ) {
            $keys = array_diff(array_keys($attributes), array_keys($fillable));

            throw new MassAssignmentException(sprintf(
                'Add fillable property [%s] to allow mass assignment on [%s].',
                implode(', ', $keys),
                get_class($this)
            ));
        }

        return $this;
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array<string, mixed>  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance(array $attributes = [], bool $exists = false)
    {
        // This method just provides a convenient way for us to generate fresh model
        // instances of this current model. It is particularly useful during the
        // hydration of new objects via the Eloquent query builder instances.
        $model = new static;

        $model->exists = $exists;

        $model->fill((array) $attributes);

        return $model;
    }

    /**
     * Create a new model instance that is existing.
     *
     * @param  array<string, mixed>  $attributes
     * @return static
     */
    public function newFromBuilder(array $attributes = [])
    {
        $model = $this->newInstance([], true);

        $model->setRawAttributes((array) $attributes, true);

        return $model;
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Set the primary key for the model.
     *
     * @param  string  $key
     * @return $this
     */
    public function setKeyName(string $key): static
    {
        $this->primaryKey = $key;

        return $this;
    }

    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType(): string
    {
        return $this->keyType;
    }

    /**
     * Set the data type for the primary key.
     *
     * @param  string  $type
     * @return $this
     */
    public function setKeyType(string $type): static
    {
        $this->keyType = $type;

        return $this;
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributesToArray();
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     *
     * @throws \Framework\Database\ORM\JsonEncodingException
     */
    public function toJson(int $options = 0): string
    {
        try {
            $json = json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw JsonEncodingException::forModel($this, $e->getMessage());
        }

        return $json;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return ! is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }
}

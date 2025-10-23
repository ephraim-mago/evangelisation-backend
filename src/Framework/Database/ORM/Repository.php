<?php

declare(strict_types=1);

namespace Framework\Database\ORM;

use Framework\Database\Connection;
use Framework\Database\Query\Builder as QueryBuilder;

/**
 * @template TModel of \Framework\Database\ORM\Model
 *
 * @mixin \Framework\Database\Query\Builder
 */
abstract class Repository
{
    /**
     * The connection for the repository.
     *
     * @var \Framework\Database\Connection
     */
    protected Connection $connection;

    /**
     * The table associated with the repository.
     *
     * @var string|null
     */
    protected string|null $table;

    /**
     * The model associated with the repository table.
     *
     * @var string|null
     */
    protected string|null $model;

    /**
     * Create a Repository instance.
     *
     * @param \Framework\Database\Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get a new query builder for the repository's table.
     *
     * @return \Framework\Database\Query\Builder
     */
    public function table(): QueryBuilder
    {
        return $this->connection->table($this->table);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return \Framework\Database\Query\Builder
     */
    public function query(): QueryBuilder
    {
        return $this->connection->query();
    }

    /**
     * Create a collection of models from plain arrays.
     *
     * @param  array  $items
     * @return array<int, TModel>
     */
    public function hydrate(array $items): array
    {
        $instance = $this->newModelInstance();

        return array_map(function ($item) use ($instance) {
            $model = $instance->newFromBuilder((array) $item);

            return $model;
        }, $items);
    }

    /**
     * Create a new instance of the model being queried.
     *
     * @param  array  $attributes
     * @return TModel
     */
    public function newModelInstance(array $attributes = [])
    {
        return (new $this->model)->newInstance($attributes);
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @return TModel
     */
    protected function newFromBuilder(array $attributes = [])
    {
        return $this->newModelInstance()->newFromBuilder($attributes);
    }
}

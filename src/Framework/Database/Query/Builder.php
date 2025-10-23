<?php

namespace Framework\Database\Query;

use DateTimeImmutable;
use Framework\Database\Connection;

class Builder
{
    /**
     * The database connection instance.
     *
     * @var \Framework\Database\Connection
     */
    protected Connection $connection;

    /**
     * The columns that should be returned.
     *
     * @var array<string>|null
     */
    protected array|null $columns;

    /**
     * The table which the query is targeting.
     *
     * @var string
     */
    protected string $from;

    /**
     * Create a new query builder instance.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Set the columns to be selected.
     *
     * @param  mixed  $columns
     * @return $this
     */
    public function select($columns = ['*']): static
    {
        $this->columns = [];
        $columns = is_array($columns) ? $columns : func_get_args();

        foreach ($columns as $column) {
            $this->columns[] = $column;
        }

        return $this;
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param  string  $table
     * @param  string|null  $as
     * @return $this
     */
    public function from($table, $as = null): static
    {
        $this->from = $as ? "{$table} as {$as}" : $table;

        return $this;
    }

    public function insert(array $values)
    {
        $values = array_merge($values, [
            'created_at' => $date = new DateTimeImmutable(),
            'updated_at' => $date
        ]);

        $columns = join(
            ', ',
            $keys = array_keys($values)
        );
        $parameters = join(
            ', ',
            array_map(fn($value) => ":$value", $keys)
        );

        return $this->connection->insert(
            "INSERT INTO {$this->from} ($columns) VALUES ($parameters)",
            $values
        );
    }


    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  string|null  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $this->insert($values);

        $id = $this->connection->getPdo()->lastInsertId($sequence);

        return is_numeric($id) ? (int) $id : $id;
    }

    public function update(array $criteria, array $values)
    {
        $values = array_merge($values, [
            'updated_at' => new DateTimeImmutable(),
        ]);

        $parameters = join(
            ', ',
            array_map(fn($value) => "$value = :$value", array_keys($values))
        );

        return $this->connection->insert(
            "UPDATE {$this->from} SET $parameters WHERE " . join(" AND ", $criteria),
            $values
        );
    }

    public function delete(array $criteria, array $values)
    {
        return $this->connection->insert(
            "DELETE FROM {$this->from} WHERE " . join(" AND ", $criteria),
            $values
        );
    }
}

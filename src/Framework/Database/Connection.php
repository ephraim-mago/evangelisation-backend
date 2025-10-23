<?php

declare(strict_types=1);

namespace Framework\Database;

use Closure;
use DateTimeInterface;
use PDO;
use PDOStatement;
use Exception;
use RuntimeException;
use Framework\Collection\Arr;
use Framework\Database\Query\Builder as QueryBuilder;

class Connection
{
    use DetectsLostConnections;

    /**
     * The active PDO connection.
     *
     * @var \PDO|\Closure
     */
    protected PDO|Closure $pdo;

    /**
     * The name of the connected database.
     *
     * @var string
     */
    protected string $database;

    /**
     * The table prefix for the connection.
     *
     * @var string
     */
    protected string $tablePrefix = '';

    /**
     * The database connection configuration options.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * The default fetch mode of the connection.
     *
     * @var int
     */
    protected int $fetchMode = PDO::FETCH_OBJ;

    /**
     * Indicates if changes have been made to the database.
     *
     * @var bool
     */
    protected bool $recordsModified = false;

    /**
     * All of the queries run against the connection.
     *
     * @var array
     */
    protected array $queryLog = [];

    /**
     * Indicates whether queries are being logged.
     *
     * @var bool
     */
    protected bool $loggingQueries = false;

    /**
     * The duration of all executed queries in milliseconds.
     *
     * @var float
     */
    protected float $totalQueryDuration = 0.0;

    /**
     * Indicates if the connection is in a "dry run".
     *
     * @var bool
     */
    protected $pretending = false;

    /**
     * Create a new database connection instance.
     *
     * @param  \PDO|\Closure  $pdo
     * @param  string  $database
     * @param  string  $tablePrefix
     * @param  array  $config
     * @return void
     */
    public function __construct(
        PDO|Closure $pdo,
        string $database = '',
        string $tablePrefix = '',
        array $config = []
    ) {
        $this->pdo = $pdo;

        // First we will setup the default properties. We keep track of the DB
        // name we are connected to since it is needed when some reflective
        // type commands are run such as checking whether a table exists.
        $this->database = $database;

        $this->tablePrefix = $tablePrefix;

        $this->config = $config;
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param  string  $table
     * @param  string|null  $as
     * @return \Framework\Database\Query\Builder
     */
    public function table($table, $as = null)
    {
        return $this->query()->from($table, $as);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Framework\Database\Query\Builder
     */
    public function query()
    {
        return new QueryBuilder($this);
    }

    /**
     * Run a select statement and return a single result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return mixed
     */
    public function selectOne(string $query, array $bindings = [])
    {
        $records = $this->select($query, $bindings);

        return array_shift($records);
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return array
     */
    public function select(string $query, array $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            // For select statements, we'll simply execute the query and return an array
            // of the database result set. Each element in the array will be a single
            // row from the database table, and will either be an array or objects.
            $statement = $this->prepared(
                $this->getPdo()->prepare($query)
            );

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            return $statement->fetchAll();
        });
    }

    /**
     * Configure the PDO prepared statement.
     *
     * @param  \PDOStatement  $statement
     * @return \PDOStatement
     */
    protected function prepared(PDOStatement $statement): PDOStatement
    {
        $statement->setFetchMode($this->fetchMode);

        return $statement;
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function insert($query, $bindings = [])
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function update($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function delete($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $this->recordsHaveBeenModified();

            return $statement->execute();
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            // For update or delete statements, we want to get the number of rows affected
            // by the statement and return that back to the developer. We'll first need
            // to execute the statement and then we'll use PDO to fetch the affected.
            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            $this->recordsHaveBeenModified(
                ($count = $statement->rowCount()) > 0
            );

            return $count;
        });
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query)
    {
        return $this->run($query, [], function ($query) {
            if ($this->pretending()) {
                return true;
            }

            $this->recordsHaveBeenModified(
                $change = $this->getPdo()->exec($query) !== false
            );

            return $change;
        });
    }

    /**
     * Bind values to their parameters in the given statement.
     *
     * @param  \PDOStatement  $statement
     * @param  array  $bindings
     * @return void
     */
    public function bindValues($statement, $bindings)
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                match (true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_resource($value) => PDO::PARAM_LOB,
                    default => PDO::PARAM_STR
                },
            );
        }
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param  array  $bindings
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            // We need to transform all instances of DateTimeInterface into the actual
            // date string. Each query grammar maintains its own date string format
            // so we'll just ask the grammar for the format to get from the date.
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($this->getDateFormat());
            } elseif (is_bool($value)) {
                $bindings[$key] = (int) $value;
            }
        }

        return $bindings;
    }

    /**
     * Run a SQL statement and log its execution context.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Framework\Database\QueryException
     */
    protected function run($query, $bindings, Closure $callback)
    {
        $start = microtime(true);

        // Here we will run this query. If an exception occurs we'll determine if it was
        // caused by a connection that has been lost. If that is the cause, we'll try
        // to re-establish connection and re-run the query with a fresh connection.
        try {
            $result = $this->runQueryCallback($query, $bindings, $callback);
        } catch (QueryException $e) {
            $result = $this->handleQueryException(
                $e,
                $query,
                $bindings,
                $callback
            );
        }

        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $this->logQuery(
            $query,
            $bindings,
            $this->getElapsedTime($start)
        );

        return $result;
    }

    /**
     * Run a SQL statement.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Framework\Database\QueryException
     */
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        // To execute the statement, we'll simply call the callback, which will actually
        // run the SQL against the PDO connection. Then we can calculate the time it
        // took to execute and log the query SQL, bindings and time in our memory.
        try {
            return $callback($query, $bindings);
        }

        // If an exception occurs when attempting to run a query, we'll format the error
        // message to include the bindings with SQL, which will make this exception a
        // lot more helpful to the developer instead of just the database's errors.
        catch (Exception $e) {
            if ($this->isUniqueConstraintError($e)) {
                throw new UniqueConstraintViolationException(
                    $this->getName(),
                    $query,
                    $this->prepareBindings($bindings),
                    $e
                );
            }

            throw new QueryException(
                $this->getName(),
                $query,
                $this->prepareBindings($bindings),
                $e
            );
        }
    }

    /**
     * Determine if the given database exception was caused by a unique constraint violation.
     *
     * @param  \Exception  $exception
     * @return bool
     */
    protected function isUniqueConstraintError(Exception $exception)
    {
        return false;
    }

    /**
     * Log a query in the connection's query log.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  float|null  $time
     * @return void
     */
    public function logQuery($query, $bindings, $time = null)
    {
        $this->totalQueryDuration += $time ?? 0.0;

        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings', 'time');
        }
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param  int|float  $start
     * @return float
     */
    protected function getElapsedTime(int|float $start)
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Get the duration of all run queries in milliseconds.
     *
     * @return float
     */
    public function totalQueryDuration()
    {
        return $this->totalQueryDuration;
    }

    /**
     * Reset the duration of all run queries.
     *
     * @return void
     */
    public function resetTotalQueryDuration()
    {
        $this->totalQueryDuration = 0.0;
    }

    /**
     * Handle a query exception.
     *
     * @param  \Framework\Database\QueryException  $e
     * @param  string  $query
     * @param  array  $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Framework\Database\QueryException
     */
    protected function handleQueryException(QueryException $e, $query, $bindings, Closure $callback)
    {
        return $this->tryAgainIfCausedByLostConnection(
            $e,
            $query,
            $bindings,
            $callback
        );
    }

    /**
     * Handle a query exception that occurred during query execution.
     *
     * @param  \Framework\Database\QueryException  $e
     * @param  string  $query
     * @param  array  $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Framework\Database\QueryException
     */
    protected function tryAgainIfCausedByLostConnection(QueryException $e, $query, $bindings, Closure $callback)
    {
        if ($this->causedByLostConnection($e->getPrevious())) {
            $this->reconnect();

            return $this->runQueryCallback($query, $bindings, $callback);
        }

        throw $e;
    }

    /**
     * Reconnect to the database.
     *
     * @return mixed|false
     *
     * @throws \Framework\Database\LostConnectionException
     */
    public function reconnect()
    {
        throw new LostConnectionException('Lost connection and no reconnector available.');
    }

    /**
     * Escape a value for safe SQL embedding.
     *
     * @param  string|float|int|bool|null  $value
     * @param  bool  $binary
     * @return string
     */
    public function escape($value, $binary = false)
    {
        if ($value === null) {
            return 'null';
        } elseif ($binary) {
            return $this->escapeBinary($value);
        } elseif (is_int($value) || is_float($value)) {
            return (string) $value;
        } elseif (is_bool($value)) {
            return $this->escapeBool($value);
        } elseif (is_array($value)) {
            throw new RuntimeException('The database connection does not support escaping arrays.');
        } else {
            if (str_contains($value, "\00")) {
                throw new RuntimeException('Strings with null bytes cannot be escaped. Use the binary escape option.');
            }

            if (preg_match('//u', $value) === false) {
                throw new RuntimeException('Strings with invalid UTF-8 byte sequences cannot be escaped.');
            }

            return $this->escapeString($value);
        }
    }

    /**
     * Escape a string value for safe SQL embedding.
     *
     * @param  string  $value
     * @return string
     */
    protected function escapeString($value)
    {
        return $this->getPdo()->quote($value);
    }

    /**
     * Escape a boolean value for safe SQL embedding.
     *
     * @param  bool  $value
     * @return string
     */
    protected function escapeBool($value)
    {
        return $value ? '1' : '0';
    }

    /**
     * Escape a binary value for safe SQL embedding.
     *
     * @param  string  $value
     * @return string
     */
    protected function escapeBinary($value)
    {
        throw new RuntimeException('The database connection does not support escaping binary values.');
    }

    /**
     * Determine if the database connection has modified any database records.
     *
     * @return bool
     */
    public function hasModifiedRecords()
    {
        return $this->recordsModified;
    }

    /**
     * Indicate if any records have been modified.
     *
     * @param  bool  $value
     * @return void
     */
    public function recordsHaveBeenModified($value = true)
    {
        if (! $this->recordsModified) {
            $this->recordsModified = $value;
        }
    }

    /**
     * Get the current PDO connection.
     *
     * @return \PDO
     */
    public function getPdo()
    {
        if ($this->pdo instanceof Closure) {
            return $this->pdo = call_user_func($this->pdo);
        }

        return $this->pdo;
    }

    /**
     * Get the current PDO connection parameter without executing any reconnect logic.
     *
     * @return \PDO|\Closure|null
     */
    public function getRawPdo()
    {
        return $this->pdo;
    }

    /**
     * Set the PDO connection.
     *
     * @param  \PDO|\Closure|null  $pdo
     * @return $this
     */
    public function setPdo($pdo)
    {
        $this->pdo = $pdo;

        return $this;
    }

    /**
     * Get the database connection name.
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->getConfig('name');
    }

    /**
     * Get an option from the configuration options.
     *
     * @param  string|null  $option
     * @return mixed
     */
    public function getConfig($option = null)
    {
        return Arr::get($this->config, $option);
    }

    /**
     * Get the PDO driver name.
     *
     * @return string
     */
    public function getDriverName()
    {
        return $this->getConfig('driver');
    }

    /**
     * Determine if the connection is in a "dry run".
     *
     * @return bool
     */
    public function pretending()
    {
        return $this->pretending === true;
    }

    /**
     * Get the connection query log.
     *
     * @return array
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    public function flushQueryLog()
    {
        $this->queryLog = [];
    }

    /**
     * Enable the query log on the connection.
     *
     * @return void
     */
    public function enableQueryLog()
    {
        $this->loggingQueries = true;
    }

    /**
     * Disable the query log on the connection.
     *
     * @return void
     */
    public function disableQueryLog()
    {
        $this->loggingQueries = false;
    }

    /**
     * Determine whether we're logging queries.
     *
     * @return bool
     */
    public function logging()
    {
        return $this->loggingQueries;
    }

    /**
     * Get the name of the connected database.
     *
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->database;
    }

    /**
     * Set the name of the connected database.
     *
     * @param  string  $database
     * @return $this
     */
    public function setDatabaseName($database)
    {
        $this->database = $database;

        return $this;
    }

    /**
     * Get the table prefix for the connection.
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * Set the table prefix in use by the connection.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function setTablePrefix($prefix)
    {
        $this->tablePrefix = $prefix;

        return $this;
    }

    public function getDateFormat()
    {
        return 'Y-m-d H:i:s';
    }
}

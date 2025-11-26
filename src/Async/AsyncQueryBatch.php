<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Async;

use Doctrine\DBAL\Async\Exception\AsyncNotSupported;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\AsyncConnection;
use Doctrine\DBAL\Driver\Mysqli\Connection as MysqliConnection;
use Doctrine\DBAL\Driver\PgSQL\Connection as PgSQLConnection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use mysqli;

use function array_keys;
use function array_values;
use function count;
use function get_class;
use function is_bool;
use function is_float;
use function is_int;
use function mysqli_poll;
use function pg_connection_busy;
use function strpos;
use function substr_replace;
use function usleep;

/**
 * Executes multiple queries in parallel using async driver capabilities.
 *
 * This class manages the lifecycle of parallel query execution:
 * 1. Creates temporary connections for each query
 * 2. Sends all queries asynchronously
 * 3. Polls for completion
 * 4. Collects and returns results
 */
final class AsyncQueryBatch
{
    private Connection $connection;
    private Driver $driver;

    /** @var array<string, mixed> */
    private array $params;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->driver     = $connection->getDriver();
        $this->params     = $connection->getParams();
    }

    /**
     * Executes multiple queries in parallel and returns all results.
     *
     * @param AsyncQuery[] $queries The queries to execute in parallel
     *
     * @return Result[] The results in the same order as the input queries
     *
     * @throws AsyncNotSupported If the driver does not support async queries
     * @throws Exception         If query execution fails
     */
    public function execute(array $queries): array
    {
        if (count($queries) === 0) {
            throw AsyncNotSupported::emptyQueryBatch();
        }

        // Create async connections for each query
        $asyncConnections = $this->createAsyncConnections(count($queries));

        // Send all queries
        $this->sendQueries($asyncConnections, $queries);

        // Wait for all results
        $this->waitForCompletion($asyncConnections);

        // Collect results (connections will be automatically cleaned up when they go out of scope)
        return $this->collectResults($asyncConnections);
    }

    /**
     * Creates multiple async-capable connections.
     *
     * @return AsyncConnection[]
     *
     * @throws AsyncNotSupported
     * @throws Exception
     */
    private function createAsyncConnections(int $count): array
    {
        $connections = [];

        for ($i = 0; $i < $count; $i++) {
            $driverConnection = $this->driver->connect($this->params);

            if (! $driverConnection instanceof AsyncConnection) {
                throw AsyncNotSupported::driverNotSupported(get_class($driverConnection));
            }

            $connections[] = $driverConnection;
        }

        return $connections;
    }

    /**
     * Sends all queries to their respective connections.
     *
     * @param AsyncConnection[] $connections
     * @param AsyncQuery[]      $queries
     */
    private function sendQueries(array $connections, array $queries): void
    {
        foreach ($queries as $index => $query) {
            $connection = $connections[$index];
            $sql        = $query->getSQL();
            $params     = $query->getParams();

            // For mysqli, we need to handle parameter substitution differently
            // since mysqli async doesn't support prepared statements
            if ($connection instanceof MysqliConnection) {
                $sql = $this->substituteParamsForMysqli($connection, $sql, $params);
                $connection->sendQueryAsync($sql, []);
            } else {
                // For PostgreSQL, we can pass params directly
                // But we need to convert named params to positional if needed
                $connection->sendQueryAsync($sql, $this->convertToPositionalParams($params));
            }
        }
    }

    /**
     * Substitutes parameters into SQL for mysqli async queries.
     *
     * @param list<mixed>|array<string, mixed> $params
     */
    private function substituteParamsForMysqli(MysqliConnection $connection, string $sql, array $params): string
    {
        if (count($params) === 0) {
            return $sql;
        }

        $mysqli = $connection->getNativeConnection();

        // Simple positional parameter substitution
        foreach ($params as $param) {
            $escaped = $this->escapeValueForMysqli($mysqli, $param);
            $pos     = strpos($sql, '?');
            if ($pos !== false) {
                $sql = substr_replace($sql, $escaped, $pos, 1);
            }
        }

        return $sql;
    }

    /**
     * Escapes a value for use in a mysqli query.
     *
     * @param mixed $value
     */
    private function escapeValueForMysqli(mysqli $mysqli, $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . $mysqli->escape_string((string) $value) . "'";
    }

    /**
     * Converts named parameters to positional parameters.
     *
     * @param list<mixed>|array<string, mixed> $params
     *
     * @return list<mixed>
     */
    private function convertToPositionalParams(array $params): array
    {
        if (count($params) === 0) {
            return [];
        }

        // If already positional (numeric keys), return as-is
        $keys = array_keys($params);
        if (is_int($keys[0])) {
            return array_values($params);
        }

        // For named params, just return values (SQL must use $1, $2 style for PgSQL)
        return array_values($params);
    }

    /**
     * Waits for all async queries to complete.
     *
     * @param AsyncConnection[] $connections
     */
    private function waitForCompletion(array $connections): void
    {
        // Determine wait strategy based on connection type
        $first = $connections[0] ?? null;

        if ($first instanceof MysqliConnection) {
            $this->waitForMysqliCompletion($connections);
        } elseif ($first instanceof PgSQLConnection) {
            $this->waitForPgSQLCompletion($connections);
        }
    }

    /**
     * Waits for mysqli async queries using mysqli_poll.
     *
     * @param AsyncConnection[] $connections
     */
    private function waitForMysqliCompletion(array $connections): void
    {
        $pending = [];
        foreach ($connections as $index => $connection) {
            if ($connection instanceof MysqliConnection) {
                $pending[$index] = $connection->getNativeConnection();
            }
        }

        while (count($pending) > 0) {
            $read   = array_values($pending);
            $error  = [];
            $reject = [];

            // Poll with 1 second timeout
            $result = mysqli_poll($read, $error, $reject, 1);

            if ($result === false) {
                break;
            }

            // Remove completed connections from pending
            foreach ($read as $readyConnection) {
                foreach ($pending as $index => $mysqli) {
                    if ($mysqli === $readyConnection) {
                        unset($pending[$index]);
                        break;
                    }
                }
            }

            // Small sleep to prevent busy-waiting
            if (count($pending) > 0 && $result === 0) {
                usleep(1000); // 1ms
            }
        }
    }

    /**
     * Waits for PostgreSQL async queries to complete.
     *
     * @param AsyncConnection[] $connections
     */
    private function waitForPgSQLCompletion(array $connections): void
    {
        $pending = [];
        foreach ($connections as $index => $connection) {
            if ($connection instanceof PgSQLConnection) {
                $pending[$index] = $connection;
            }
        }

        while (count($pending) > 0) {
            foreach ($pending as $index => $connection) {
                $nativeConnection = $connection->getNativeConnection();
                if (! pg_connection_busy($nativeConnection)) {
                    unset($pending[$index]);
                }
            }

            // Small sleep to prevent busy-waiting
            if (count($pending) > 0) {
                usleep(1000); // 1ms
            }
        }
    }

    /**
     * Collects results from all completed async queries.
     *
     * @param AsyncConnection[] $connections
     *
     * @return Result[]
     */
    private function collectResults(array $connections): array
    {
        $results = [];

        foreach ($connections as $connection) {
            $driverResult = $connection->getAsyncResult();
            $results[]    = new Result($driverResult, $this->connection);
        }

        return $results;
    }
}


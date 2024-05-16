<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Exception\NoKeyValue;
use Traversable;

use function array_shift;
use function assert;
use function count;

class Result
{
    /** @internal The result can be only instantiated by {@see Connection} or {@see Statement}. */
    public function __construct(private readonly DriverResult $result, private readonly Connection $connection)
    {
    }

    /**
     * Returns the next row of the result as a numeric array or FALSE if there are no more rows.
     *
     * @return list<mixed>|false
     *
     * @throws Exception
     */
    public function fetchNumeric(): array|false
    {
        try {
            return $this->result->fetchNumeric();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * Returns the next row of the result as an associative array or FALSE if there are no more rows.
     *
     * @return array<string,mixed>|false
     *
     * @throws Exception
     */
    public function fetchAssociative(): array|false
    {
        try {
            return $this->result->fetchAssociative();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * Returns the first value of the next row of the result or FALSE if there are no more rows.
     *
     * @throws Exception
     */
    public function fetchOne(): mixed
    {
        try {
            return $this->result->fetchOne();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * Returns an array containing all of the result rows represented as numeric arrays.
     *
     * @return list<list<mixed>>
     *
     * @throws Exception
     */
    public function fetchAllNumeric(): array
    {
        try {
            return $this->result->fetchAllNumeric();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * Returns an array containing all of the result rows represented as associative arrays.
     *
     * @return list<array<string,mixed>>
     *
     * @throws Exception
     */
    public function fetchAllAssociative(): array
    {
        try {
            return $this->result->fetchAllAssociative();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * Returns an array containing the values of the first column of the result.
     *
     * @return array<mixed,mixed>
     *
     * @throws Exception
     */
    public function fetchAllKeyValue(): array
    {
        $this->ensureHasKeyValue();

        $data = [];

        foreach ($this->fetchAllNumeric() as $row) {
            assert(count($row) >= 2);
            [$key, $value] = $row;
            $data[$key]    = $value;
        }

        return $data;
    }

    /**
     * Returns an associative array with the keys mapped to the first column and the values being
     * an associative array representing the rest of the columns and their values.
     *
     * @return array<mixed,array<string,mixed>>
     *
     * @throws Exception
     */
    public function fetchAllAssociativeIndexed(): array
    {
        $data = [];

        foreach ($this->fetchAllAssociative() as $row) {
            $data[array_shift($row)] = $row;
        }

        return $data;
    }

    /**
     * @return list<mixed>
     *
     * @throws Exception
     */
    public function fetchFirstColumn(): array
    {
        try {
            return $this->result->fetchFirstColumn();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * @return Traversable<int,list<mixed>>
     *
     * @throws Exception
     */
    public function iterateNumeric(): Traversable
    {
        while (($row = $this->fetchNumeric()) !== false) {
            yield $row;
        }
    }

    /**
     * @return Traversable<int,array<string,mixed>>
     *
     * @throws Exception
     */
    public function iterateAssociative(): Traversable
    {
        while (($row = $this->fetchAssociative()) !== false) {
            yield $row;
        }
    }

    /**
     * @return Traversable<mixed, mixed>
     *
     * @throws Exception
     */
    public function iterateKeyValue(): Traversable
    {
        $this->ensureHasKeyValue();

        foreach ($this->iterateNumeric() as $row) {
            assert(count($row) >= 2);
            [$key, $value] = $row;

            yield $key => $value;
        }
    }

    /**
     * Returns an iterator over the result set with the keys mapped to the first column and the values being
     * an associative array representing the rest of the columns and their values.
     *
     * @return Traversable<mixed,array<string,mixed>>
     *
     * @throws Exception
     */
    public function iterateAssociativeIndexed(): Traversable
    {
        foreach ($this->iterateAssociative() as $row) {
            yield array_shift($row) => $row;
        }
    }

    /**
     * @return Traversable<int,mixed>
     *
     * @throws Exception
     */
    public function iterateColumn(): Traversable
    {
        while (($value = $this->fetchOne()) !== false) {
            yield $value;
        }
    }

    /**
     * Returns the number of rows affected by the DELETE, INSERT, or UPDATE statement that produced the result.
     *
     * If the statement executed a SELECT query or a similar platform-specific SQL (e.g. DESCRIBE, SHOW, etc.),
     * some database drivers may return the number of rows returned by that query. However, this behaviour
     * is not guaranteed for all drivers and should not be relied on in portable applications.
     *
     * If the number of rows exceeds {@see PHP_INT_MAX}, it might be returned as string if the driver supports it.
     *
     * @return int|numeric-string
     *
     * @throws Exception
     */
    public function rowCount(): int|string
    {
        try {
            return $this->result->rowCount();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /** @throws Exception */
    public function columnCount(): int
    {
        try {
            return $this->result->columnCount();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    public function free(): void
    {
        $this->result->free();
    }

    /** @throws Exception */
    private function ensureHasKeyValue(): void
    {
        $columnCount = $this->columnCount();

        if ($columnCount < 2) {
            throw NoKeyValue::fromColumnCount($columnCount);
        }
    }
}

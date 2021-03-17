<?php

namespace Doctrine\DBAL\ForwardCompatibility;

use Doctrine\DBAL\Driver\ResultStatement as DriverResultStatement;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\NoKeyValue;
use Doctrine\DBAL\Result as BaseResult;
use IteratorAggregate;
use PDO;
use Traversable;

use function array_shift;
use function method_exists;

/**
 * A wrapper around a Doctrine\DBAL\Driver\ResultStatement that adds 3.0 features
 * defined in Result interface
 */
class Result implements IteratorAggregate, DriverResultStatement, BaseResult
{
    /** @var DriverResultStatement */
    private $stmt;

    public function __construct(DriverResultStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    /**
     * @return DriverResultStatement
     */
    public function getIterator()
    {
        return $this->stmt;
    }

    /**
     * {@inheritDoc}
     */
    public function closeCursor()
    {
        return $this->stmt->closeCursor();
    }

    /**
     * {@inheritDoc}
     */
    public function columnCount()
    {
        return $this->stmt->columnCount();
    }

    /**
     * {@inheritDoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        return $this->stmt->setFetchMode($fetchMode, $arg2, $arg3);
    }

    /**
     * {@inheritDoc}
     */
    public function fetch($fetchMode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        return $this->stmt->fetch($fetchMode, $cursorOrientation, $cursorOffset);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        return $this->stmt->fetchAll($fetchMode, $fetchArgument, $ctorArgs);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        return $this->stmt->fetchColumn($columnIndex);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchNumeric()
    {
        return $this->stmt->fetch(PDO::FETCH_NUM);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAssociative()
    {
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchOne()
    {
        $row = $this->fetchNumeric();

        if ($row === false) {
            return false;
        }

        return $row[0];
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllNumeric(): array
    {
        $rows = [];

        while (($row = $this->fetchNumeric()) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllAssociative(): array
    {
        $rows = [];

        while (($row = $this->fetchAssociative()) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllKeyValue(): array
    {
        $this->ensureHasKeyValue();
        $data = [];

        foreach ($this->fetchAllNumeric() as [$key, $value]) {
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function fetchFirstColumn(): array
    {
        $rows = [];

        while (($row = $this->fetchOne()) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     *
     * @return Traversable<int,array<int,mixed>>
     */
    public function iterateNumeric(): Traversable
    {
        while (($row = $this->fetchNumeric()) !== false) {
            yield $row;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return Traversable<int,array<string,mixed>>
     */
    public function iterateAssociative(): Traversable
    {
        while (($row = $this->fetchAssociative()) !== false) {
            yield $row;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return Traversable<mixed,mixed>
     */
    public function iterateKeyValue(): Traversable
    {
        $this->ensureHasKeyValue();

        foreach ($this->iterateNumeric() as [$key, $value]) {
            yield $key => $value;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return Traversable<mixed,array<string,mixed>>
     */
    public function iterateAssociativeIndexed(): Traversable
    {
        foreach ($this->iterateAssociative() as $row) {
            yield array_shift($row) => $row;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return Traversable<int,mixed>
     */
    public function iterateColumn(): Traversable
    {
        while (($value = $this->fetchOne()) !== false) {
            yield $value;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function rowCount()
    {
        if (method_exists($this->stmt, 'rowCount')) {
            return $this->stmt->rowCount();
        }

        throw Exception::notSupported('rowCount');
    }

    public function free(): void
    {
        $this->closeCursor();
    }

    private function ensureHasKeyValue(): void
    {
        $columnCount = $this->columnCount();

        if ($columnCount < 2) {
            throw NoKeyValue::fromColumnCount($columnCount);
        }
    }
}

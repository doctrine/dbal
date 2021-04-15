<?php

namespace Doctrine\DBAL\ForwardCompatibility;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\NoKeyValue;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use IteratorAggregate;
use PDO;
use Traversable;

use function array_shift;
use function method_exists;

/**
 * A wrapper around a Doctrine\DBAL\Driver\ResultStatement that adds 3.0 features
 * defined in Result interface
 */
class Result implements IteratorAggregate, DriverStatement, DriverResultStatement
{
    /** @var Driver\ResultStatement */
    private $stmt;

    public static function ensure(Driver\ResultStatement $stmt): Result
    {
        if ($stmt instanceof Result) {
            return $stmt;
        }

        return new Result($stmt);
    }

    public function __construct(Driver\ResultStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    /**
     * @return Driver\ResultStatement
     */
    public function getIterator()
    {
        return $this->stmt;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use Result::free() instead.
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
     *
     * @deprecated Use one of the fetch- or iterate-related methods.
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        return $this->stmt->setFetchMode($fetchMode, $arg2, $arg3);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use fetchNumeric(), fetchAssociative() or fetchOne() instead.
     */
    public function fetch($fetchMode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4019',
            'Result::fetch() is deprecated, use Result::fetchNumeric(), fetchAssociative() or fetchOne() instead.'
        );

        return $this->stmt->fetch($fetchMode, $cursorOrientation, $cursorOffset);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use fetchAllNumeric(), fetchAllAssociative() or fetchFirstColumn() instead.
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4019',
            'Result::fetchAll() is deprecated, use Result::fetchAllNumeric(), fetchAllAssociative() or ' .
            'fetchFirstColumn() instead.'
        );

        return $this->stmt->fetchAll($fetchMode, $fetchArgument, $ctorArgs);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use fetchOne() instead.
     */
    public function fetchColumn($columnIndex = 0)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4019',
            'Result::fetchColumn() is deprecated, use Result::fetchOne() instead.'
        );

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

    /**
     * {@inheritDoc}
     *
     * @deprecated This feature will no longer be available on Result object in 3.0.x version.
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4019',
            'Result::bindValue() is deprecated, no replacement.'
        );

        if ($this->stmt instanceof Driver\Statement) {
            return $this->stmt->bindValue($param, $value, $type);
        }

        throw Exception::notSupported('bindValue');
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated This feature will no longer be available on Result object in 3.0.x version.
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4019',
            'Result::bindParam() is deprecated, no replacement.'
        );

        if ($this->stmt instanceof Driver\Statement) {
            return $this->stmt->bindParam($param, $variable, $type, $length);
        }

        throw Exception::notSupported('bindParam');
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated The error information is available via exceptions.
     */
    public function errorCode()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4019',
            'Result::errorCode() is deprecated, the error information is available via exceptions.'
        );

        if ($this->stmt instanceof Driver\Statement) {
            return $this->stmt->errorCode();
        }

        throw Exception::notSupported('errorCode');
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated The error information is available via exceptions.
     */
    public function errorInfo()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4019',
            'Result::errorInfo() is deprecated, the error information is available via exceptions.'
        );

        if ($this->stmt instanceof Driver\Statement) {
            return $this->stmt->errorInfo();
        }

        throw Exception::notSupported('errorInfo');
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated This feature will no longer be available on Result object in 3.0.x version.
     */
    public function execute($params = null)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4019',
            'Result::execute() is deprecated, no replacement.'
        );

        if ($this->stmt instanceof Driver\Statement) {
            return $this->stmt->execute($params);
        }

        throw Exception::notSupported('execute');
    }
}

<?php

namespace Doctrine\DBAL;

use Doctrine\DBAL\Abstraction\Result;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Exception\NoKeyValue;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
use IteratorAggregate;
use PDO;
use PDOStatement;
use Throwable;
use Traversable;

use function array_shift;
use function is_array;
use function is_string;

/**
 * A thin wrapper around a Doctrine\DBAL\Driver\Statement that adds support
 * for logging, DBAL mapping types, etc.
 */
class Statement implements IteratorAggregate, DriverStatement, Result
{
    /**
     * The SQL statement.
     *
     * @var string
     */
    protected $sql;

    /**
     * The bound parameters.
     *
     * @var mixed[]
     */
    protected $params = [];

    /**
     * The parameter types.
     *
     * @var int[]|string[]
     */
    protected $types = [];

    /**
     * The underlying driver statement.
     *
     * @var \Doctrine\DBAL\Driver\Statement
     */
    protected $stmt;

    /**
     * The underlying database platform.
     *
     * @var AbstractPlatform
     */
    protected $platform;

    /**
     * The connection this statement is bound to and executed on.
     *
     * @var Connection
     */
    protected $conn;

    /**
     * Creates a new <tt>Statement</tt> for the given SQL and <tt>Connection</tt>.
     *
     * @internal The statement can be only instantiated by {@link Connection}.
     *
     * @param string     $sql  The SQL of the statement.
     * @param Connection $conn The connection on which the statement should be executed.
     */
    public function __construct($sql, Connection $conn)
    {
        $this->sql      = $sql;
        $this->stmt     = $conn->getWrappedConnection()->prepare($sql);
        $this->conn     = $conn;
        $this->platform = $conn->getDatabasePlatform();
    }

    /**
     * Binds a parameter value to the statement.
     *
     * The value can optionally be bound with a PDO binding type or a DBAL mapping type.
     * If bound with a DBAL mapping type, the binding type is derived from the mapping
     * type and the value undergoes the conversion routines of the mapping type before
     * being bound.
     *
     * @param string|int $param The name or position of the parameter.
     * @param mixed      $value The value of the parameter.
     * @param mixed      $type  Either a PDO binding type or a DBAL mapping type name or instance.
     *
     * @return bool TRUE on success, FALSE on failure.
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        $this->params[$param] = $value;
        $this->types[$param]  = $type;
        if ($type !== null) {
            if (is_string($type)) {
                $type = Type::getType($type);
            }

            if ($type instanceof Type) {
                $value       = $type->convertToDatabaseValue($value, $this->platform);
                $bindingType = $type->getBindingType();
            } else {
                $bindingType = $type;
            }

            return $this->stmt->bindValue($param, $value, $bindingType);
        }

        return $this->stmt->bindValue($param, $value);
    }

    /**
     * Binds a parameter to a value by reference.
     *
     * Binding a parameter by reference does not support DBAL mapping types.
     *
     * @param string|int $param    The name or position of the parameter.
     * @param mixed      $variable The reference to the variable to bind.
     * @param int        $type     The PDO binding type.
     * @param int|null   $length   Must be specified when using an OUT bind
     *                             so that PHP allocates enough memory to hold the returned value.
     *
     * @return bool TRUE on success, FALSE on failure.
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        $this->params[$param] = $variable;
        $this->types[$param]  = $type;

        if ($this->stmt instanceof PDOStatement) {
            $length = $length ?? 0;
        }

        return $this->stmt->bindParam($param, $variable, $type, $length);
    }

    /**
     * Executes the statement with the currently bound parameters.
     *
     * @param mixed[]|null $params
     *
     * @return bool TRUE on success, FALSE on failure.
     *
     * @throws Exception
     */
    public function execute($params = null)
    {
        if (is_array($params)) {
            $this->params = $params;
        }

        $logger = $this->conn->getConfiguration()->getSQLLogger();
        if ($logger) {
            $logger->startQuery($this->sql, $this->params, $this->types);
        }

        try {
            $stmt = $this->stmt->execute($params);
        } catch (Throwable $ex) {
            if ($logger) {
                $logger->stopQuery();
            }

            $this->conn->handleExceptionDuringQuery($ex, $this->sql, $this->params, $this->types);
        }

        if ($logger) {
            $logger->stopQuery();
        }

        return $stmt;
    }

    /**
     * Closes the cursor, freeing the database resources used by this statement.
     *
     * @deprecated Use Result::free() instead.
     *
     * @return bool TRUE on success, FALSE on failure.
     */
    public function closeCursor()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4049',
            'Statement::closeCursor() is deprecated, use Result::free() instead.'
        );

        return $this->stmt->closeCursor();
    }

    /**
     * Returns the number of columns in the result set.
     *
     * @return int
     */
    public function columnCount()
    {
        return $this->stmt->columnCount();
    }

    /**
     * Fetches the SQLSTATE associated with the last operation on the statement.
     *
     * @deprecated The error information is available via exceptions.
     *
     * @return string|int|bool
     */
    public function errorCode()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/3507',
            'Connection::errorCode() is deprecated, use getCode() or getSQLState() on Exception instead.'
        );

        return $this->stmt->errorCode();
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
            'https://github.com/doctrine/dbal/pull/3507',
            'Connection::errorInfo() is deprecated, use getCode() or getSQLState() on Exception instead.'
        );

        return $this->stmt->errorInfo();
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use one of the fetch- or iterate-related methods.
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4019',
            'Statement::setFetchMode() is deprecated, use explicit Result::fetch*() APIs instead.'
        );

        if ($arg2 === null) {
            return $this->stmt->setFetchMode($fetchMode);
        }

        if ($arg3 === null) {
            return $this->stmt->setFetchMode($fetchMode, $arg2);
        }

        return $this->stmt->setFetchMode($fetchMode, $arg2, $arg3);
    }

    /**
     * Required by interface IteratorAggregate.
     *
     * @deprecated Use iterateNumeric(), iterateAssociative() or iterateColumn() instead.
     *
     * {@inheritdoc}
     */
    public function getIterator()
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4019',
            'Statement::getIterator() is deprecated, use Result::iterateNumeric(), iterateAssociative() ' .
            'or iterateColumn() instead.'
        );

        return $this->stmt;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use fetchNumeric(), fetchAssociative() or fetchOne() instead.
     */
    public function fetch($fetchMode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4019',
            'Statement::fetch() is deprecated, use Result::fetchNumeric(), fetchAssociative() or fetchOne() instead.'
        );

        return $this->stmt->fetch($fetchMode);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use fetchAllNumeric(), fetchAllAssociative() or fetchFirstColumn() instead.
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4019',
            'Statement::fetchAll() is deprecated, use Result::fetchAllNumeric(), fetchAllAssociative() or ' .
            'fetchFirstColumn() instead.'
        );

        if ($ctorArgs !== null) {
            return $this->stmt->fetchAll($fetchMode, $fetchArgument, $ctorArgs);
        }

        if ($fetchArgument !== null) {
            return $this->stmt->fetchAll($fetchMode, $fetchArgument);
        }

        return $this->stmt->fetchAll($fetchMode);
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
            'Statement::fetchColumn() is deprecated, use Result::fetchOne() instead.'
        );

        return $this->stmt->fetchColumn($columnIndex);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use Result::fetchNumeric() instead
     *
     * @throws Exception
     */
    public function fetchNumeric()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        try {
            if ($this->stmt instanceof Result) {
                return $this->stmt->fetchNumeric();
            }

            return $this->stmt->fetch(FetchMode::NUMERIC);
        } catch (Exception $e) {
            $this->conn->handleDriverException($e);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use Result::fetchAssociative() instead
     *
     * @throws Exception
     */
    public function fetchAssociative()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        try {
            if ($this->stmt instanceof Result) {
                return $this->stmt->fetchAssociative();
            }

            return $this->stmt->fetch(FetchMode::ASSOCIATIVE);
        } catch (Exception $e) {
            $this->conn->handleDriverException($e);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use Result::fetchOne() instead
     *
     * @throws Exception
     */
    public function fetchOne()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        try {
            if ($this->stmt instanceof Result) {
                return $this->stmt->fetchOne();
            }

            return $this->stmt->fetch(FetchMode::COLUMN);
        } catch (Exception $e) {
            $this->conn->handleDriverException($e);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use Result::fetchAllNumeric() instead
     *
     * @throws Exception
     */
    public function fetchAllNumeric(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        try {
            if ($this->stmt instanceof Result) {
                return $this->stmt->fetchAllNumeric();
            }

            return $this->stmt->fetchAll(FetchMode::NUMERIC);
        } catch (Exception $e) {
            $this->conn->handleDriverException($e);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use Result::fetchAllAssociative() instead
     *
     * @throws Exception
     */
    public function fetchAllAssociative(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        try {
            if ($this->stmt instanceof Result) {
                return $this->stmt->fetchAllAssociative();
            }

            return $this->stmt->fetchAll(FetchMode::ASSOCIATIVE);
        } catch (Exception $e) {
            $this->conn->handleDriverException($e);
        }
    }

    /**
     * Returns an associative array with the keys mapped to the first column and the values mapped to the second column.
     *
     * The result must contain at least two columns.
     *
     * @deprecated Use Result::fetchAllKeyValue() instead
     *
     * @return array<mixed,mixed>
     *
     * @throws Exception
     */
    public function fetchAllKeyValue(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        $this->ensureHasKeyValue();

        $data = [];

        foreach ($this->fetchAllNumeric() as [$key, $value]) {
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Returns an associative array with the keys mapped to the first column and the values being
     * an associative array representing the rest of the columns and their values.
     *
     * @deprecated Use Result::fetchAllAssociativeIndexed() instead
     *
     * @return array<mixed,array<string,mixed>>
     *
     * @throws Exception
     */
    public function fetchAllAssociativeIndexed(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        $data = [];

        foreach ($this->fetchAll(FetchMode::ASSOCIATIVE) as $row) {
            $data[array_shift($row)] = $row;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use Result::fetchFirstColumn() instead
     *
     * @throws Exception
     */
    public function fetchFirstColumn(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        try {
            if ($this->stmt instanceof Result) {
                return $this->stmt->fetchFirstColumn();
            }

            return $this->stmt->fetchAll(FetchMode::COLUMN);
        } catch (Exception $e) {
            $this->conn->handleDriverException($e);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use Result::iterateNumeric() instead
     *
     * @return Traversable<int,array<int,mixed>>
     *
     * @throws Exception
     */
    public function iterateNumeric(): Traversable
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        try {
            if ($this->stmt instanceof Result) {
                while (($row = $this->stmt->fetchNumeric()) !== false) {
                    yield $row;
                }
            } else {
                while (($row = $this->stmt->fetch(FetchMode::NUMERIC)) !== false) {
                    yield $row;
                }
            }
        } catch (Exception $e) {
            $this->conn->handleDriverException($e);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use Result::iterateAssociative() instead
     *
     * @return Traversable<int,array<string,mixed>>
     *
     * @throws Exception
     */
    public function iterateAssociative(): Traversable
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        try {
            if ($this->stmt instanceof Result) {
                while (($row = $this->stmt->fetchAssociative()) !== false) {
                    yield $row;
                }
            } else {
                while (($row = $this->stmt->fetch(FetchMode::ASSOCIATIVE)) !== false) {
                    yield $row;
                }
            }
        } catch (Exception $e) {
            $this->conn->handleDriverException($e);
        }
    }

    /**
     * Returns an iterator over the result set with the keys mapped to the first column
     * and the values mapped to the second column.
     *
     * The result must contain at least two columns.
     *
     * @deprecated Use Result::iterateKeyValue() instead
     *
     * @return Traversable<mixed,mixed>
     *
     * @throws Exception
     */
    public function iterateKeyValue(): Traversable
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        $this->ensureHasKeyValue();

        foreach ($this->iterateNumeric() as [$key, $value]) {
            yield $key => $value;
        }
    }

    /**
     * Returns an iterator over the result set with the keys mapped to the first column and the values being
     * an associative array representing the rest of the columns and their values.
     *
     * @deprecated Use Result::iterateAssociativeIndexed() instead
     *
     * @return Traversable<mixed,array<string,mixed>>
     *
     * @throws Exception
     */
    public function iterateAssociativeIndexed(): Traversable
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        while (($row = $this->stmt->fetch(FetchMode::ASSOCIATIVE)) !== false) {
            yield array_shift($row) => $row;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use Result::iterateColumn() instead
     *
     * @return Traversable<int,mixed>
     *
     * @throws Exception
     */
    public function iterateColumn(): Traversable
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4554',
            'Statement::%s() is deprecated, use Result::%s() instead.',
            __FUNCTION__,
            __FUNCTION__
        );

        try {
            if ($this->stmt instanceof Result) {
                while (($value = $this->stmt->fetchOne()) !== false) {
                    yield $value;
                }
            } else {
                while (($value = $this->stmt->fetch(FetchMode::COLUMN)) !== false) {
                    yield $value;
                }
            }
        } catch (Exception $e) {
            $this->conn->handleDriverException($e);
        }
    }

    /**
     * Returns the number of rows affected by the last execution of this statement.
     *
     * @return int The number of affected rows.
     */
    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

    public function free(): void
    {
        if ($this->stmt instanceof Result) {
            $this->stmt->free();

            return;
        }

        $this->stmt->closeCursor();
    }

    /**
     * Gets the wrapped driver statement.
     *
     * @return \Doctrine\DBAL\Driver\Statement
     */
    public function getWrappedStatement()
    {
        return $this->stmt;
    }

    private function ensureHasKeyValue(): void
    {
        $columnCount = $this->columnCount();

        if ($columnCount < 2) {
            throw NoKeyValue::fromColumnCount($columnCount);
        }
    }
}

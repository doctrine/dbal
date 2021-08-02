<?php

namespace Doctrine\Tests\DBAL\Functional\Connection\BackwardCompatibility;

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement as BaseStatement;
use PDO;
use Traversable;

use function assert;

/**
 * A wrapper that does not implement the forward-compatible statement interface.
 */
class Statement extends BaseStatement
{
    /** @var DriverStatement|ResultStatement */
    protected $stmt;

    /**
     * @param DriverStatement|ResultStatement $stmt
     */
    public function __construct($stmt)
    {
        $this->stmt = $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        assert($this->stmt instanceof DriverStatement);

        return $this->stmt->bindParam($param, $variable, $type, $length);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        assert($this->stmt instanceof DriverStatement);

        return $this->stmt->bindValue($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        return $this->stmt->closeCursor();
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->stmt->columnCount();
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated The error information is available via exceptions.
     */
    public function errorCode()
    {
        assert($this->stmt instanceof DriverStatement);

        return $this->stmt->errorCode();
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated The error information is available via exceptions.
     */
    public function errorInfo()
    {
        assert($this->stmt instanceof DriverStatement);

        return $this->stmt->errorInfo();
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        assert($this->stmt instanceof DriverStatement);

        return $this->stmt->execute($params);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use one of the fetch- or iterate-related methods.
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        return $this->stmt->setFetchMode($fetchMode, $arg2, $arg3);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use iterateNumeric(), iterateAssociative() or iterateColumn() instead.
     */
    public function getIterator(): Traversable
    {
        return new StatementIterator($this);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use fetchNumeric(), fetchAssociative() or fetchOne() instead.
     */
    public function fetch($fetchMode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        return $this->stmt->fetch($fetchMode);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use fetchAllNumeric(), fetchAllAssociative() or fetchColumn() instead.
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        return $this->stmt->fetchAll($fetchMode, $fetchArgument);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use fetchOne() instead.
     */
    public function fetchColumn($columnIndex = 0)
    {
        return $this->stmt->fetchColumn($columnIndex);
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        assert($this->stmt instanceof DriverStatement);

        return $this->stmt->rowCount();
    }
}

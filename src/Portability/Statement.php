<?php

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use function assert;

/**
 * Portability wrapper for a Statement.
 */
class Statement implements DriverStatement
{
    /** @var DriverStatement|ResultStatement */
    private $stmt;

    /** @var Converter */
    private $converter;

    /**
     * Wraps <tt>Statement</tt> and applies portability measures.
     *
     * @param DriverStatement|ResultStatement $stmt
     */
    public function __construct($stmt, Converter $converter)
    {
        $this->stmt      = $stmt;
        $this->converter = $converter;
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null)
    {
        assert($this->stmt instanceof DriverStatement);

        return $this->stmt->bindParam($column, $variable, $type, $length);
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
     */
    public function execute($params = null)
    {
        assert($this->stmt instanceof DriverStatement);

        return $this->stmt->execute($params);
    }

    public function rowCount() : int
    {
        assert($this->stmt instanceof DriverStatement);

        return $this->stmt->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNumeric()
    {
        return $this->converter->convertNumeric(
            $this->stmt->fetchNumeric()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssociative()
    {
        return $this->converter->convertAssociative(
            $this->stmt->fetchAssociative()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne()
    {
        return $this->converter->convertOne(
            $this->stmt->fetchOne()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllNumeric() : array
    {
        return $this->converter->convertAllNumeric(
            $this->stmt->fetchAllNumeric()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative() : array
    {
        return $this->converter->convertAllAssociative(
            $this->stmt->fetchAllAssociative()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn() : array
    {
        return $this->converter->convertFirstColumn(
            $this->stmt->fetchColumn()
        );
    }
}

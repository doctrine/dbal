<?php

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;

/**
 * Portability wrapper for a Statement.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class Statement implements \IteratorAggregate, \Doctrine\DBAL\Driver\Statement
{
    /**
     * @var integer
     */
    private $portability;

    /**
     * @var \Doctrine\DBAL\Driver\Statement
     */
    private $stmt;

    /**
     * @var integer
     */
    private $case;

    /**
     * @var integer
     */
    private $defaultFetchMode = FetchMode::MIXED;

    /**
     * Wraps <tt>Statement</tt> and applies portability measures.
     *
     * @param \Doctrine\DBAL\Driver\Statement       $stmt
     * @param \Doctrine\DBAL\Portability\Connection $conn
     */
    public function __construct($stmt, Connection $conn)
    {
        $this->stmt = $stmt;
        $this->portability = $conn->getPortability();
        $this->case = $conn->getFetchCase();
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null)
    {
        return $this->stmt->bindParam($column, $variable, $type, $length);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
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
    public function errorCode()
    {
        return $this->stmt->errorCode();
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return $this->stmt->errorInfo();
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        return $this->stmt->execute($params);
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, ...$args)
    {
        $this->defaultFetchMode = $fetchMode;

        return $this->stmt->setFetchMode($fetchMode, ...$args);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $data = $this->fetchAll();

        return new \ArrayIterator($data);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, ...$args)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        $row = $this->stmt->fetch($fetchMode, ...$args);

        $iterateRow = $this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM);
        $fixCase    = ! is_null($this->case) && ($fetchMode == FetchMode::ASSOCIATIVE || $fetchMode == FetchMode::MIXED)
            && ($this->portability & Connection::PORTABILITY_FIX_CASE);

        $row = $this->fixRow($row, $iterateRow, $fixCase);

        return $row;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, ...$args)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        $rows = $this->stmt->fetchAll($fetchMode, ...$args);

        $iterateRow = $this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM);
        $fixCase    = ! is_null($this->case)
            && ($fetchMode === FetchMode::ASSOCIATIVE || $fetchMode === FetchMode::MIXED)
            && ($this->portability & Connection::PORTABILITY_FIX_CASE);

        if ( ! $iterateRow && !$fixCase) {
            return $rows;
        }

        if ($fetchMode === FetchMode::COLUMN) {
            foreach ($rows as $num => $row) {
                $rows[$num] = [$row];
            }
        }

        foreach ($rows as $num => $row) {
            $rows[$num] = $this->fixRow($row, $iterateRow, $fixCase);
        }

        if ($fetchMode === FetchMode::COLUMN) {
            foreach ($rows as $num => $row) {
                $rows[$num] = $row[0];
            }
        }

        return $rows;
    }

    /**
     * @param mixed   $row
     * @param integer $iterateRow
     * @param boolean $fixCase
     *
     * @return array
     */
    protected function fixRow($row, $iterateRow, $fixCase)
    {
        if ( ! $row) {
            return $row;
        }

        if ($fixCase) {
            $row = array_change_key_case($row, $this->case);
        }

        if ($iterateRow) {
            foreach ($row as $k => $v) {
                if (($this->portability & Connection::PORTABILITY_EMPTY_TO_NULL) && $v === '') {
                    $row[$k] = null;
                } elseif (($this->portability & Connection::PORTABILITY_RTRIM) && is_string($v)) {
                    $row[$k] = rtrim($v);
                }
            }
        }

        return $row;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        $value = $this->stmt->fetchColumn($columnIndex);

        if ($this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM)) {
            if (($this->portability & Connection::PORTABILITY_EMPTY_TO_NULL) && $value === '') {
                $value = null;
            } elseif (($this->portability & Connection::PORTABILITY_RTRIM) && is_string($value)) {
                $value = rtrim($value);
            }
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        return $this->stmt->rowCount();
    }
}

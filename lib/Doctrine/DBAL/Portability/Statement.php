<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use IteratorAggregate;
use function array_change_key_case;
use function assert;
use function is_string;
use function rtrim;

/**
 * Portability wrapper for a Statement.
 */
final class Statement implements IteratorAggregate, DriverStatement
{
    /** @var int */
    private $portability;

    /** @var DriverStatement|ResultStatement */
    private $stmt;

    /** @var int|null */
    private $case;

    /** @var int */
    private $defaultFetchMode = FetchMode::MIXED;

    /**
     * Wraps <tt>Statement</tt> and applies portability measures.
     *
     * @param DriverStatement|ResultStatement $stmt
     */
    public function __construct($stmt, Connection $conn)
    {
        $this->stmt        = $stmt;
        $this->portability = $conn->getPortability();
        $this->case        = $conn->getFetchCase();
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null) : void
    {
        assert($this->stmt instanceof DriverStatement);

        $this->stmt->bindParam($param, $variable, $type, $length);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING) : void
    {
        assert($this->stmt instanceof DriverStatement);

        $this->stmt->bindValue($param, $value, $type);
    }

    public function closeCursor() : void
    {
        $this->stmt->closeCursor();
    }

    public function columnCount() : int
    {
        return $this->stmt->columnCount();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null) : void
    {
        assert($this->stmt instanceof DriverStatement);

        $this->stmt->execute($params);
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode(int $fetchMode, ...$args) : void
    {
        $this->defaultFetchMode = $fetchMode;

        $this->stmt->setFetchMode($fetchMode, ...$args);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new StatementIterator($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(?int $fetchMode = null, ...$args)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        $row = $this->stmt->fetch($fetchMode, ...$args);

        $iterateRow = ($this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM)) !== 0;
        $fixCase    = $this->case !== null
            && ($fetchMode === FetchMode::ASSOCIATIVE || $fetchMode === FetchMode::MIXED)
            && ($this->portability & Connection::PORTABILITY_FIX_CASE);

        $row = $this->fixRow($row, $iterateRow, $fixCase);

        return $row;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(?int $fetchMode = null, ...$args) : array
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        $rows = $this->stmt->fetchAll($fetchMode, ...$args);

        $iterateRow = ($this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM)) !== 0;
        $fixCase    = $this->case !== null
            && ($fetchMode === FetchMode::ASSOCIATIVE || $fetchMode === FetchMode::MIXED)
            && ($this->portability & Connection::PORTABILITY_FIX_CASE);

        if (! $iterateRow && ! $fixCase) {
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
     * {@inheritdoc}
     */
    public function fetchColumn(int $columnIndex = 0)
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

    public function rowCount() : int
    {
        assert($this->stmt instanceof DriverStatement);

        return $this->stmt->rowCount();
    }

    /**
     * @param mixed $row
     *
     * @return mixed
     */
    private function fixRow($row, bool $iterateRow, bool $fixCase)
    {
        if (! $row) {
            return $row;
        }

        if ($fixCase) {
            assert($this->case !== null);
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
}

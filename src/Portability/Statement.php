<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use function array_change_key_case;
use function assert;
use function is_string;
use function rtrim;

/**
 * Portability wrapper for a Statement.
 */
final class Statement implements DriverStatement
{
    /** @var int */
    private $portability;

    /** @var DriverStatement|ResultStatement */
    private $stmt;

    /** @var int|null */
    private $case;

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
    public function fetchNumeric()
    {
        return $this->fixResult(
            $this->stmt->fetchAssociative(),
            false
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssociative()
    {
        return $this->fixResult(
            $this->stmt->fetchAssociative(),
            true
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne()
    {
        $value = $this->stmt->fetchOne();

        if (($this->portability & Connection::PORTABILITY_EMPTY_TO_NULL) !== 0 && $value === '') {
            $value = null;
        } elseif (($this->portability & Connection::PORTABILITY_RTRIM) !== 0 && is_string($value)) {
            $value = rtrim($value);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllNumeric() : array
    {
        return $this->fixResultSet(
            $this->stmt->fetchAllNumeric(),
            false,
            true
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative() : array
    {
        return $this->fixResultSet(
            $this->stmt->fetchAllAssociative(),
            true,
            true
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn() : array
    {
        return $this->fixResultSet(
            $this->stmt->fetchColumn(),
            true,
            false
        );
    }

    /**
     * @param mixed $result
     *
     * @return mixed
     */
    private function fixResult($result, bool $fixCase)
    {
        $iterateRow = ($this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM)) !== 0;
        $fixCase    = $fixCase && $this->case !== null && ($this->portability & Connection::PORTABILITY_FIX_CASE) !== 0;

        return $this->fixRow($result, $iterateRow, $fixCase);
    }

    /**
     * @param array<int,mixed> $resultSet
     *
     * @return array<int,mixed>
     */
    private function fixResultSet(array $resultSet, bool $fixCase, bool $isArray) : array
    {
        $iterateRow = ($this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM)) !== 0;
        $fixCase    = $fixCase && $this->case !== null && ($this->portability & Connection::PORTABILITY_FIX_CASE) !== 0;

        if (! $iterateRow && ! $fixCase) {
            return $resultSet;
        }

        if (! $isArray) {
            foreach ($resultSet as $num => $value) {
                $resultSet[$num] = [$value];
            }
        }

        foreach ($resultSet as $num => $row) {
            $resultSet[$num] = $this->fixRow($row, $iterateRow, $fixCase);
        }

        if (! $isArray) {
            foreach ($resultSet as $num => $row) {
                $resultSet[$num] = $row[0];
            }
        }

        return $resultSet;
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
        if ($row === false) {
            return $row;
        }

        if ($fixCase) {
            assert($this->case !== null);
            $row = array_change_key_case($row, $this->case);
        }

        if ($iterateRow) {
            foreach ($row as $k => $v) {
                if (($this->portability & Connection::PORTABILITY_EMPTY_TO_NULL) !== 0 && $v === '') {
                    $row[$k] = null;
                } elseif (($this->portability & Connection::PORTABILITY_RTRIM) !== 0 && is_string($v)) {
                    $row[$k] = rtrim($v);
                }
            }
        }

        return $row;
    }
}

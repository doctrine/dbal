<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver\Exception\UnknownFetchMode;
use Doctrine\DBAL\Driver\Exception\UnknownParamType;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use IteratorAggregate;
use PDO;
use function array_slice;
use function assert;
use function count;
use function func_get_args;
use function is_array;

/**
 * The PDO implementation of the Statement interface.
 * Used by all PDO-based drivers.
 */
class PDOStatement implements IteratorAggregate, Statement
{
    private const PARAM_TYPE_MAP = [
        ParameterType::NULL         => PDO::PARAM_NULL,
        ParameterType::INTEGER      => PDO::PARAM_INT,
        ParameterType::STRING       => PDO::PARAM_STR,
        ParameterType::BINARY       => PDO::PARAM_LOB,
        ParameterType::LARGE_OBJECT => PDO::PARAM_LOB,
        ParameterType::BOOLEAN      => PDO::PARAM_BOOL,
    ];

    private const FETCH_MODE_MAP = [
        FetchMode::ASSOCIATIVE => PDO::FETCH_ASSOC,
        FetchMode::NUMERIC     => PDO::FETCH_NUM,
        FetchMode::MIXED       => PDO::FETCH_BOTH,
        FetchMode::COLUMN      => PDO::FETCH_COLUMN,
    ];

    /** @var \PDOStatement */
    private $stmt;

    public function __construct(\PDOStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    public function setFetchMode(int $fetchMode) : void
    {
        $fetchMode = $this->convertFetchMode($fetchMode);

        try {
            $this->stmt->setFetchMode($fetchMode);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING) : void
    {
        $type = $this->convertParamType($type);

        try {
            $this->stmt->bindValue($param, $value, $type);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * @param mixed $param
     * @param mixed $variable
     * @param mixed $driverOptions
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null, $driverOptions = null) : void
    {
        $type            = $this->convertParamType($type);
        $extraParameters = array_slice(func_get_args(), 3);

        if (count($extraParameters) !== 0) {
            $extraParameters[0] = $extraParameters[0] ?? 0;
        }

        try {
            $this->stmt->bindParam($param, $variable, $type, ...$extraParameters);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
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
        try {
            $this->stmt->execute($params);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    public function rowCount() : int
    {
        return $this->stmt->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(?int $fetchMode = null)
    {
        try {
            if ($fetchMode === null) {
                return $this->stmt->fetch();
            }

            return $this->stmt->fetch(
                $this->convertFetchMode($fetchMode)
            );
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(?int $fetchMode = null) : array
    {
        try {
            if ($fetchMode === null) {
                $data = $this->stmt->fetchAll();
            } else {
                $data = $this->stmt->fetchAll(
                    $this->convertFetchMode($fetchMode)
                );
            }
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }

        assert(is_array($data));

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn()
    {
        try {
            return $this->stmt->fetchColumn();
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * Converts DBAL parameter type to PDO parameter type
     *
     * @param int $type Parameter type
     */
    private function convertParamType(int $type) : int
    {
        if (! isset(self::PARAM_TYPE_MAP[$type])) {
            throw UnknownParamType::new($type);
        }

        return self::PARAM_TYPE_MAP[$type];
    }

    /**
     * Converts DBAL fetch mode to PDO fetch mode
     *
     * @param int $fetchMode Fetch mode
     */
    private function convertFetchMode(int $fetchMode) : int
    {
        if (! isset(self::FETCH_MODE_MAP[$fetchMode])) {
            throw UnknownFetchMode::new($fetchMode);
        }

        return self::FETCH_MODE_MAP[$fetchMode];
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        yield from $this->stmt;
    }
}

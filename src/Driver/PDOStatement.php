<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver\Exception\UnknownParamType;
use Doctrine\DBAL\ParameterType;
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
class PDOStatement implements Statement
{
    private const PARAM_TYPE_MAP = [
        ParameterType::NULL         => PDO::PARAM_NULL,
        ParameterType::INTEGER      => PDO::PARAM_INT,
        ParameterType::STRING       => PDO::PARAM_STR,
        ParameterType::BINARY       => PDO::PARAM_LOB,
        ParameterType::LARGE_OBJECT => PDO::PARAM_LOB,
        ParameterType::BOOLEAN      => PDO::PARAM_BOOL,
    ];

    /** @var \PDOStatement */
    private $stmt;

    public function __construct(\PDOStatement $stmt)
    {
        $this->stmt = $stmt;
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
    public function fetchNumeric()
    {
        return $this->fetch(PDO::FETCH_NUM);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssociative()
    {
        return $this->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne()
    {
        return $this->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllNumeric() : array
    {
        return $this->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative() : array
    {
        return $this->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn() : array
    {
        return $this->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return mixed|false
     *
     * @throws PDOException
     */
    private function fetch(int $mode)
    {
        try {
            return $this->stmt->fetch($mode);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * @return array<int,mixed>
     *
     * @throws PDOException
     */
    private function fetchAll(int $mode) : array
    {
        try {
            $data = $this->stmt->fetchAll($mode);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }

        assert(is_array($data));

        return $data;
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
}

<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use PDO;

use function array_slice;
use function assert;
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
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        $type = $this->convertParamType($type);

        try {
            return $this->stmt->bindValue($param, $value, $type);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * @param mixed    $column
     * @param mixed    $variable
     * @param int      $type
     * @param int|null $length
     * @param mixed    $driverOptions
     *
     * @return bool
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null, $driverOptions = null)
    {
        $type = $this->convertParamType($type);

        try {
            return $this->stmt->bindParam($column, $variable, $type, ...array_slice(func_get_args(), 3));
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        try {
            return $this->stmt->closeCursor();
        } catch (\PDOException $exception) {
            // Exceptions not allowed by the interface.
            // In case driver implementations do not adhere to the interface, silence exceptions here.
            return true;
        }
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
        try {
            return $this->stmt->execute($params);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    public function rowCount(): int
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
    public function fetchAllNumeric(): array
    {
        return $this->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative(): array
    {
        return $this->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchFirstColumn(): array
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
    private function fetchAll(int $mode): array
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
    private function convertParamType(int $type): int
    {
        if (! isset(self::PARAM_TYPE_MAP[$type])) {
            throw new InvalidArgumentException('Invalid parameter type: ' . $type);
        }

        return self::PARAM_TYPE_MAP[$type];
    }
}

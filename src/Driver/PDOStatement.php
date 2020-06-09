<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver\Exception\UnknownParamType;
use Doctrine\DBAL\Driver\PDO\Result;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\ParameterType;
use PDO;

use function array_slice;
use function count;
use function func_get_args;

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
    public function bindValue($param, $value, int $type = ParameterType::STRING): void
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
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null, $driverOptions = null): void
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

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null): ResultInterface
    {
        try {
            $this->stmt->execute($params);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }

        return new Result($this->stmt);
    }

    /**
     * Converts DBAL parameter type to PDO parameter type
     *
     * @param int $type Parameter type
     */
    private function convertParamType(int $type): int
    {
        if (! isset(self::PARAM_TYPE_MAP[$type])) {
            throw UnknownParamType::new($type);
        }

        return self::PARAM_TYPE_MAP[$type];
    }
}

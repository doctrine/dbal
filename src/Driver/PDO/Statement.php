<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\Exception as ExceptionInterface;
use Doctrine\DBAL\Driver\Exception\UnknownParameterType;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use PDO;
use PDOException;
use PDOStatement;

final class Statement implements StatementInterface
{
    private const PARAM_TYPE_MAP = [
        ParameterType::NULL         => PDO::PARAM_NULL,
        ParameterType::INTEGER      => PDO::PARAM_INT,
        ParameterType::STRING       => PDO::PARAM_STR,
        ParameterType::ASCII        => PDO::PARAM_STR,
        ParameterType::BINARY       => PDO::PARAM_LOB,
        ParameterType::LARGE_OBJECT => PDO::PARAM_LOB,
        ParameterType::BOOLEAN      => PDO::PARAM_BOOL,
    ];

    private PDOStatement $stmt;

    /**
     * @internal The statement can be only instantiated by its driver connection.
     */
    public function __construct(PDOStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    public function bindValue(int|string $param, mixed $value, int $type = ParameterType::STRING): void
    {
        $type = $this->convertParamType($type);

        try {
            $this->stmt->bindValue($param, $value, $type);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function bindParam(
        string|int $param,
        mixed &$variable,
        int $type = ParameterType::STRING,
        ?int $length = null
    ): void {
        try {
            if ($length === null) {
                $this->stmt->bindParam($param, $variable, $this->convertParamType($type));
            } else {
                $this->stmt->bindParam($param, $variable, $this->convertParamType($type), $length);
            }
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * @internal Driver options can be only specified by a PDO-based driver.
     *
     * @throws ExceptionInterface
     */
    public function bindParamWithDriverOptions(
        string|int $param,
        mixed &$variable,
        int $type,
        int $length,
        mixed $driverOptions
    ): void {
        try {
            $this->stmt->bindParam($param, $variable, $this->convertParamType($type), $length, $driverOptions);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function execute(?array $params = null): Result
    {
        try {
            $this->stmt->execute($params);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        return new Result($this->stmt);
    }

    /**
     * Converts DBAL parameter type to PDO parameter type
     *
     * @param int $type Parameter type
     *
     * @throws ExceptionInterface
     */
    private function convertParamType(int $type): int
    {
        if (! isset(self::PARAM_TYPE_MAP[$type])) {
            throw UnknownParameterType::new($type);
        }

        return self::PARAM_TYPE_MAP[$type];
    }
}

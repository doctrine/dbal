<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\Exception as ExceptionInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use PDO;
use PDOException;
use PDOStatement;

final class Statement implements StatementInterface
{
    /** @internal The statement can be only instantiated by its driver connection. */
    public function __construct(private readonly PDOStatement $stmt)
    {
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $pdoType = $this->convertParamType($type);

        try {
            $this->stmt->bindValue($param, $value, $pdoType);
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
        ParameterType $type,
        mixed $driverOptions,
    ): void {
        $pdoType = $this->convertParamType($type);

        try {
            $this->stmt->bindParam($param, $variable, $pdoType, 0, $driverOptions);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function execute(): Result
    {
        try {
            $this->stmt->execute();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        return new Result($this->stmt);
    }

    /**
     * Converts DBAL parameter type to PDO parameter type
     *
     * @phpstan-return PDO::PARAM_*
     */
    private function convertParamType(ParameterType $type): int
    {
        return match ($type) {
            ParameterType::NULL => PDO::PARAM_NULL,
            ParameterType::INTEGER => PDO::PARAM_INT,
            ParameterType::STRING,
            ParameterType::ASCII => PDO::PARAM_STR,
            ParameterType::BINARY,
            ParameterType::LARGE_OBJECT => PDO::PARAM_LOB,
            ParameterType::BOOLEAN => PDO::PARAM_BOOL,
        };
    }
}

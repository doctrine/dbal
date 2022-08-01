<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\Exception as ExceptionInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use PDO;
use PDOException;
use PDOStatement;

use function func_num_args;

final class Statement implements StatementInterface
{
    /**
     * @internal The statement can be only instantiated by its driver connection.
     */
    public function __construct(private readonly PDOStatement $stmt)
    {
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type = ParameterType::STRING): void
    {
        if (func_num_args() < 3) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5558',
                'Not passing $type to Statement::bindValue() is deprecated.'
                    . ' Pass the type corresponding to the parameter being bound.'
            );
        }

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
        ParameterType $type = ParameterType::STRING,
        ?int $length = null
    ): void {
        if (func_num_args() < 3) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5558',
                'Not passing $type to Statement::bindParam() is deprecated.'
                . ' Pass the type corresponding to the parameter being bound.'
            );
        }

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
        ParameterType $type,
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
        if ($params !== null) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5556',
                'Passing $params to Statement::execute() is deprecated. Bind parameters using'
                    . ' Statement::bindParam() or Statement::bindValue() instead.'
            );
        }

        try {
            $this->stmt->execute($params);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        return new Result($this->stmt);
    }

    /**
     * Converts DBAL parameter type to PDO parameter type
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

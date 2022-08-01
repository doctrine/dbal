<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO\SQLSrv;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\PDO\Statement as PDOStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use PDO;

use function func_num_args;

final class Statement extends AbstractStatementMiddleware
{
    private readonly PDOStatement $statement;

    /**
     * @internal The statement can be only instantiated by its driver connection.
     */
    public function __construct(PDOStatement $statement)
    {
        parent::__construct($statement);

        $this->statement = $statement;
    }

    public function bindParam(
        int|string $param,
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

        switch ($type) {
            case ParameterType::LARGE_OBJECT:
            case ParameterType::BINARY:
                $this->statement->bindParamWithDriverOptions(
                    $param,
                    $variable,
                    $type,
                    $length ?? 0,
                    PDO::SQLSRV_ENCODING_BINARY
                );
                break;

            case ParameterType::ASCII:
                $this->statement->bindParamWithDriverOptions(
                    $param,
                    $variable,
                    ParameterType::STRING,
                    $length ?? 0,
                    PDO::SQLSRV_ENCODING_SYSTEM
                );
                break;

            default:
                $this->statement->bindParam($param, $variable, $type, $length ?? 0);
        }
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

        $this->bindParam($param, $value, $type);
    }
}

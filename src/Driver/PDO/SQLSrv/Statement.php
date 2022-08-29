<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO\SQLSrv;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\PDO\Statement as PDOStatement;
use Doctrine\DBAL\ParameterType;
use PDO;

final class Statement extends AbstractStatementMiddleware
{
    /** @internal The statement can be only instantiated by its driver connection. */
    public function __construct(private readonly PDOStatement $statement)
    {
        parent::__construct($statement);
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        switch ($type) {
            case ParameterType::LARGE_OBJECT:
            case ParameterType::BINARY:
                $this->statement->bindParamWithDriverOptions(
                    $param,
                    $value,
                    $type,
                    PDO::SQLSRV_ENCODING_BINARY,
                );
                break;

            case ParameterType::ASCII:
                $this->statement->bindParamWithDriverOptions(
                    $param,
                    $value,
                    ParameterType::STRING,
                    PDO::SQLSRV_ENCODING_SYSTEM,
                );
                break;

            default:
                $this->statement->bindValue($param, $value, $type);
        }
    }
}

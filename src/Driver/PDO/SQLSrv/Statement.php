<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO\SQLSrv;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\PDO\Statement as PDOStatement;
use Doctrine\DBAL\ParameterType;
use PDO;

final class Statement extends AbstractStatementMiddleware
{
    private PDOStatement $statement;

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
        int $type = ParameterType::STRING,
        ?int $length = null
    ): void {
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

    public function bindValue(int|string $param, mixed $value, int $type = ParameterType::STRING): void
    {
        $this->bindParam($param, $value, $type);
    }
}

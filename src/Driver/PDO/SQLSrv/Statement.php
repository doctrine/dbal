<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO\SQLSrv;

use Doctrine\DBAL\Driver\PDO\Statement as PDOStatement;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use PDO;

final class Statement implements StatementInterface
{
    /** @var PDOStatement */
    private $statement;

    /**
     * @internal The statement can be only instantiated by its driver connection.
     */
    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $driverOptions
     */
    public function bindParam(
        $param,
        &$variable,
        int $type = ParameterType::STRING,
        ?int $length = null,
        $driverOptions = null
    ): void {
        if (
            ($type === ParameterType::LARGE_OBJECT || $type === ParameterType::BINARY)
            && $driverOptions === null
        ) {
            $driverOptions = PDO::SQLSRV_ENCODING_BINARY;
        }

        $this->statement->bindParam($param, $variable, $type, $length, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING): void
    {
        $this->bindParam($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null): Result
    {
        return $this->statement->execute($params);
    }
}

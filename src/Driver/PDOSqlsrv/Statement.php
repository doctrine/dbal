<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDO\Statement as PDOStatement;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use PDO;

/**
 * PDO SQL Server Statement
 */
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
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null, $driverOptions = null)
    {
        if (
            ($type === ParameterType::LARGE_OBJECT || $type === ParameterType::BINARY)
            && $driverOptions === null
        ) {
            $driverOptions = PDO::SQLSRV_ENCODING_BINARY;
        }

        return $this->statement->bindParam($column, $variable, $type, $length, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        return $this->bindParam($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null): Result
    {
        return $this->statement->execute($params);
    }
}

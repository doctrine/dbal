<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDOStatement;
use Doctrine\DBAL\ParameterType;
use PDO;

/**
 * PDO SQL Server Statement
 */
final class Statement extends PDOStatement
{
    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null, $driverOptions = null): void
    {
        if (
            ($type === ParameterType::LARGE_OBJECT || $type === ParameterType::BINARY)
            && $driverOptions === null
        ) {
            $driverOptions = PDO::SQLSRV_ENCODING_BINARY;
        }

        parent::bindParam($param, $variable, $type, $length, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING): void
    {
        $this->bindParam($param, $value, $type);
    }
}

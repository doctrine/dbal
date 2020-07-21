<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\ParameterType;

/**
 * PDO SQL Server Statement
 *
 * @deprecated Use {@link PDO\SQLSrv\Statement} instead.
 */
class Statement extends PDO\Statement
{
    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null, $driverOptions = null)
    {
        if (
            ($type === ParameterType::LARGE_OBJECT || $type === ParameterType::BINARY)
            && $driverOptions === null
        ) {
            $driverOptions = \PDO::SQLSRV_ENCODING_BINARY;
        }

        return parent::bindParam($param, $variable, $type, $length, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        return $this->bindParam($param, $value, $type);
    }
}

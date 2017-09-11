<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDOStatement;
use PDO;

/**
 * PDO SQL Server Statement
 */
class Statement extends PDOStatement
{
    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = PDO::PARAM_STR, $length = null, $driverOptions = null)
    {
        if ($type === PDO::PARAM_LOB && $driverOptions === null) {
            $driverOptions = PDO::SQLSRV_ENCODING_BINARY;
        }

        return parent::bindParam($column, $variable, $type, $length, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = PDO::PARAM_STR)
    {
        return $this->bindParam($param, $value, $type);
    }
}

<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDO\Connection as BaseConnection;
use Doctrine\DBAL\Driver\PDO\Statement as BaseStatement;
use Doctrine\DBAL\ParameterType;
use PDOStatement;

use function is_string;
use function strpos;
use function substr;

/**
 * Sqlsrv Connection implementation.
 */
class Connection extends BaseConnection
{
    /**
     * {@inheritDoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name === null) {
            return parent::lastInsertId($name);
        }

        return $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?')
            ->execute([$name])
            ->fetchOne();
    }

    /**
     * {@inheritDoc}
     */
    public function quote($value, $type = ParameterType::STRING)
    {
        $val = parent::quote($value, $type);

        // Fix for a driver version terminating all values with null byte
        if (is_string($val) && strpos($val, "\0") !== false) {
            $val = substr($val, 0, -1);
        }

        return $val;
    }

    protected function createStatement(PDOStatement $stmt): BaseStatement
    {
        return new Statement($stmt);
    }
}

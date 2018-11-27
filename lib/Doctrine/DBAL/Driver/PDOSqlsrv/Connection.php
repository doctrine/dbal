<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\AbstractDriverException;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\PDOStatement;
use function strpos;
use function substr;

/**
 * Sqlsrv Connection implementation.
 */
class Connection extends PDOConnection implements \Doctrine\DBAL\Driver\Connection
{
    /**
     * {@inheritDoc}
     */
    public function lastInsertId(?string $name = null) : string
    {
        if ($name === null) {
            return parent::lastInsertId($name);
        }

        $stmt = $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?');
        $stmt->execute([$name]);

        $result = $stmt->fetchColumn();

        if ($result === false) {
            throw AbstractDriverException::noSuchSequence($name);
        }

        return (string) $result;
    }

    /**
     * {@inheritDoc}
     */
    public function quote(string $value) : string
    {
        $val = parent::quote($value);

        // Fix for a driver version terminating all values with null byte
        if (strpos($val, "\0") !== false) {
            $val = substr($val, 0, -1);
        }

        return $val;
    }

    /**
     * {@inheritDoc}
     */
    protected function createStatement(\PDOStatement $stmt) : PDOStatement
    {
        return new Statement($stmt);
    }
}

<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\ParameterType;

/**
 * Sqlsrv Connection implementation.
 *
 * @since 2.0
 */
class Connection extends PDOConnection implements \Doctrine\DBAL\Driver\Connection
{
    /**
     * {@inheritdoc}
     */
    public function __construct($dsn, $user = null, $password = null, array $options = null)
    {
        parent::__construct($dsn, $user, $password, $options);
        $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [Statement::class, []]);
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId($name = null)
    {
        if (null === $name) {
            return parent::lastInsertId($name);
        }

        $stmt = $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?');
        $stmt->execute([$name]);

        return $stmt->fetchColumn();
    }

    /**
     * {@inheritDoc}
     */
    public function quote($value, $type = ParameterType::STRING)
    {
        $val = parent::quote($value, $type);

        // Fix for a driver version terminating all values with null byte
        if (strpos($val, "\0") !== false) {
            $val = substr($val, 0, -1);
        }

        return $val;
    }
}

<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDOConnection;
use PDO;

/**
 * Sqlsrv Connection implementation.
 */
class Connection extends PDOConnection
{
    /**
     * {@inheritdoc}
     */
    public function __construct($dsn, $user = null, $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $user, $password, $options);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Statement::class, []]);
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name === null) {
            return parent::lastInsertId($name);
        }

        $stmt = $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?');
        $stmt->execute([$name]);

        return $stmt->fetchColumn();
    }
}

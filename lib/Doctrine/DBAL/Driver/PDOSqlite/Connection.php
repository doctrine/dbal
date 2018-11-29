<?php

namespace Doctrine\DBAL\Driver\PDOSqlite;

use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\PDOConnection;

/**
 * SQLite Connection implementation.
 */
class Connection extends PDOConnection
{
    /**
     * {@inheritdoc}
     */
    public function lastInsertId(?string $name = null) : string
    {
        if ($name !== null) {
            // SQLite does not support sequences. However, PDO::lastInsertId() ignores the name parameter, and returns
            // the last insert ID even if a sequence name is given. We expect an exception in that case.
            throw new DriverException('SQLite does not support sequences.');
        }

        return parent::lastInsertId();
    }
}

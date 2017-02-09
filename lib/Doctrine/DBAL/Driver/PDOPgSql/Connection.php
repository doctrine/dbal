<?php

namespace Doctrine\DBAL\Driver\PDOPgSql;

use Doctrine\DBAL\Driver\PDOConnection;

/**
 * pdo_pgsql connection implementation.
 */
class Connection extends PDOConnection implements \Doctrine\DBAL\Driver\Connection
{
    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        try {
            return $this->fetchLastInsertId($name);
        } catch (\PDOException $exception) {
            return '0';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function trackLastInsertId()
    {
        // Do not track last insert ID as it is not considered "safe" and can cause transactions to fail.
        // If there is no last insert ID yet, we get an error with SQLSTATE 55000:
        // "Object not in prerequisite state: 7 ERROR:  lastval is not yet defined in this session"
        // That error can modify the transaction/connection state.
    }
}

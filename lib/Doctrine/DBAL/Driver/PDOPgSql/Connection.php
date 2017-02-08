<?php

namespace Doctrine\DBAL\Driver\PDOPgSql;

use Doctrine\DBAL\Driver\PDOConnection;

/**
 * pdo_pgsql connection implementation.
 */
class Connection extends PDOConnection implements \Doctrine\DBAL\Driver\Connection
{
    /**
     * @var string
     */
    private $lastInsertId = '0';

    /**
     * @var bool
     */
    private $lastInsertIdCallNested = false;

    /**
     * {@inheritDoc}
     */
    /*
    public function lastInsertId($name = null)
    {
        if ($this->lastInsertIdCallNested) {
            return $this->lastInsertId;
        }

        if (null !== $name) {
            return parent::lastInsertId($name);
        }

        // The driver behaves inconsistently between different PHP versions.
        // Starting with 7.0.16 and 7.1.2, the driver throws an exception,
        // if no last insert ID is available in the current session yet.
        // In prior versions an "undefined" value like "4294967295" is returned.
        // Therefore we try to make the behaviour consistent across PHP versions here.

        // First we need to avoid unnecessary exception generation, by temporarily disabling exception mode.
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $this->lastInsertIdCallNested = true; // Avoid infinite loop.
        $stmt = $this->query('SELECT LASTVAL()');
        $this->lastInsertIdCallNested = false;

        // Reactivate exception mode.
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if (! $stmt) {
            // If there is no last insert ID yet, we get an error with SQLSTATE 55000 here:
            // "Object not in prerequisite state: 7 ERROR:  lastval is not yet defined in this session"
            // In case of any other error than that, we still need to return the last tracked insert ID,
            // as we do not have a new one.
            return $this->lastInsertId;
        }

        $lastInsertId = (string) $stmt->fetchColumn();

        // The last insert ID is reset to "0" in certain situations like after dropping the table
        // that held the last insert ID.
        // Therefore we keep the previously set insert ID locally.
        if ('0' !== $lastInsertId) {
            $this->lastInsertId = $lastInsertId;
        }

        return $this->lastInsertId;
    }
    */
}

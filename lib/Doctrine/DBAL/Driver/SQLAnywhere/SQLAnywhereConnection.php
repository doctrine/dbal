<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use function assert;
use function is_resource;
use function is_string;
use function sasql_affected_rows;
use function sasql_commit;
use function sasql_connect;
use function sasql_escape_string;
use function sasql_insert_id;
use function sasql_pconnect;
use function sasql_real_query;
use function sasql_rollback;
use function sasql_set_option;

/**
 * SAP Sybase SQL Anywhere implementation of the Connection interface.
 */
class SQLAnywhereConnection implements Connection, ServerInfoAwareConnection
{
    /** @var resource The SQL Anywhere connection resource. */
    private $connection;

    /**
     * Connects to database with given connection string.
     *
     * @param string $dsn        The connection string.
     * @param bool   $persistent Whether or not to establish a persistent connection.
     *
     * @throws SQLAnywhereException
     */
    public function __construct(string $dsn, bool $persistent = false)
    {
        $this->connection = $persistent ? @sasql_pconnect($dsn) : @sasql_connect($dsn);

        if (! is_resource($this->connection)) {
            throw SQLAnywhereException::fromSQLAnywhereError();
        }

        // Disable PHP warnings on error.
        if (! sasql_set_option($this->connection, 'verbose_errors', false)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        // Enable auto committing by default.
        if (! sasql_set_option($this->connection, 'auto_commit', 'on')) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLAnywhereException
     */
    public function beginTransaction() : void
    {
        if (! sasql_set_option($this->connection, 'auto_commit', 'off')) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLAnywhereException
     */
    public function commit() : void
    {
        if (! sasql_commit($this->connection)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        $this->endTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function exec(string $statement) : int
    {
        if (sasql_real_query($this->connection, $statement) === false) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        return sasql_affected_rows($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion() : string
    {
        $version = $this->query("SELECT PROPERTY('ProductVersion')")->fetchColumn();

        assert(is_string($version));

        return $version;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(?string $name = null) : string
    {
        if ($name === null) {
            return sasql_insert_id($this->connection);
        }

        return $this->query('SELECT ' . $name . '.CURRVAL')->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $sql) : DriverStatement
    {
        return new SQLAnywhereStatement($this->connection, $sql);
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql) : ResultStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function quote(string $input) : string
    {
        return "'" . sasql_escape_string($this->connection, $input) . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion() : bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLAnywhereException
     */
    public function rollBack() : void
    {
        if (! sasql_rollback($this->connection)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        $this->endTransaction();
    }

    /**
     * Ends transactional mode and enables auto commit again.
     *
     * @throws SQLAnywhereException
     */
    private function endTransaction() : void
    {
        if (! sasql_set_option($this->connection, 'auto_commit', 'on')) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }
    }
}

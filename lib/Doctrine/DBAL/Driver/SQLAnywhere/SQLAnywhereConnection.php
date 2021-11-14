<?php

namespace Doctrine\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;

use function assert;
use function func_get_args;
use function is_float;
use function is_int;
use function is_resource;
use function is_string;
use function sasql_affected_rows;
use function sasql_commit;
use function sasql_connect;
use function sasql_error;
use function sasql_errorcode;
use function sasql_escape_string;
use function sasql_insert_id;
use function sasql_pconnect;
use function sasql_real_query;
use function sasql_rollback;
use function sasql_set_option;

/**
 * SAP Sybase SQL Anywhere implementation of the Connection interface.
 *
 * @deprecated Support for SQLAnywhere will be removed in 3.0.
 */
class SQLAnywhereConnection implements Connection, ServerInfoAwareConnection
{
    /** @var resource The SQL Anywhere connection resource. */
    private $connection;

    /**
     * Connects to database with given connection string.
     *
     * @internal The connection can be only instantiated by its driver.
     *
     * @param string $dsn        The connection string.
     * @param bool   $persistent Whether or not to establish a persistent connection.
     *
     * @throws SQLAnywhereException
     */
    public function __construct($dsn, $persistent = false)
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4077',
            'The SQLAnywhere driver is deprecated'
        );

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
    public function beginTransaction()
    {
        if (! sasql_set_option($this->connection, 'auto_commit', 'off')) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLAnywhereException
     */
    public function commit()
    {
        if (! sasql_commit($this->connection)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        $this->endTransaction();

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated The error information is available via exceptions.
     */
    public function errorCode()
    {
        return sasql_errorcode($this->connection);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated The error information is available via exceptions.
     */
    public function errorInfo()
    {
        return sasql_error($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function exec($sql)
    {
        if (sasql_real_query($this->connection, $sql) === false) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        return sasql_affected_rows($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        $stmt = $this->query("SELECT PROPERTY('ProductVersion')");

        if ($stmt instanceof Result) {
            $version = $stmt->fetchOne();
        } else {
            $version = $stmt->fetchColumn();
        }

        assert(is_string($version));

        return $version;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name === null) {
            return sasql_insert_id($this->connection);
        }

        $stmt = $this->query('SELECT ' . $name . '.CURRVAL');

        if ($stmt instanceof Result) {
            return $stmt->fetchOne();
        }

        return $stmt->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($sql)
    {
        return new SQLAnywhereStatement($this->connection, $sql);
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        $args = func_get_args();
        $stmt = $this->prepare($args[0]);

        $stmt->execute();

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function quote($value, $type = ParameterType::STRING)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return "'" . sasql_escape_string($this->connection, $value) . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4114',
            'ServerInfoAwareConnection::requiresQueryForServerVersion() is deprecated and removed in DBAL 3.'
        );

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLAnywhereException
     */
    public function rollBack()
    {
        if (! sasql_rollback($this->connection)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        $this->endTransaction();

        return true;
    }

    /**
     * Ends transactional mode and enables auto commit again.
     *
     * @return bool Whether or not ending transactional mode succeeded.
     *
     * @throws SQLAnywhereException
     */
    private function endTransaction()
    {
        if (! sasql_set_option($this->connection, 'auto_commit', 'on')) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        return true;
    }
}

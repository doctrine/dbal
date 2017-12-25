<?php

namespace Doctrine\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\ParameterType;

/**
 * SAP Sybase SQL Anywhere implementation of the Connection interface.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class SQLAnywhereConnection implements Connection, ServerInfoAwareConnection
{
    /**
     * @var resource The SQL Anywhere connection resource.
     */
    private $connection;

    /**
     * Constructor.
     *
     * Connects to database with given connection string.
     *
     * @param string  $dsn        The connection string.
     * @param boolean $persistent Whether or not to establish a persistent connection.
     *
     * @throws SQLAnywhereException
     */
    public function __construct($dsn, $persistent = false)
    {
        $this->connection = $persistent ? @sasql_pconnect($dsn) : @sasql_connect($dsn);

        if ( ! is_resource($this->connection)) {
            throw SQLAnywhereException::fromSQLAnywhereError();
        }

        // Disable PHP warnings on error.
        if ( ! sasql_set_option($this->connection, 'verbose_errors', false)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        // Enable auto committing by default.
        if ( ! sasql_set_option($this->connection, 'auto_commit', 'on')) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        // Enable exact, non-approximated row count retrieval.
        if ( ! sasql_set_option($this->connection, 'row_counts', true)) {
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
        if ( ! sasql_set_option($this->connection, 'auto_commit', 'off')) {
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
        if ( ! sasql_commit($this->connection)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        $this->endTransaction();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return sasql_errorcode($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return sasql_error($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
    {
        if (false === sasql_real_query($this->connection, $statement)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        return sasql_affected_rows($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        return $this->query("SELECT PROPERTY('ProductVersion')")->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        if (null === $name) {
            return sasql_insert_id($this->connection);
        }

        return $this->query('SELECT ' . $name . '.CURRVAL')->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($prepareString)
    {
        return new SQLAnywhereStatement($this->connection, $prepareString);
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
    public function quote($input, $type = ParameterType::STRING)
    {
        if (is_int($input) || is_float($input)) {
            return $input;
        }

        return "'" . sasql_escape_string($this->connection, $input) . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLAnywhereException
     */
    public function rollBack()
    {
        if ( ! sasql_rollback($this->connection)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        $this->endTransaction();

        return true;
    }

    /**
     * Ends transactional mode and enables auto commit again.
     *
     * @throws SQLAnywhereException
     *
     * @return boolean Whether or not ending transactional mode succeeded.
     */
    private function endTransaction()
    {
        if ( ! sasql_set_option($this->connection, 'auto_commit', 'on')) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->connection);
        }

        return true;
    }
}

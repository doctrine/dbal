<?php

namespace Doctrine\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use InvalidArgumentException;
use function assert;
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
     * @throws DriverException
     */
    public function __construct($dsn, $persistent = false)
    {
        $this->connection = $persistent ? @sasql_pconnect($dsn) : @sasql_connect($dsn);

        if (! is_resource($this->connection)) {
            throw self::exceptionFromSQLAnywhereError();
        }

        // Disable PHP warnings on error.
        if (! sasql_set_option($this->connection, 'verbose_errors', false)) {
            throw self::exceptionFromSQLAnywhereError($this->connection);
        }

        // Enable auto committing by default.
        if (! sasql_set_option($this->connection, 'auto_commit', 'on')) {
            throw self::exceptionFromSQLAnywhereError($this->connection);
        }

        // Enable exact, non-approximated row count retrieval.
        if (! sasql_set_option($this->connection, 'row_counts', true)) {
            throw self::exceptionFromSQLAnywhereError($this->connection);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction() : void
    {
        if (! sasql_set_option($this->connection, 'auto_commit', 'off')) {
            throw self::exceptionFromSQLAnywhereError($this->connection);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function commit() : void
    {
        if (! sasql_commit($this->connection)) {
            throw self::exceptionFromSQLAnywhereError($this->connection);
        }

        $this->endTransaction();
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
    public function exec(string $statement) : int
    {
        if (sasql_real_query($this->connection, $statement) === false) {
            throw self::exceptionFromSQLAnywhereError($this->connection);
        }

        return sasql_affected_rows($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
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
            $result = sasql_insert_id($this->connection);

            if ($result === false) {
                throw self::exceptionFromSQLAnywhereError($this->connection);
            }

            if ($result === 0) {
                throw DriverException::noInsertId();
            }

            return (string) $result;
        }

        $result = $this->query('SELECT ' . $name . '.CURRVAL')->fetchColumn();

        if ($result === false) {
            throw DriverException::noSuchSequence($name);
        }

        return (string) $result;
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
    public function requiresQueryForServerVersion()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack() : void
    {
        if (! sasql_rollback($this->connection)) {
            throw self::exceptionFromSQLAnywhereError($this->connection);
        }

        $this->endTransaction();
    }

    /**
     * Ends transactional mode and enables auto commit again.
     *
     * @return bool Whether or not ending transactional mode succeeded.
     *
     * @throws DriverException
     */
    private function endTransaction()
    {
        if (! sasql_set_option($this->connection, 'auto_commit', 'on')) {
            throw self::exceptionFromSQLAnywhereError($this->connection);
        }

        return true;
    }

    /**
     * Helper method to turn SQL Anywhere error into exception.
     *
     * @param resource|null $conn The SQL Anywhere connection resource to retrieve the last error from.
     * @param resource|null $stmt The SQL Anywhere statement resource to retrieve the last error from.
     *
     * @return DriverException
     *
     * @throws InvalidArgumentException
     */
    public static function exceptionFromSQLAnywhereError($conn = null, $stmt = null) : DriverException
    {
        if ($conn !== null && ! is_resource($conn)) {
            throw new InvalidArgumentException('Invalid SQL Anywhere connection resource given: ' . $conn);
        }

        if ($stmt !== null && ! is_resource($stmt)) {
            throw new InvalidArgumentException('Invalid SQL Anywhere statement resource given: ' . $stmt);
        }

        $state   = $conn ? sasql_sqlstate($conn) : sasql_sqlstate();
        $code    = null;
        $message = null;

        /**
         * Try retrieving the last error from statement resource if given
         */
        if ($stmt) {
            $code    = sasql_stmt_errno($stmt);
            $message = sasql_stmt_error($stmt);
        }

        /**
         * Try retrieving the last error from the connection resource
         * if either the statement resource is not given or the statement
         * resource is given but the last error could not be retrieved from it (fallback).
         * Depending on the type of error, it is sometimes necessary to retrieve
         * it from the connection resource even though it occurred during
         * a prepared statement.
         */
        if ($conn && ! $code) {
            $code    = sasql_errorcode($conn);
            $message = sasql_error($conn);
        }

        /**
         * Fallback mode if either no connection resource is given
         * or the last error could not be retrieved from the given
         * connection / statement resource.
         */
        if (! $conn || ! $code) {
            $code    = sasql_errorcode();
            $message = sasql_error();
        }

        if ($message) {
            return new DriverException('SQLSTATE [' . $state . '] [' . $code . '] ' . $message, $state, $code);
        }

        return new DriverException('SQL Anywhere error occurred but no error message was retrieved from driver.', $state, $code);
    }
}

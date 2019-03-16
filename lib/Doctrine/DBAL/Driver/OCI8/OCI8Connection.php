<?php

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use UnexpectedValueException;
use const OCI_COMMIT_ON_SUCCESS;
use const OCI_DEFAULT;
use const OCI_NO_AUTO_COMMIT;
use function addcslashes;
use function oci_commit;
use function oci_connect;
use function oci_error;
use function oci_pconnect;
use function oci_rollback;
use function oci_server_version;
use function preg_match;
use function sprintf;
use function str_replace;

/**
 * OCI8 implementation of the Connection interface.
 */
class OCI8Connection implements Connection, ServerInfoAwareConnection
{
    /** @var resource */
    protected $dbh;

    /** @var int */
    protected $executeMode = OCI_COMMIT_ON_SUCCESS;

    /**
     * Creates a Connection to an Oracle Database using oci8 extension.
     *
     * @param string $username
     * @param string $password
     * @param string $db
     * @param string $charset
     * @param int    $sessionMode
     * @param bool   $persistent
     *
     * @throws OCI8Exception
     */
    public function __construct(
        $username,
        $password,
        $db,
        $charset = '',
        $sessionMode = OCI_DEFAULT,
        $persistent = false
    ) {
        $dbh = $persistent
            ? @oci_pconnect($username, $password, $db, $charset, $sessionMode)
            : @oci_connect($username, $password, $db, $charset, $sessionMode);

        if ($dbh === false) {
            throw OCI8Exception::fromErrorInfo(oci_error());
        }

        $this->dbh = $dbh;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnexpectedValueException If the version string returned by the database server
     *                                  does not contain a parsable version number.
     */
    public function getServerVersion()
    {
        $version = oci_server_version($this->dbh);

        if ($version === false) {
            throw OCI8Exception::fromErrorInfo(oci_error($this->dbh));
        }

        if (! preg_match('/\s+(\d+\.\d+\.\d+\.\d+\.\d+)\s+/', $version, $matches)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Unexpected database version string "%s". Cannot parse an appropriate version number from it. ' .
                    'Please report this database version string to the Doctrine team.',
                    $version
                )
            );
        }

        return $matches[1];
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $sql) : DriverStatement
    {
        return new OCI8Statement($this->dbh, $sql, $this);
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
        return "'" . addcslashes(str_replace("'", "''", $input), "\000\n\r\\\032") . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function exec(string $statement) : int
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name === null) {
            return false;
        }

        $sql    = 'SELECT ' . $name . '.CURRVAL FROM DUAL';
        $stmt   = $this->query($sql);
        $result = $stmt->fetchColumn();

        if ($result === false) {
            throw new OCI8Exception('lastInsertId failed: Query was executed but no result was returned.');
        }

        return (int) $result;
    }

    /**
     * Returns the current execution mode.
     *
     * @return int
     */
    public function getExecuteMode()
    {
        return $this->executeMode;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction() : void
    {
        $this->executeMode = OCI_NO_AUTO_COMMIT;
    }

    /**
     * {@inheritdoc}
     */
    public function commit() : void
    {
        if (! oci_commit($this->dbh)) {
            throw OCI8Exception::fromErrorInfo($this->errorInfo());
        }

        $this->executeMode = OCI_COMMIT_ON_SUCCESS;
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack() : void
    {
        if (! oci_rollback($this->dbh)) {
            throw OCI8Exception::fromErrorInfo($this->errorInfo());
        }

        $this->executeMode = OCI_COMMIT_ON_SUCCESS;
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        $error = oci_error($this->dbh);
        if ($error !== false) {
            $error = $error['code'];
        }

        return $error;
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        $error = oci_error($this->dbh);

        if ($error === false) {
            return [];
        }

        return $error;
    }
}

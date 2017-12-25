<?php

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\ParameterType;

/**
 * OCI8 implementation of the Connection interface.
 *
 * @since 2.0
 */
class OCI8Connection implements Connection, ServerInfoAwareConnection
{
    /**
     * @var resource
     */
    protected $dbh;

    /**
     * @var integer
     */
    protected $executeMode = OCI_COMMIT_ON_SUCCESS;

    /**
     * Creates a Connection to an Oracle Database using oci8 extension.
     *
     * @param string      $username
     * @param string      $password
     * @param string      $db
     * @param string|null $charset
     * @param integer     $sessionMode
     * @param boolean     $persistent
     *
     * @throws OCI8Exception
     */
    public function __construct($username, $password, $db, $charset = null, $sessionMode = OCI_DEFAULT, $persistent = false)
    {
        if (!defined('OCI_NO_AUTO_COMMIT')) {
            define('OCI_NO_AUTO_COMMIT', 0);
        }

        $this->dbh = $persistent
            ? @oci_pconnect($username, $password, $db, $charset, $sessionMode)
            : @oci_connect($username, $password, $db, $charset, $sessionMode);

        if ( ! $this->dbh) {
            throw OCI8Exception::fromErrorInfo(oci_error());
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws \UnexpectedValueException if the version string returned by the database server
     *                                   does not contain a parsable version number.
     */
    public function getServerVersion()
    {
        if ( ! preg_match('/\s+(\d+\.\d+\.\d+\.\d+\.\d+)\s+/', oci_server_version($this->dbh), $version)) {
            throw new \UnexpectedValueException(
                sprintf(
                    'Unexpected database version string "%s". Cannot parse an appropriate version number from it. ' .
                    'Please report this database version string to the Doctrine team.',
                    oci_server_version($this->dbh)
                )
            );
        }

        return $version[1];
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
    public function prepare($prepareString)
    {
        return new OCI8Statement($this->dbh, $prepareString, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        $args = func_get_args();
        $sql = $args[0];
        //$fetchMode = $args[1];
        $stmt = $this->prepare($sql);
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
        $value = str_replace("'", "''", $value);

        return "'" . addcslashes($value, "\000\n\r\\\032") . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
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
            throw new OCI8Exception("lastInsertId failed: Query was executed but no result was returned.");
        }

        return (int) $result;
    }

    /**
     * Returns the current execution mode.
     *
     * @return integer
     */
    public function getExecuteMode()
    {
        return $this->executeMode;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->executeMode = OCI_NO_AUTO_COMMIT;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        if (!oci_commit($this->dbh)) {
            throw OCI8Exception::fromErrorInfo($this->errorInfo());
        }
        $this->executeMode = OCI_COMMIT_ON_SUCCESS;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack()
    {
        if (!oci_rollback($this->dbh)) {
            throw OCI8Exception::fromErrorInfo($this->errorInfo());
        }
        $this->executeMode = OCI_COMMIT_ON_SUCCESS;

        return true;
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
        return oci_error($this->dbh);
    }
}

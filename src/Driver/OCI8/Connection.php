<?php

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\OCI8\Exception\ConnectionFailed;
use Doctrine\DBAL\Driver\OCI8\Exception\Error;
use Doctrine\DBAL\Driver\OCI8\Exception\SequenceDoesNotExist;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use UnexpectedValueException;

use function addcslashes;
use function is_float;
use function is_int;
use function oci_commit;
use function oci_connect;
use function oci_pconnect;
use function oci_rollback;
use function oci_server_version;
use function preg_match;
use function sprintf;
use function str_replace;

use const OCI_NO_AUTO_COMMIT;

final class Connection implements ConnectionInterface, ServerInfoAwareConnection
{
    /** @var resource */
    protected $dbh;

    /** @var ExecutionMode */
    private $executionMode;

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
     * @throws Exception
     */
    public function __construct(
        $username,
        $password,
        $db,
        $charset = '',
        $sessionMode = OCI_NO_AUTO_COMMIT,
        $persistent = false
    ) {
        $dbh = $persistent
            ? @oci_pconnect($username, $password, $db, $charset, $sessionMode)
            : @oci_connect($username, $password, $db, $charset, $sessionMode);

        if ($dbh === false) {
            throw ConnectionFailed::new();
        }

        $this->dbh           = $dbh;
        $this->executionMode = new ExecutionMode();
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
            throw Error::new($this->dbh);
        }

        if (preg_match('/\s+(\d+\.\d+\.\d+\.\d+\.\d+)\s+/', $version, $matches) === 0) {
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

    public function prepare(string $sql): DriverStatement
    {
        return new Statement($this->dbh, $sql, $this->executionMode);
    }

    public function query(string $sql): ResultInterface
    {
        return $this->prepare($sql)->execute();
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

    public function exec(string $statement): int
    {
        return $this->prepare($statement)->execute()->rowCount();
    }

    /**
     * {@inheritdoc}
     *
     * @return int|false
     */
    public function lastInsertId($name = null)
    {
        if ($name === null) {
            return false;
        }

        $result = $this->query('SELECT ' . $name . '.CURRVAL FROM DUAL')->fetchOne();

        if ($result === false) {
            throw SequenceDoesNotExist::new();
        }

        return (int) $result;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->executionMode->disableAutoCommit();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        if (! oci_commit($this->dbh)) {
            throw Error::new($this->dbh);
        }

        $this->executionMode->enableAutoCommit();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack()
    {
        if (! oci_rollback($this->dbh)) {
            throw Error::new($this->dbh);
        }

        $this->executionMode->enableAutoCommit();

        return true;
    }
}

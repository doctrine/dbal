<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use UnexpectedValueException;
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
use const OCI_DEFAULT;

/**
 * OCI8 implementation of the Connection interface.
 */
final class OCI8Connection implements Connection, ServerInfoAwareConnection
{
    /** @var resource */
    protected $dbh;

    /** @var ExecutionMode */
    private $executionMode;

    /**
     * Creates a Connection to an Oracle Database using oci8 extension.
     *
     * @throws OCI8Exception
     */
    public function __construct(
        string $username,
        string $password,
        string $db,
        string $charset = '',
        int $sessionMode = OCI_DEFAULT,
        bool $persistent = false
    ) {
        $dbh = $persistent
            ? @oci_pconnect($username, $password, $db, $charset, $sessionMode)
            : @oci_connect($username, $password, $db, $charset, $sessionMode);

        if ($dbh === false) {
            throw OCI8Exception::fromErrorInfo(oci_error());
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
    public function getServerVersion() : string
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

    public function prepare(string $sql) : DriverStatement
    {
        return new OCI8Statement($this->dbh, $sql, $this->executionMode);
    }

    public function query(string $sql) : ResultStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    public function quote(string $input) : string
    {
        return "'" . addcslashes(str_replace("'", "''", $input), "\000\n\r\\\032") . "'";
    }

    public function exec(string $statement) : int
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function lastInsertId(?string $name = null) : string
    {
        if ($name === null) {
            throw new OCI8Exception('The driver does not support identity columns.');
        }

        $sql    = 'SELECT ' . $name . '.CURRVAL FROM DUAL';
        $stmt   = $this->query($sql);
        $result = $stmt->fetchColumn();

        if ($result === false) {
            throw new OCI8Exception('lastInsertId failed: Query was executed but no result was returned.');
        }

        return $result;
    }

    public function beginTransaction() : void
    {
        $this->executionMode->disableAutoCommit();
    }

    public function commit() : void
    {
        if (! oci_commit($this->dbh)) {
            throw OCI8Exception::fromErrorInfo(oci_error($this->dbh));
        }

        $this->executionMode->enableAutoCommit();
    }

    public function rollBack() : void
    {
        if (! oci_rollback($this->dbh)) {
            throw OCI8Exception::fromErrorInfo(oci_error($this->dbh));
        }

        $this->executionMode->enableAutoCommit();
    }
}

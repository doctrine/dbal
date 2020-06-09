<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result as ResultInterface;
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

use const OCI_NO_AUTO_COMMIT;

/**
 * OCI8 implementation of the Connection interface.
 */
final class OCI8Connection implements Connection, ServerInfoAwareConnection
{
    /** @var resource */
    private $connection;

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
        int $sessionMode = OCI_NO_AUTO_COMMIT,
        bool $persistent = false
    ) {
        if ($persistent) {
            $connection = @oci_pconnect($username, $password, $db, $charset, $sessionMode);
        } else {
            $connection = @oci_connect($username, $password, $db, $charset, $sessionMode);
        }

        if ($connection === false) {
            throw OCI8Exception::fromErrorInfo(oci_error());
        }

        $this->connection    = $connection;
        $this->executionMode = new ExecutionMode();
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnexpectedValueException If the version string returned by the database server
     *                                  does not contain a parsable version number.
     */
    public function getServerVersion(): string
    {
        $version = oci_server_version($this->connection);

        if ($version === false) {
            throw OCI8Exception::fromErrorInfo(oci_error($this->connection));
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
        return new OCI8Statement($this->connection, $sql, $this->executionMode);
    }

    public function query(string $sql): ResultInterface
    {
        return $this->prepare($sql)->execute();
    }

    public function quote(string $input): string
    {
        return "'" . addcslashes(str_replace("'", "''", $input), "\000\n\r\\\032") . "'";
    }

    public function exec(string $statement): int
    {
        return $this->prepare($statement)->execute()->rowCount();
    }

    public function lastInsertId(?string $name = null): string
    {
        if ($name === null) {
            throw new OCI8Exception('The driver does not support identity columns.');
        }

        $result = $this->query('SELECT ' . $name . '.CURRVAL FROM DUAL')->fetchOne();

        if ($result === false) {
            throw new OCI8Exception('lastInsertId failed: Query was executed but no result was returned.');
        }

        return $result;
    }

    public function beginTransaction(): void
    {
        $this->executionMode->disableAutoCommit();
    }

    public function commit(): void
    {
        if (! oci_commit($this->connection)) {
            throw OCI8Exception::fromErrorInfo(oci_error($this->connection));
        }

        $this->executionMode->enableAutoCommit();
    }

    public function rollBack(): void
    {
        if (! oci_rollback($this->connection)) {
            throw OCI8Exception::fromErrorInfo(oci_error($this->connection));
        }

        $this->executionMode->enableAutoCommit();
    }
}

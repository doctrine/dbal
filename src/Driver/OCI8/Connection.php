<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\IdentityColumnsNotSupported;
use Doctrine\DBAL\Driver\OCI8\Exception\Error;

use function addcslashes;
use function assert;
use function oci_commit;
use function oci_rollback;
use function oci_server_version;
use function preg_match;
use function str_replace;

final class Connection implements ConnectionInterface
{
    /** @var resource */
    private $connection;

    private ExecutionMode $executionMode;

    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param resource $connection
     */
    public function __construct($connection)
    {
        $this->connection    = $connection;
        $this->executionMode = new ExecutionMode();
    }

    public function getServerVersion(): string
    {
        $version = oci_server_version($this->connection);

        if ($version === false) {
            throw Error::new($this->connection);
        }

        assert(preg_match('/\s+(\d+\.\d+\.\d+\.\d+\.\d+)\s+/', $version, $matches) === 1);

        return $matches[1];
    }

    public function prepare(string $sql): Statement
    {
        return new Statement($this->connection, $sql, $this->executionMode);
    }

    public function query(string $sql): Result
    {
        return $this->prepare($sql)->execute();
    }

    public function quote(string $value): string
    {
        return "'" . addcslashes(str_replace("'", "''", $value), "\000\n\r\\\032") . "'";
    }

    public function exec(string $sql): int
    {
        return $this->prepare($sql)->execute()->rowCount();
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId()
    {
        throw IdentityColumnsNotSupported::new();
    }

    public function beginTransaction(): void
    {
        $this->executionMode->disableAutoCommit();
    }

    public function commit(): void
    {
        if (! oci_commit($this->connection)) {
            throw Error::new($this->connection);
        }

        $this->executionMode->enableAutoCommit();
    }

    public function rollBack(): void
    {
        if (! oci_rollback($this->connection)) {
            throw Error::new($this->connection);
        }

        $this->executionMode->enableAutoCommit();
    }
}

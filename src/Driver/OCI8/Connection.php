<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Exception\IdentityColumnsNotSupported;
use Doctrine\DBAL\Driver\OCI8\Exception\Error;
use Doctrine\DBAL\SQL\Parser;

use function addcslashes;
use function assert;
use function is_resource;
use function oci_commit;
use function oci_parse;
use function oci_rollback;
use function oci_server_version;
use function preg_match;
use function str_replace;

final class Connection implements ConnectionInterface
{
    private readonly Parser $parser;
    private readonly ExecutionMode $executionMode;

    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param resource $connection
     */
    public function __construct(private readonly mixed $connection)
    {
        $this->parser        = new Parser(false);
        $this->executionMode = new ExecutionMode();
    }

    public function getServerVersion(): string
    {
        $version = oci_server_version($this->connection);
        assert($version !== false);

        $result = preg_match('/\s+(\d+\.\d+\.\d+\.\d+\.\d+)\s+/', $version, $matches);
        assert($result === 1);

        return $matches[1];
    }

    /** @throws Parser\Exception */
    public function prepare(string $sql): Statement
    {
        $visitor = new ConvertPositionalToNamedPlaceholders();

        $this->parser->parse($sql, $visitor);

        $statement = oci_parse($this->connection, $visitor->getSQL());
        assert(is_resource($statement));

        return new Statement($this->connection, $statement, $visitor->getParameterMap(), $this->executionMode);
    }

    /**
     * @throws Exception
     * @throws Parser\Exception
     */
    public function query(string $sql): Result
    {
        return $this->prepare($sql)->execute();
    }

    public function quote(string $value): string
    {
        return "'" . addcslashes(str_replace("'", "''", $value), "\000\n\r\\\032") . "'";
    }

    /**
     * @throws Exception
     * @throws Parser\Exception
     */
    public function exec(string $sql): int|string
    {
        return $this->prepare($sql)->execute()->rowCount();
    }

    public function lastInsertId(): int|string
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

    /** @return resource */
    public function getNativeConnection()
    {
        return $this->connection;
    }
}

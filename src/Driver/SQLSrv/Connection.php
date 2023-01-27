<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\NoIdentityValue;
use Doctrine\DBAL\Driver\SQLSrv\Exception\Error;

use function sqlsrv_begin_transaction;
use function sqlsrv_commit;
use function sqlsrv_query;
use function sqlsrv_rollback;
use function sqlsrv_rows_affected;
use function sqlsrv_server_info;
use function str_replace;

final class Connection implements ConnectionInterface
{
    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param resource $connection
     */
    public function __construct(private readonly mixed $connection)
    {
    }

    public function getServerVersion(): string
    {
        $serverInfo = sqlsrv_server_info($this->connection);

        return $serverInfo['SQLServerVersion'];
    }

    public function prepare(string $sql): Statement
    {
        return new Statement($this->connection, $sql);
    }

    public function query(string $sql): Result
    {
        return $this->prepare($sql)->execute();
    }

    public function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    public function exec(string $sql): int
    {
        $stmt = sqlsrv_query($this->connection, $sql);

        if ($stmt === false) {
            throw Error::new();
        }

        $rowsAffected = sqlsrv_rows_affected($stmt);

        if ($rowsAffected === false) {
            throw Error::new();
        }

        return $rowsAffected;
    }

    public function lastInsertId(): int|string
    {
        $result = $this->query('SELECT @@IDENTITY');

        $lastInsertId = $result->fetchOne();

        if ($lastInsertId === null) {
            throw NoIdentityValue::new();
        }

        return $lastInsertId;
    }

    public function beginTransaction(): void
    {
        if (! sqlsrv_begin_transaction($this->connection)) {
            throw Error::new();
        }
    }

    public function commit(): void
    {
        if (! sqlsrv_commit($this->connection)) {
            throw Error::new();
        }
    }

    public function rollBack(): void
    {
        if (! sqlsrv_rollback($this->connection)) {
            throw Error::new();
        }
    }

    /** @return resource */
    public function getNativeConnection()
    {
        return $this->connection;
    }
}

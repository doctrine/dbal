<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\NoIdentityValue;
use Doctrine\DBAL\Driver\SQLSrv\Exception\Error;
use Override;

use function sqlsrv_begin_transaction;
use function sqlsrv_commit;
use function sqlsrv_query;
use function sqlsrv_rollback;
use function sqlsrv_rows_affected;
use function sqlsrv_server_info;
use function str_replace;

final readonly class Connection implements ConnectionInterface
{
    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param resource $connection
     */
    public function __construct(private mixed $connection)
    {
    }

    #[Override]
    public function getServerVersion(): string
    {
        $serverInfo = sqlsrv_server_info($this->connection);

        return $serverInfo['SQLServerVersion'];
    }

    #[Override]
    public function prepare(string $sql): Statement
    {
        return new Statement($this->connection, $sql);
    }

    #[Override]
    public function query(string $sql): Result
    {
        return $this->prepare($sql)->execute();
    }

    #[Override]
    public function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    #[Override]
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

    #[Override]
    public function lastInsertId(): int|string
    {
        $result = $this->query('SELECT @@IDENTITY');

        $lastInsertId = $result->fetchOne();

        if ($lastInsertId === null) {
            throw NoIdentityValue::new();
        }

        return $lastInsertId;
    }

    #[Override]
    public function beginTransaction(): void
    {
        if (! sqlsrv_begin_transaction($this->connection)) {
            throw Error::new();
        }
    }

    #[Override]
    public function commit(): void
    {
        if (! sqlsrv_commit($this->connection)) {
            throw Error::new();
        }
    }

    #[Override]
    public function rollBack(): void
    {
        if (! sqlsrv_rollback($this->connection)) {
            throw Error::new();
        }
    }

    /** @return resource */
    #[Override]
    public function getNativeConnection()
    {
        return $this->connection;
    }
}

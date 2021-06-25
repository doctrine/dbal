<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\SQLSrv\Exception\Error;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\Deprecations\Deprecation;

use function sqlsrv_begin_transaction;
use function sqlsrv_commit;
use function sqlsrv_configure;
use function sqlsrv_connect;
use function sqlsrv_query;
use function sqlsrv_rollback;
use function sqlsrv_rows_affected;
use function sqlsrv_server_info;
use function str_replace;

final class Connection implements ServerInfoAwareConnection
{
    /** @var resource */
    private $conn;

    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param array<string, mixed> $connectionOptions
     *
     * @throws Exception
     */
    public function __construct(string $serverName, array $connectionOptions)
    {
        if (! sqlsrv_configure('WarningsReturnAsErrors', 0)) {
            throw Error::new();
        }

        $conn = sqlsrv_connect($serverName, $connectionOptions);

        if ($conn === false) {
            throw Error::new();
        }

        $this->conn = $conn;
    }

    public function getServerVersion(): string
    {
        $serverInfo = sqlsrv_server_info($this->conn);

        return $serverInfo['SQLServerVersion'];
    }

    public function prepare(string $sql): DriverStatement
    {
        return new Statement($this->conn, $sql);
    }

    public function query(string $sql): ResultInterface
    {
        return $this->prepare($sql)->execute();
    }

    public function quote(string $input): string
    {
        return "'" . str_replace("'", "''", $input) . "'";
    }

    public function exec(string $sql): int
    {
        $stmt = sqlsrv_query($this->conn, $sql);

        if ($stmt === false) {
            throw Error::new();
        }

        $rowsAffected = sqlsrv_rows_affected($stmt);

        if ($rowsAffected === false) {
            throw Error::new();
        }

        return $rowsAffected;
    }

    public function lastInsertId(?string $name = null): string
    {
        if ($name !== null) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/4687',
                'The usage of Connection::lastInsertId() with a sequence name is deprecated.'
            );

            $result = $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?')
                ->execute([$name]);
        } else {
            $result = $this->query('SELECT @@IDENTITY');
        }

        return $result->fetchOne();
    }

    public function beginTransaction(): void
    {
        if (! sqlsrv_begin_transaction($this->conn)) {
            throw Error::new();
        }
    }

    public function commit(): void
    {
        if (! sqlsrv_commit($this->conn)) {
            throw Error::new();
        }
    }

    public function rollBack(): void
    {
        if (! sqlsrv_rollback($this->conn)) {
            throw Error::new();
        }
    }
}

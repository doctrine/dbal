<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Synchronizer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Throwable;

/**
 * Abstract schema synchronizer with methods for executing batches of SQL.
 */
abstract class AbstractSchemaSynchronizer implements SchemaSynchronizer
{
    /** @var Connection */
    protected $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /**
     * @param array<int, string> $sql
     */
    protected function processSqlSafely(array $sql): void
    {
        foreach ($sql as $s) {
            try {
                $this->conn->executeStatement($s);
            } catch (Throwable $e) {
            }
        }
    }

    /**
     * @param array<int, string> $sql
     *
     * @throws DBALException
     */
    protected function processSql(array $sql): void
    {
        foreach ($sql as $s) {
            $this->conn->executeStatement($s);
        }
    }
}

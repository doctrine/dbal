<?php

namespace Doctrine\DBAL\Schema\Synchronizer;

use Doctrine\DBAL\Connection;

/**
 * Abstract schema synchronizer with methods for executing batches of SQL.
 */
abstract class AbstractSchemaSynchronizer implements SchemaSynchronizer
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $conn;

    /**
     * @param \Doctrine\DBAL\Connection $conn
     */
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /**
     * @param array $sql
     */
    protected function processSqlSafely(array $sql)
    {
        foreach ($sql as $s) {
            try {
                $this->conn->exec($s);
            } catch (\Exception $e) {

            }
        }
    }

    /**
     * @param array $sql
     */
    protected function processSql(array $sql)
    {
        foreach ($sql as $s) {
            $this->conn->exec($s);
        }
    }
}

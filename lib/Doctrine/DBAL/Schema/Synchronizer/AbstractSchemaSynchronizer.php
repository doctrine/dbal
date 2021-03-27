<?php

namespace Doctrine\DBAL\Schema\Synchronizer;

use Doctrine\DBAL\Connection;
use Doctrine\Deprecations\Deprecation;
use Throwable;

/**
 * Abstract schema synchronizer with methods for executing batches of SQL.
 *
 * @deprecated
 */
abstract class AbstractSchemaSynchronizer implements SchemaSynchronizer
{
    /** @var Connection */
    protected $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;

        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4213',
            'SchemaSynchronizer API is deprecated without a replacement and will be removed in DBAL 3.0'
        );
    }

    /**
     * @param string[] $sql
     *
     * @return void
     */
    protected function processSqlSafely(array $sql)
    {
        foreach ($sql as $s) {
            try {
                $this->conn->exec($s);
            } catch (Throwable $e) {
            }
        }
    }

    /**
     * @param string[] $sql
     *
     * @return void
     */
    protected function processSql(array $sql)
    {
        foreach ($sql as $s) {
            $this->conn->exec($s);
        }
    }
}

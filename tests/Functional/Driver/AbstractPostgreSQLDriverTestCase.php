<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver;

use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;

use function array_key_exists;
use function microtime;
use function sprintf;

abstract class AbstractPostgreSQLDriverTestCase extends AbstractDriverTestCase
{
    public function testConnectsWithApplicationNameParameter(): void
    {
        $parameters                     = $this->connection->getParams();
        $parameters['application_name'] = 'doctrine';

        $connection = $this->driver->connect($parameters);

        $hash    = microtime(true); // required to identify the record in the results uniquely
        $sql     = sprintf('SELECT * FROM pg_stat_activity WHERE %d = %d', $hash, $hash);
        $records = $connection->query($sql)->fetchAllAssociative();

        foreach ($records as $record) {
            // The query column is named "current_query" on PostgreSQL < 9.2
            $queryColumnName = array_key_exists('current_query', $record) ? 'current_query' : 'query';

            if ($record[$queryColumnName] === $sql) {
                self::assertSame('doctrine', $record['application_name']);

                return;
            }
        }

        self::fail(sprintf('Query result does not contain a record where column "query" equals "%s".', $sql));
    }

    abstract protected function createDriver(): AbstractPostgreSQLDriver;

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return 'postgres';
    }
}

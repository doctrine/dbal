<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\PgSQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;
use Doctrine\DBAL\Tests\TestUtil;

use function array_key_exists;
use function microtime;
use function sprintf;

/**
 * @requires extension pdo_pgsql
 */
class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_pgsql')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_pgsql driver.');
    }

    /**
     * @dataProvider getDatabaseParameter
     */
    public function testDatabaseParameters(
        ?string $databaseName,
        ?string $defaultDatabaseName,
        ?string $expectedDatabaseName
    ): void {
        $params = $this->connection->getParams();

        if ($databaseName !== null) {
            $params['dbname'] = $databaseName;
        } else {
            unset($params['dbname']);
        }

        if ($defaultDatabaseName !== null) {
            $params['default_dbname'] = $defaultDatabaseName;
        }

        $connection = new Connection(
            $params,
            $this->connection->getDriver(),
            $this->connection->getConfiguration(),
            $this->connection->getEventManager()
        );

        self::assertSame(
            $expectedDatabaseName,
            $connection->getDatabase()
        );
    }

    /**
     * @return mixed[][]
     */
    public static function getDatabaseParameter(): iterable
    {
        $params            = TestUtil::getConnectionParams();
        $realDatabaseName  = $params['dbname'] ?? '';
        $dummyDatabaseName = $realDatabaseName . 'a';

        return [
            // dbname, default_dbname, expected
            [$realDatabaseName, null, $realDatabaseName],
            [$realDatabaseName, $dummyDatabaseName, $realDatabaseName],
            [null, $realDatabaseName, $realDatabaseName],
            [null, null, static::getDatabaseNameForConnectionWithoutDatabaseNameParameter()],
        ];
    }

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

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return 'postgres';
    }
}

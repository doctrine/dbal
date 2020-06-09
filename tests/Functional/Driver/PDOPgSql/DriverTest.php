<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PDOPgSql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDOPgSql\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;
use Doctrine\DBAL\Tests\TestUtil;

use function array_key_exists;
use function extension_loaded;
use function microtime;
use function sprintf;

class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('pdo_pgsql is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        self::markTestSkipped('pdo_pgsql only test.');
    }

    /**
     * @dataProvider getDatabaseParameter
     */
    public function testDatabaseParameters(?string $databaseName, ?string $defaultDatabaseName, ?string $expectedDatabaseName): void
    {
        $params                   = $this->connection->getParams();
        $params['dbname']         = $databaseName;
        $params['default_dbname'] = $defaultDatabaseName;

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

    /**
     * @group DBAL-1146
     */
    public function testConnectsWithApplicationNameParameter(): void
    {
        $parameters                     = $this->connection->getParams();
        $parameters['application_name'] = 'doctrine';

        $user     = $parameters['user'] ?? '';
        $password = $parameters['password'] ?? '';

        $connection = $this->driver->connect($parameters, $user, $password);

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

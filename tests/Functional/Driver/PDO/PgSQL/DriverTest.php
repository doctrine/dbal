<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\PgSQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractPostgreSQLDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;

/** @requires extension pdo_pgsql */
class DriverTest extends AbstractPostgreSQLDriverTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_pgsql')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_pgsql driver.');
    }

    /** @dataProvider getDatabaseParameter */
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
            $this->connection->getEventManager(),
        );

        self::assertSame(
            $expectedDatabaseName,
            $connection->getDatabase(),
        );
    }

    /** @return mixed[][] */
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

    protected function createDriver(): Driver
    {
        return new Driver();
    }
}

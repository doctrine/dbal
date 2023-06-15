<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\PDO\PgSQL;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractPostgreSQLDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;

use function array_merge;

class DriverTest extends AbstractPostgreSQLDriverTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (isset($GLOBALS['db_driver']) && $GLOBALS['db_driver'] === 'pdo_pgsql') {
            return;
        }

        self::markTestSkipped('Test enabled only when using pdo_pgsql specific phpunit.xml');
    }

    public function testConnectionDisablesPrepares(): void
    {
        $connection = $this->connect([]);

        self::assertInstanceOf(PDO\Connection::class, $connection);
        self::assertTrue(
            $connection->getNativeConnection()->getAttribute(\PDO::PGSQL_ATTR_DISABLE_PREPARES),
        );
    }

    public function testConnectionDoesNotDisablePreparesWhenAttributeDefined(): void
    {
        $connection = $this->connect(
            [\PDO::PGSQL_ATTR_DISABLE_PREPARES => false],
        );

        self::assertInstanceOf(PDO\Connection::class, $connection);
        self::assertNotTrue(
            $connection->getNativeConnection()->getAttribute(\PDO::PGSQL_ATTR_DISABLE_PREPARES),
        );
    }

    public function testConnectionDisablePreparesWhenDisablePreparesIsExplicitlyDefined(): void
    {
        $connection = $this->connect(
            [\PDO::PGSQL_ATTR_DISABLE_PREPARES => true],
        );

        self::assertInstanceOf(PDO\Connection::class, $connection);
        self::assertTrue(
            $connection->getNativeConnection()->getAttribute(\PDO::PGSQL_ATTR_DISABLE_PREPARES),
        );
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }

    /** @param array<int,mixed> $driverOptions */
    private function connect(array $driverOptions): Connection
    {
        return $this->createDriver()->connect(
            array_merge(
                TestUtil::getConnectionParams(),
                ['driverOptions' => $driverOptions],
            ),
        );
    }
}

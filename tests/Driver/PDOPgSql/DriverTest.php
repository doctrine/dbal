<?php

namespace Doctrine\DBAL\Tests\Driver\PDOPgSql;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\PDO\Connection as PDOConnection;
use Doctrine\DBAL\Driver\PDOPgSql\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractPostgreSQLDriverTest;
use Doctrine\DBAL\Tests\TestUtil;
use PDO;

use function array_merge;

class DriverTest extends AbstractPostgreSQLDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (isset($GLOBALS['db_type']) && $GLOBALS['db_driver'] === 'pdo_pgsql') {
            return;
        }

        $this->markTestSkipped('Test enabled only when using pdo_pgsql specific phpunit.xml');
    }

    /**
     * @group DBAL-920
     */
    public function testConnectionDisablesPrepares(): void
    {
        $connection = $this->connect([]);

        self::assertInstanceOf(PDOConnection::class, $connection);
        self::assertTrue(
            $connection->getWrappedConnection()->getAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES)
        );
    }

    /**
     * @group DBAL-920
     */
    public function testConnectionDoesNotDisablePreparesWhenAttributeDefined(): void
    {
        $connection = $this->connect(
            [PDO::PGSQL_ATTR_DISABLE_PREPARES => false]
        );

        self::assertInstanceOf(PDOConnection::class, $connection);
        self::assertNotTrue(
            $connection->getWrappedConnection()->getAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES)
        );
    }

    /**
     * @group DBAL-920
     */
    public function testConnectionDisablePreparesWhenDisablePreparesIsExplicitlyDefined(): void
    {
        $connection = $this->connect(
            [PDO::PGSQL_ATTR_DISABLE_PREPARES => true]
        );

        self::assertInstanceOf(PDOConnection::class, $connection);
        self::assertTrue(
            $connection->getWrappedConnection()->getAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES)
        );
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }

    /**
     * @param array<int,mixed> $driverOptions
     */
    private function connect(array $driverOptions): Connection
    {
        return $this->createDriver()->connect(
            array_merge(
                TestUtil::getConnectionParams(),
                ['driver_options' => $driverOptions]
            )
        );
    }
}

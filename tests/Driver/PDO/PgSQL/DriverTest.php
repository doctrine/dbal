<?php

namespace Doctrine\DBAL\Tests\Driver\PDO\PgSQL;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractPostgreSQLDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Pdo\Pgsql;

use function array_merge;

use const PHP_VERSION_ID;

class DriverTest extends AbstractPostgreSQLDriverTestCase
{
    private const DISABLE_PREPARES_ATTRIBUTE = PHP_VERSION_ID >= 80400
        ? Pgsql::ATTR_DISABLE_PREPARES
        : \PDO::PGSQL_ATTR_DISABLE_PREPARES;

    protected function setUp(): void
    {
        parent::setUp();

        if (isset($GLOBALS['db_driver']) && $GLOBALS['db_driver'] === 'pdo_pgsql') {
            return;
        }

        $this->markTestSkipped('Test enabled only when using pdo_pgsql specific phpunit.xml');
    }

    public function testConnectionDisablesPrepares(): void
    {
        $connection = $this->connect([]);

        self::assertInstanceOf(PDO\Connection::class, $connection);
        self::assertTrue(
            $connection->getNativeConnection()->getAttribute(self::DISABLE_PREPARES_ATTRIBUTE),
        );
    }

    public function testConnectionDoesNotDisablePreparesWhenAttributeDefined(): void
    {
        $connection = $this->connect(
            [self::DISABLE_PREPARES_ATTRIBUTE => false],
        );

        self::assertInstanceOf(PDO\Connection::class, $connection);
        self::assertNotTrue(
            $connection->getNativeConnection()->getAttribute(self::DISABLE_PREPARES_ATTRIBUTE),
        );
    }

    public function testConnectionDisablePreparesWhenDisablePreparesIsExplicitlyDefined(): void
    {
        $connection = $this->connect(
            [self::DISABLE_PREPARES_ATTRIBUTE => true],
        );

        self::assertInstanceOf(PDO\Connection::class, $connection);
        self::assertTrue(
            $connection->getNativeConnection()->getAttribute(self::DISABLE_PREPARES_ATTRIBUTE),
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

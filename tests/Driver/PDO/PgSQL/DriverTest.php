<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\PDO\PgSQL;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Driver\PDO\Exception\InvalidConfiguration;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;

use function array_merge;

class DriverTest extends AbstractDriverTestCase
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

    public function testUserIsFalse(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage(
            'The user configuration parameter is expected to be either a string or null, got bool.',
        );
        $this->driver->connect(['user' => false]);
    }

    public function testPasswordIsFalse(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage(
            'The password configuration parameter is expected to be either a string or null, got bool.',
        );
        $this->driver->connect(['password' => false]);
    }

    protected function createDriver(): Driver
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

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\PDOPgSql;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\PDOPgSql\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractPostgreSQLDriverTest;
use PDO;

class DriverTest extends AbstractPostgreSQLDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (isset($GLOBALS['db_type']) && $GLOBALS['db_type'] === 'pdo_pgsql') {
            return;
        }

        self::markTestSkipped('Test enabled only when using pdo_pgsql specific phpunit.xml');
    }

    /**
     * @group DBAL-920
     */
    public function testConnectionDisablesPrepares(): void
    {
        $connection = $this->createDriver()->connect(
            [
                'host' => $GLOBALS['db_host'],
                'port' => $GLOBALS['db_port'],
            ],
            $GLOBALS['db_username'],
            $GLOBALS['db_password']
        );

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
        $connection = $this->createDriver()->connect(
            [
                'host' => $GLOBALS['db_host'],
                'port' => $GLOBALS['db_port'],
            ],
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
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
        $connection = $this->createDriver()->connect(
            [
                'host' => $GLOBALS['db_host'],
                'port' => $GLOBALS['db_port'],
            ],
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
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
}

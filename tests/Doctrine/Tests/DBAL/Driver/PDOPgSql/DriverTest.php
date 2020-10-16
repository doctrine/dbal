<?php

namespace Doctrine\Tests\DBAL\Driver\PDOPgSql;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractPostgreSQLDriverTest;
use Doctrine\Tests\TestUtil;
use PDO;
use PDOException;

class DriverTest extends AbstractPostgreSQLDriverTest
{
    public function testReturnsName(): void
    {
        self::assertSame('pdo_pgsql', $this->driver->getName());
    }

    public function testConnectionDisablesPreparesOnPhp56(): void
    {
        $this->skipWhenNotUsingPdoPgsql();

        $connection = $this->connect([]);

        try {
            self::assertTrue($connection->getAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES));
        } catch (PDOException $ignored) {
            /** @link https://bugs.php.net/bug.php?id=68371 */
            $this->markTestIncomplete('See https://bugs.php.net/bug.php?id=68371');
        }
    }

    public function testConnectionDoesNotDisablePreparesOnPhp56WhenAttributeDefined(): void
    {
        $this->skipWhenNotUsingPdoPgsql();

        $connection = $this->connect(
            [PDO::PGSQL_ATTR_DISABLE_PREPARES => false]
        );

        try {
            self::assertNotSame(true, $connection->getAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES));
        } catch (PDOException $ignored) {
            /** @link https://bugs.php.net/bug.php?id=68371 */
            $this->markTestIncomplete('See https://bugs.php.net/bug.php?id=68371');
        }
    }

    public function testConnectionDisablePreparesOnPhp56WhenDisablePreparesIsExplicitlyDefined(): void
    {
        $this->skipWhenNotUsingPdoPgsql();

        $connection = $this->connect(
            [PDO::PGSQL_ATTR_DISABLE_PREPARES => true]
        );

        try {
            self::assertTrue($connection->getAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES));
        } catch (PDOException $ignored) {
            /** @link https://bugs.php.net/bug.php?id=68371 */
            $this->markTestIncomplete('See https://bugs.php.net/bug.php?id=68371');
        }
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }

    private function skipWhenNotUsingPdoPgsql(): void
    {
        if (isset($GLOBALS['db_driver']) && $GLOBALS['db_driver'] === 'pdo_pgsql') {
            return;
        }

        $this->markTestSkipped('Test enabled only when using pdo_pgsql specific phpunit.xml');
    }

    /**
     * @param array<int,mixed> $driverOptions
     */
    private function connect(array $driverOptions): Connection
    {
        $params = TestUtil::getConnectionParams();

        $connection = $this->createDriver()->connect(
            $params,
            $params['user'] ?? '',
            $params['password'] ?? '',
            $driverOptions
        );

        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}

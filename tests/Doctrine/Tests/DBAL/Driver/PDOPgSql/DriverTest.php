<?php

namespace Doctrine\Tests\DBAL\Driver\PDOPgSql;

use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\PDOPgSql\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractPostgreSQLDriverTest;
use PDO;
use PDOException;
use PHPUnit_Framework_SkippedTestError;
use function defined;

class DriverTest extends AbstractPostgreSQLDriverTest
{
    public function testReturnsName()
    {
        self::assertSame('pdo_pgsql', $this->driver->getName());
    }

    /**
     * @group DBAL-920
     */
    public function testConnectionDisablesPreparesOnPhp56()
    {
        $this->skipWhenNotUsingPhp56AndPdoPgsql();

        $connection = $this->createDriver()->connect(
            [
                'host' => $GLOBALS['db_host'],
                'port' => $GLOBALS['db_port'],
            ],
            $GLOBALS['db_username'],
            $GLOBALS['db_password']
        );

        self::assertInstanceOf(PDOConnection::class, $connection);

        try {
            self::assertTrue($connection->getWrappedConnection()->getAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES));
        } catch (PDOException $ignored) {
            /** @link https://bugs.php.net/bug.php?id=68371 */
            $this->markTestIncomplete('See https://bugs.php.net/bug.php?id=68371');
        }
    }

    /**
     * @group DBAL-920
     */
    public function testConnectionDoesNotDisablePreparesOnPhp56WhenAttributeDefined()
    {
        $this->skipWhenNotUsingPhp56AndPdoPgsql();

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

        try {
            self::assertNotSame(
                true,
                $connection->getWrappedConnection()->getAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES)
            );
        } catch (PDOException $ignored) {
            /** @link https://bugs.php.net/bug.php?id=68371 */
            $this->markTestIncomplete('See https://bugs.php.net/bug.php?id=68371');
        }
    }

    /**
     * @group DBAL-920
     */
    public function testConnectionDisablePreparesOnPhp56WhenDisablePreparesIsExplicitlyDefined()
    {
        $this->skipWhenNotUsingPhp56AndPdoPgsql();

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

        try {
            self::assertTrue($connection->getWrappedConnection()->getAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES));
        } catch (PDOException $ignored) {
            /** @link https://bugs.php.net/bug.php?id=68371 */
            $this->markTestIncomplete('See https://bugs.php.net/bug.php?id=68371');
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function createDriver()
    {
        return new Driver();
    }

    /**
     * @throws PHPUnit_Framework_SkippedTestError
     */
    private function skipWhenNotUsingPhp56AndPdoPgsql()
    {
        if (! defined('PDO::PGSQL_ATTR_DISABLE_PREPARES')) {
            $this->markTestSkipped('Test requires PHP 5.6+');
        }

        if (isset($GLOBALS['db_type']) && $GLOBALS['db_type'] === 'pdo_pgsql') {
            return;
        }

        $this->markTestSkipped('Test enabled only when using pdo_pgsql specific phpunit.xml');
    }
}

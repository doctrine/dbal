<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\PDOSqlsrv\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;
use PDO;

use function assert;

use function extension_loaded;

class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlsrv')) {
            self::markTestSkipped('pdo_sqlsrv is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        self::markTestSkipped('pdo_sqlsrv only test.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return 'master';
    }

    /**
     * @param int[]|string[] $driverOptions
     */
    protected function getConnection(array $driverOptions): Connection
    {
        return $this->connection->getDriver()->connect(
            [
                'host' => $GLOBALS['db_host'],
                'port' => $GLOBALS['db_port'],
            ],
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
            $driverOptions
        );
    }

    public function testConnectionOptions(): void
    {
        $connection = $this->getConnection(['APP' => 'APP_NAME']);
        $result     = $connection->query('SELECT APP_NAME()')->fetchOne();

        self::assertSame('APP_NAME', $result);
    }

    public function testDriverOptions(): void
    {
        $connection = $this->getConnection([PDO::ATTR_CASE => PDO::CASE_UPPER]);

        assert($connection instanceof PDOConnection);

        self::assertSame(
            PDO::CASE_UPPER,
            $connection
                ->getWrappedConnection()
                ->getAttribute(PDO::ATTR_CASE)
        );
    }
}

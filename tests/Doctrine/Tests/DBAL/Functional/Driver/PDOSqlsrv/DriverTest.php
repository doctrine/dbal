<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\PDOSqlsrv\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;
use Doctrine\Tests\TestUtil;
use PDO;

use function array_merge;

/**
 * @requires extension pdo_sqlsrv
 */
class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('pdo_sqlsrv only test.');
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
    private function getConnection(array $driverOptions): PDOConnection
    {
        $params = TestUtil::getConnectionParams();

        if (isset($params['driverOptions'])) {
            $driverOptions = array_merge($params['driverOptions'], $driverOptions);
        }

        return $this->connection->getDriver()->connect(
            $params,
            $params['user'] ?? '',
            $params['password'] ?? '',
            $driverOptions
        );
    }

    public function testConnectionOptions(): void
    {
        $connection = $this->getConnection(['APP' => 'APP_NAME']);
        $result     = $connection->query('SELECT APP_NAME()')->fetchColumn();

        self::assertSame('APP_NAME', $result);
    }

    public function testDriverOptions(): void
    {
        $connection = $this->getConnection([PDO::ATTR_CASE => PDO::CASE_UPPER]);

        self::assertSame(PDO::CASE_UPPER, $connection->getAttribute(PDO::ATTR_CASE));
    }
}

<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;
use Doctrine\DBAL\Driver\Mysqli\MysqliException;
use Doctrine\Tests\DbalFunctionalTestCase;
use Doctrine\Tests\TestUtil;

use function array_merge;

use const MYSQLI_OPT_CONNECT_TIMEOUT;

/**
 * @requires extension mysqli
 */
class ConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('MySQLi only test.');
    }

    public function testDriverOptions(): void
    {
        $driverOptions = [MYSQLI_OPT_CONNECT_TIMEOUT => 1];

        $connection = $this->getConnection($driverOptions);
        self::assertInstanceOf(MysqliConnection::class, $connection);
    }

    public function testUnsupportedDriverOption(): void
    {
        $this->expectException(MysqliException::class);

        $this->getConnection(['hello' => 'world']); // use local infile
    }

    public function testPing(): void
    {
        $conn = $this->getConnection([]);
        self::assertTrue($conn->ping());
    }

    /**
     * @param mixed[] $driverOptions
     */
    private function getConnection(array $driverOptions): MysqliConnection
    {
        $params = TestUtil::getConnectionParams();

        if (isset($params['driverOptions'])) {
            $driverOptions = array_merge($params['driverOptions'], $driverOptions);
        }

        return new MysqliConnection(
            $params,
            $params['user'] ?? '',
            $params['password'] ?? '',
            $driverOptions
        );
    }
}

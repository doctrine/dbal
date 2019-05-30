<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;
use Doctrine\DBAL\Driver\Mysqli\MysqliException;
use Doctrine\Tests\DbalFunctionalTestCase;
use const MYSQLI_OPT_CONNECT_TIMEOUT;
use function extension_loaded;

class ConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp() : void
    {
        if (! extension_loaded('mysqli')) {
            $this->markTestSkipped('mysqli is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('MySQLi only test.');
    }

    protected function tearDown() : void
    {
        parent::tearDown();
    }

    public function testDriverOptions() : void
    {
        $driverOptions = [MYSQLI_OPT_CONNECT_TIMEOUT => 1];

        $connection = $this->getConnection($driverOptions);
        self::assertInstanceOf(MysqliConnection::class, $connection);
    }

    public function testUnsupportedDriverOption() : void
    {
        $this->expectException(MysqliException::class);

        $this->getConnection(['hello' => 'world']); // use local infile
    }

    public function testPing() : void
    {
        $conn = $this->getConnection([]);
        self::assertTrue($conn->ping());
    }

    /**
     * @param mixed[] $driverOptions
     */
    private function getConnection(array $driverOptions) : MysqliConnection
    {
        return new MysqliConnection(
            [
                'host' => $GLOBALS['db_host'],
                'dbname' => $GLOBALS['db_name'],
                'port' => $GLOBALS['db_port'],
            ],
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
            $driverOptions
        );
    }
}

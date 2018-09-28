<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;
use Doctrine\Tests\DbalFunctionalTestCase;
use const MYSQLI_OPT_CONNECT_TIMEOUT;
use function extension_loaded;

class ConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp()
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

    protected function tearDown()
    {
        parent::tearDown();
    }

    public function testDriverOptions()
    {
        $driverOptions = [MYSQLI_OPT_CONNECT_TIMEOUT => 1];

        $connection = $this->getConnection($driverOptions);
        self::assertInstanceOf(MysqliConnection::class, $connection);
    }

    /**
     * @expectedException \Doctrine\DBAL\Driver\Mysqli\MysqliException
     */
    public function testUnsupportedDriverOption()
    {
        $this->getConnection(['hello' => 'world']); // use local infile
    }

    public function testPing()
    {
        $conn = $this->getConnection([]);
        self::assertTrue($conn->ping());
    }

    /**
     * @param mixed[] $driverOptions
     */
    private function getConnection(array $driverOptions)
    {
        return new MysqliConnection(
            [
                'host' => $GLOBALS['db_host'],
                'dbname' => $GLOBALS['db_name'],
            ],
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
            $driverOptions
        );
    }
}

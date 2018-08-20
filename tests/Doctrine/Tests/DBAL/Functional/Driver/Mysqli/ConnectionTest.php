<?php
namespace Doctrine\Tests\DBAL\Functional\Driver\Mysqli;
use function extension_loaded;

class ConnectionTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        if (!extension_loaded('mysqli')) {
            $this->markTestSkipped('mysqli is not installed.');
        }

        parent::setUp();

        if ( !($this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\Mysqli\Driver)) {
            $this->markTestSkipped('MySQLi only test.');
        }
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    public function testDriverOptions()
    {
        $driverOptions = array(
            \MYSQLI_OPT_CONNECT_TIMEOUT => 1,
        );

        $connection = $this->getConnection($driverOptions);
        self::assertInstanceOf("\Doctrine\DBAL\Driver\Mysqli\MysqliConnection", $connection);
    }

    /**
     * @expectedException \Doctrine\DBAL\Driver\Mysqli\MysqliException
     */
    public function testUnsupportedDriverOption()
    {
        $this->getConnection(array('hello' => 'world')); // use local infile
    }

    public function testPing()
    {
        $conn = $this->getConnection(array());
        self::assertTrue($conn->ping());
    }

    private function getConnection(array $driverOptions)
    {
        return new \Doctrine\DBAL\Driver\Mysqli\MysqliConnection(
            array(
                 'host' => $GLOBALS['db_host'],
                 'dbname' => $GLOBALS['db_name'],
            ),
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
            $driverOptions
        );
    }
}

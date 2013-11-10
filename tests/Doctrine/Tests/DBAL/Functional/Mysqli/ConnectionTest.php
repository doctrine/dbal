<?php
namespace Doctrine\Tests\DBAL\Functional\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;

class ConnectionTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function setUp()
    {
        $this->resetSharedConn();
        parent::setUp();

        if (!$this->_conn->getWrappedConnection() instanceof MysqliConnection) {
            $this->markTestSkipped('this test case is for mysqli only');
        }
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->resetSharedConn();
    }

    public function testDriverOptions()
    {
        $driverOptions = array(
            \MYSQLI_OPT_CONNECT_TIMEOUT => 1,
        );

        $connection = $this->getConnection($driverOptions);
        $this->assertInstanceOf("\Doctrine\DBAL\Driver\Mysqli\MysqliConnection", $connection);
    }

    /**
     * @expectedException \Doctrine\DBAL\Driver\Mysqli\MysqliException
     */
    public function testUnsupportedDriverOption()
    {
        $this->getConnection(array('hello' => 'world')); // use local infile
    }

    private function getConnection(array $driverOptions)
    {
        return new MysqliConnection(
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
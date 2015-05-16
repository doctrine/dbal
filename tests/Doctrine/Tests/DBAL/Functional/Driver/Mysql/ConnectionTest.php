<?php
namespace Doctrine\Tests\DBAL\Functional\Driver\Mysql;

use Doctrine\DBAL\Driver\PDOConnection;

class ConnectionTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function setUp()
    {
        parent::setUp();

        if ( !($this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\PDOMySql\Driver)) {
            $this->markTestSkipped('PDOMySql only test.');
        }
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function testPing()
    {
        /** @var PDOConnection $pdo */
        $pdo = $this->_conn->getWrappedConnection();

        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 1);
        $this->_conn->query("SET interactive_timeout = 1");
        $this->_conn->query("SET wait_timeout = 1");

        set_error_handler(function ($severity, $message) use (&$warning) {
            // does nothing, to demonstrate that ping works badly
            // if you remove the error handler from ping, the warning will fall here and the test will fail
            $warning = new \ErrorException($message, 0, $severity);
        });

        $this->assertTrue($this->_conn->ping());

        sleep(2);
        $this->assertFalse($this->_conn->ping());
        restore_error_handler(); // restore global error handler

        $this->_conn->close(); // reset timeouts

        $this->assertNull($warning);
    }

    public function testSimulatePingException()
    {
        /** @var PDOConnection $pdo */
        $pdo = $this->_conn->getWrappedConnection();

        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 1);
        $this->_conn->query("SET interactive_timeout = 1");
        $this->_conn->query("SET wait_timeout = 1");

        // convert warnings to errors
        set_error_handler(function ($severity, $message, $file = '', $line = 0, $context = array()) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        $this->assertTrue($this->_conn->ping());

        sleep(2);

        try {
            // simulate pinging
            $this->_conn->query($this->_conn->getDatabasePlatform()->getDummySelectSQL());
            $this->fail("Expected exception, because mysql connection should have timed out");

        } catch (\Exception $e) {
            // This is what actually happens when running ping() on PDOMySql without proper error handling
            // The error is catched thanks to error_handler
            $this->assertInstanceOf('Doctrine\\DBAL\\DBALException', $e);
            $this->assertInstanceOf('ErrorException', $e->getPrevious());
            $this->assertNull($e->getPrevious()->getPrevious());

            $this->assertSame('PDO::query(): MySQL server has gone away', $e->getPrevious()->getMessage());
        }

        restore_error_handler();

        $this->_conn->close(); // reset timeouts
    }
}

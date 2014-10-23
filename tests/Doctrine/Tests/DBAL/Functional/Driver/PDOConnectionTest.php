<?php

namespace Doctrine\Tests\DBAL\Functional\Driver;

use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\Tests\DbalFunctionalTestCase;

class PDOConnectionTest extends DbalFunctionalTestCase
{
    /**
     * The PDO driver connection under test.
     *
     * @var \Doctrine\DBAL\Driver\PDOConnection
     */
    protected $driverConnection;

    protected function setUp()
    {
        if ( ! extension_loaded('PDO')) {
            $this->markTestSkipped('PDO is not installed.');
        }

        parent::setUp();

        $this->driverConnection = $this->_conn->getWrappedConnection();

        if ( ! $this->driverConnection instanceof PDOConnection) {
            $this->markTestSkipped('PDO connection only test.');
        }
    }

    public function testDoesNotRequireQueryForServerVersion()
    {
        $this->assertFalse($this->driverConnection->requiresQueryForServerVersion());
    }

    /**
     * @expectedException \Doctrine\DBAL\Driver\PDOException
     */
    public function testThrowsWrappedExceptionOnConstruct()
    {
        new PDOConnection('foo');
    }

    /**
     * @group DBAL-1022
     *
     * @expectedException \Doctrine\DBAL\Driver\PDOException
     */
    public function testThrowsWrappedExceptionOnExec()
    {
        $this->driverConnection->exec('foo');
    }

    /**
     * @expectedException \Doctrine\DBAL\Driver\PDOException
     */
    public function testThrowsWrappedExceptionOnPrepare()
    {
        // Emulated prepared statements have to be disabled for this test
        // so that PDO actually communicates with the database server to check the query.
        $this->driverConnection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

        $this->driverConnection->prepare('foo');

        // Some PDO adapters like PostgreSQL do not check the query server-side
        // even though emulated prepared statements are disabled,
        // so an exception is thrown only eventually.
        // Skip the test otherwise.
        $this->markTestSkipped(
            sprintf(
                'The PDO adapter %s does not check the query to be prepared server-side, ' .
                'so no assertions can be made.',
                $this->_conn->getDriver()->getName()
            )
        );
    }

    /**
     * @expectedException \Doctrine\DBAL\Driver\PDOException
     */
    public function testThrowsWrappedExceptionOnQuery()
    {
        $this->driverConnection->query('foo');
    }
}

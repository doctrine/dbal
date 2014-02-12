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

        if ( ! $this->_conn->getWrappedConnection() instanceof PDOConnection) {
            $this->markTestSkipped('PDO connection only test.');
        }
    }

    public function testDoesNotRequireQueryForServerVersion()
    {
        $this->assertFalse($this->driverConnection->requiresQueryForServerVersion());
    }
}

<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\IBMDB2;

use Doctrine\DBAL\Driver\IBMDB2\Connection;
use Doctrine\DBAL\Driver\IBMDB2\Driver;
use Doctrine\DBAL\Driver\IBMDB2\Exception\ConnectionFailed;
use Doctrine\DBAL\Driver\IBMDB2\Exception\PrepareFailed;
use Doctrine\Tests\DbalFunctionalTestCase;
use ReflectionProperty;

use function db2_close;
use function extension_loaded;
use function get_parent_class;

class ConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('ibm_db2')) {
            $this->markTestSkipped('ibm_db2 is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('ibm_db2 only test.');
    }

    protected function tearDown(): void
    {
        $this->resetSharedConn();
    }

    public function testConnectionFailure(): void
    {
        $this->expectException(ConnectionFailed::class);
        new Connection(['dbname' => 'garbage'], '', '');
    }

    public function testPrepareFailure(): void
    {
        $driverConnection = $this->connection->getWrappedConnection();

        $re = new ReflectionProperty(get_parent_class($driverConnection), 'conn');
        $re->setAccessible(true);
        $conn = $re->getValue($driverConnection);
        db2_close($conn);

        $this->expectException(PrepareFailed::class);
        $driverConnection->prepare('SELECT 1');
    }
}

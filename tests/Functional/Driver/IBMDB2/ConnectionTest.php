<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\IBMDB2;

use Doctrine\DBAL\Driver\IBMDB2\DB2Connection;
use Doctrine\DBAL\Driver\IBMDB2\DB2Driver;
use Doctrine\DBAL\Driver\IBMDB2\Exception\ConnectionFailed;
use Doctrine\DBAL\Driver\IBMDB2\Exception\PrepareFailed;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use ReflectionProperty;

use function db2_close;
use function extension_loaded;

class ConnectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('ibm_db2')) {
            $this->markTestSkipped('ibm_db2 is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof DB2Driver) {
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
        new DB2Connection('garbage', false, '', '');
    }

    public function testPrepareFailure(): void
    {
        $driverConnection = $this->connection->getWrappedConnection();

        $re = new ReflectionProperty($driverConnection, 'conn');
        $re->setAccessible(true);
        $conn = $re->getValue($driverConnection);
        db2_close($conn);

        $this->expectException(PrepareFailed::class);
        $driverConnection->prepare('SELECT 1');
    }
}

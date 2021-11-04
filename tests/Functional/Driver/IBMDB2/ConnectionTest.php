<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\IBMDB2;

use Doctrine\DBAL\Driver\IBMDB2\Connection;
use Doctrine\DBAL\Driver\IBMDB2\Exception\ConnectionFailed;
use Doctrine\DBAL\Driver\IBMDB2\Exception\PrepareFailed;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use ReflectionProperty;

use function db2_close;

/**
 * @require extension ibm_db2
 */
class ConnectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('ibm_db2')) {
            return;
        }

        self::markTestSkipped('This test requires the ibm_db2 driver.');
    }

    protected function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    public function testConnectionFailure(): void
    {
        $this->expectException(ConnectionFailed::class);
        new Connection('garbage', false, '', '');
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

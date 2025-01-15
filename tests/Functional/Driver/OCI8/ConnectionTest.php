<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\OCI8;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Throwable;

class ConnectionTest extends FunctionalTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (TestUtil::isDriverOneOf('oci8')) {
            return;
        }

        self::markTestSkipped('This test requires the oci8 driver.');
    }

    public function testPrepareThrowsErrorOnConnectionLost(): void
    {
        $this->markConnectionNotReusable();
        $this->killCurrentSession();

        $this->expectException(DriverException::class);

        $this->connection->prepare('SELECT * FROM 1');
    }

    /**
     * Kill the current session, by using another connection
     * Oracle doesn't allow you to terminate the current session, so we use a second connection
     */
    private function killCurrentSession(): void
    {
        $row = $this->connection->fetchNumeric(
            <<<'SQL'
SELECT SID, SERIAL#
FROM V$SESSION
WHERE AUDSID = USERENV('SESSIONID')
SQL,
        );

        self::assertNotFalse($row);
        [$sid, $serialNumber] = $row;

        self::assertNotNull($sid, 'SID is missing.');
        self::assertNotNull($serialNumber, 'Serial number is missing.');

        $params                               = TestUtil::getConnectionParams();
        $params['driverOptions']['exclusive'] = true;
        $secondConnection                     = DriverManager::getConnection($params);

        $sessionParam = $this->connection->quote($sid . ', ' . $serialNumber);
        $secondConnection->executeStatement('ALTER SYSTEM DISCONNECT SESSION ' . $sessionParam . ' IMMEDIATE');

        // Ensure OCI driver is aware of connection state change by executing any statement
        try {
            $this->connection->executeStatement('INVALID SQL');
        } catch (Throwable) {
        }
    }
}

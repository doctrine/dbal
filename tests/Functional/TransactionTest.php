<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function sleep;

class TransactionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        $this->markTestSkipped('Restricted to MySQL.');
    }

    public function testCommitFalse(): void
    {
        $this->connection->executeStatement('SET SESSION wait_timeout=1');

        self::assertTrue($this->connection->beginTransaction());

        sleep(2); // during the sleep mysql will close the connection

        try {
            @$this->connection->commit(); // we will ignore `Packets out of order.` error
        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            self::assertInstanceOf(DriverException::class, $e);

            /* For PDO, we are using ERRMODE EXCEPTION, so this catch should be
             * necessary as the equivalent of the error control operator above.
             * This seems to be the case only since PHP 8 */
        } finally {
            $this->connection->close();
        }
    }
}

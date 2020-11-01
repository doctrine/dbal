<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\Tests\DbalFunctionalTestCase;
use PDOException;

use function sleep;

class TransactionTest extends DbalFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDatabasePlatform() instanceof MySqlPlatform) {
            return;
        }

        $this->markTestSkipped('Restricted to MySQL.');
    }

    protected function tearDown(): void
    {
        $this->resetSharedConn();

        parent::tearDown();
    }

    public function testCommitFalse(): void
    {
        $this->connection->query('SET SESSION wait_timeout=1');

        $this->assertTrue($this->connection->beginTransaction());

        sleep(2); // during the sleep mysql will close the connection

        try {
            $this->assertFalse(@$this->connection->commit()); // we will ignore `MySQL server has gone away` warnings
        } catch (PDOException $e) {
            /* For PDO, we are using ERRMODE EXCEPTION, so this catch should be
             * necessary as the equivalent of the error control operator above.
             * This seems to be the case only since PHP 8 */
        }
    }
}

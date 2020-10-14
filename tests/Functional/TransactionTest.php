<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function sleep;

class TransactionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDatabasePlatform() instanceof MySQLPlatform) {
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
        $this->connection->executeStatement('SET SESSION wait_timeout=1');

        self::assertTrue($this->connection->beginTransaction());

        sleep(2); // during the sleep mysql will close the connection

        self::assertFalse(@$this->connection->commit()); // we will ignore `MySQL server has gone away` warnings
    }
}

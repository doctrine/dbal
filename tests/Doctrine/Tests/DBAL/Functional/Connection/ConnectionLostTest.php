<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\Tests\DbalFunctionalTestCase;

use function sleep;

class ConnectionLostTest extends DbalFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDatabasePlatform() instanceof MySqlPlatform) {
            return;
        }

        $this->markTestSkipped('Currently only supported with MySQL');
    }

    public function testConnectionLost(): void
    {
        $this->connection->executeStatement('SET SESSION wait_timeout=1');

        sleep(2);

        $query = $this->connection->getDatabasePlatform()
            ->getDummySelectSQL();

        try {
            // in addition to the error, PHP 7.3 will generate a warning that needs to be
            // suppressed in order to not let PHPUnit handle it before the actual error
            @$this->connection->executeQuery($query);
        } catch (ConnectionLost $e) {
            self::assertEquals(1, $this->connection->fetchOne($query));

            return;
        }

        self::fail('The connection should have lost');
    }
}

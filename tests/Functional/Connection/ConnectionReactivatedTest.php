<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Connection;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function sleep;

class ConnectionReactivatedTest extends FunctionalTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::markConnectionWithHeartBeat();
    }

    protected function setUp(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        self::markTestSkipped('Currently only supported with MySQL');
    }

    public function testConnectionReactivated(): void
    {
        $this->connection->executeStatement('SET SESSION wait_timeout=1');

        sleep(2);

        $query = $this->connection->getDatabasePlatform()
            ->getDummySelectSQL();

        $this->connection->executeQuery($query);

        self::assertEquals(1, $this->connection->fetchOne($query));
    }
}

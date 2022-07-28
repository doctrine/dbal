<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Connection;

use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function sleep;

class ConnectionLostTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        self::markTestSkipped('Currently only supported with MySQL');
    }

    public function testConnectionLost(): void
    {
        $this->connection->executeStatement('SET SESSION wait_timeout=1');

        sleep(2);

        $query = $this->connection->getDatabasePlatform()
            ->getDummySelectSQL();

        try {
            // in addition to the error, MYSQLI_REPORT_OFF will generate a warning that needs to be
            // suppressed in order to not let PHPUnit handle it before the actual error
            @$this->connection->executeQuery($query);
        } catch (ConnectionLost) {
            self::assertEquals(1, $this->connection->fetchOne($query));

            return;
        }

        self::fail('The connection should have lost');
    }
}

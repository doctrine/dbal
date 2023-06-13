<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\SQLite;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;

/** @requires extension pdo_sqlite */
class DriverTest extends AbstractDriverTestCase
{
    use VerifyDeprecations;

    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_sqlite')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_sqlite driver.');
    }

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return 'main';
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }

    public function testRegisterCustomFunction(): void
    {
        $params                                          = $this->connection->getParams();
        $params['driverOptions']['userDefinedFunctions'] = [
            'my_add' => ['callback' => static fn (int $a, int $b): int => $a + $b, 'numArgs' => 2],
        ];

        $connection = new Connection(
            $params,
            $this->connection->getDriver(),
            $this->connection->getConfiguration(),
            $this->connection->getEventManager(),
        );

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/5742');

        self::assertSame(42, (int) $connection->fetchOne('SELECT my_add(20, 22)'));
    }
}

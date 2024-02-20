<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\SQLite3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\SQLite3\Driver;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('sqlite3')]
class DriverTest extends AbstractDriverTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('sqlite3')) {
            return;
        }

        self::markTestSkipped('This test requires the sqlite3 driver.');
    }

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return 'main';
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }

    public function testMissingMandatoryParams(): void
    {
        $params = $this->connection->getParams();
        unset($params['path'], $params['memory']);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage(
            'An exception occurred in the driver: '
                . 'Invalid connection settings: specify either the "path" or the "memory" parameter for SQLite3.',
        );

        $connection = new Connection(
            $params,
            $this->connection->getDriver(),
            $this->connection->getConfiguration(),
        );

        $connection->fetchOne('SELECT 1');
    }

    public function testAmbiguousParams(): void
    {
        $params           = $this->connection->getParams();
        $params['path']   = __DIR__ . '/dont-create-me.db';
        $params['memory'] = true;

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage(
            'An exception occurred in the driver: '
                . 'Invalid connection settings: specifying both parameters "path" and "memory" is ambiguous.',
        );

        $connection = new Connection(
            $params,
            $this->connection->getDriver(),
            $this->connection->getConfiguration(),
        );

        $connection->fetchOne('SELECT 1');
    }
}

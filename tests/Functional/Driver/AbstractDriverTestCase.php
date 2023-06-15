<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use PHPUnit\Framework\Constraint\IsType;

abstract class AbstractDriverTestCase extends FunctionalTestCase
{
    /**
     * The driver instance under test.
     */
    protected Driver $driver;

    protected function setUp(): void
    {
        $this->driver = $this->createDriver();
    }

    public function testConnectsWithoutDatabaseNameParameter(): void
    {
        $params = $this->connection->getParams();
        unset($params['dbname']);

        $this->expectNotToPerformAssertions();
        $this->driver->connect($params);
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        $params = $this->connection->getParams();
        unset($params['dbname']);

        $connection = new Connection(
            $params,
            $this->connection->getDriver(),
            $this->connection->getConfiguration(),
        );

        self::assertSame(
            static::getDatabaseNameForConnectionWithoutDatabaseNameParameter(),
            $connection->getDatabase(),
        );
    }

    public function testProvidesAccessToTheNativeConnection(): void
    {
        $nativeConnection = $this->connection->getNativeConnection();

        self::assertThat($nativeConnection, self::logicalOr(
            new IsType(IsType::TYPE_OBJECT),
            new IsType(IsType::TYPE_RESOURCE),
        ));
    }

    abstract protected function createDriver(): Driver;

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return null;
    }
}

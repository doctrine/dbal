<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Tests\FunctionalTestCase;

abstract class AbstractDriverTest extends FunctionalTestCase
{
    /**
     * The driver instance under test.
     *
     * @var Driver
     */
    protected $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = $this->createDriver();
    }

    /**
     * @group DBAL-1215
     */
    public function testConnectsWithoutDatabaseNameParameter(): void
    {
        $params = $this->connection->getParams();
        unset($params['dbname']);

        $this->expectNotToPerformAssertions();
        $this->driver->connect($params);
    }

    /**
     * @group DBAL-1215
     */
    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        $params = $this->connection->getParams();
        unset($params['dbname']);

        $connection = new Connection(
            $params,
            $this->connection->getDriver(),
            $this->connection->getConfiguration(),
            $this->connection->getEventManager()
        );

        self::assertSame(
            static::getDatabaseNameForConnectionWithoutDatabaseNameParameter(),
            $connection->getDatabase()
        );
    }

    abstract protected function createDriver(): Driver;

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return null;
    }
}

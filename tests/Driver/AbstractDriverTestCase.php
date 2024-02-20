<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** @template P of AbstractPlatform */
abstract class AbstractDriverTestCase extends TestCase
{
    /**
     * The driver mock under test.
     */
    protected Driver $driver;

    protected function setUp(): void
    {
        $this->driver = $this->createDriver();
    }

    public function testReturnsExceptionConverter(): void
    {
        self::assertEquals($this->createExceptionConverter(), $this->driver->getExceptionConverter());
    }

    /**
     * Factory method for creating the driver instance under test.
     */
    abstract protected function createDriver(): Driver;

    /**
     * Factory method for creating the the platform instance return by the driver under test.
     *
     * The platform instance returned by this method must be the same as returned by
     * the driver's getDatabasePlatform() method.
     *
     * @return P
     */
    abstract protected function createPlatform(): AbstractPlatform;

    /**
     * Factory method for creating the the schema manager instance return by the driver under test.
     *
     * The schema manager instance returned by this method must be the same as returned by
     * the driver's getSchemaManager() method.
     *
     * @param Connection $connection The underlying connection to use.
     */
    abstract protected function createSchemaManager(Connection $connection): AbstractSchemaManager;

    abstract protected function createExceptionConverter(): ExceptionConverter;

    protected function getConnectionMock(): Connection&MockObject
    {
        return $this->createMock(Connection::class);
    }
}

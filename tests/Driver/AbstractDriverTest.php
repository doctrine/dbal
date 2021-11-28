<?php

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function get_class;
use function sprintf;

/**
 * @template P of AbstractPlatform
 */
abstract class AbstractDriverTest extends TestCase
{
    /**
     * The driver mock under test.
     *
     * @var Driver
     */
    protected $driver;

    protected function setUp(): void
    {
        $this->driver = $this->createDriver();
    }

    public function testCreatesDatabasePlatformForVersion(): void
    {
        if (! $this->driver instanceof VersionAwarePlatformDriver) {
            self::markTestSkipped('This test is only intended for version aware platform drivers.');
        }

        $data = $this->getDatabasePlatformsForVersions();

        self::assertNotEmpty(
            $data,
            sprintf(
                'No test data found for test %s. You have to return test data from %s.',
                static::class . '::' . __FUNCTION__,
                static::class . '::getDatabasePlatformsForVersions'
            )
        );

        foreach ($data as $item) {
            $generatedVersion = get_class($this->driver->createDatabasePlatformForVersion($item[0]));

            self::assertSame(
                $item[1],
                $generatedVersion,
                sprintf(
                    'Expected platform for version "%s" should be "%s", "%s" given',
                    $item[0],
                    $item[1],
                    $generatedVersion
                )
            );
        }
    }

    public function testThrowsExceptionOnCreatingDatabasePlatformsForInvalidVersion(): void
    {
        if (! $this->driver instanceof VersionAwarePlatformDriver) {
            self::markTestSkipped('This test is only intended for version aware platform drivers.');
        }

        $this->expectException(Exception::class);
        $this->driver->createDatabasePlatformForVersion('foo');
    }

    public function testReturnsDatabasePlatform(): void
    {
        self::assertEquals($this->createPlatform(), $this->driver->getDatabasePlatform());
    }

    public function testReturnsSchemaManager(): void
    {
        $connection    = $this->getConnectionMock();
        $schemaManager = $this->driver->getSchemaManager(
            $connection,
            $this->createPlatform()
        );

        self::assertEquals($this->createSchemaManager($connection), $schemaManager);

        $re = new ReflectionProperty($schemaManager, '_conn');
        $re->setAccessible(true);

        self::assertSame($connection, $re->getValue($schemaManager));
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

    /**
     * @return Connection&MockObject
     */
    protected function getConnectionMock(): Connection
    {
        return $this->createMock(Connection::class);
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function getDatabasePlatformsForVersions(): array
    {
        return [];
    }
}

<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\DriverException as DriverExceptionInterface;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\DBAL\Exception\DatabaseObjectExistsException;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\ReadOnlyException;
use Doctrine\DBAL\Exception\ServerException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionProperty;
use function array_merge;
use function get_class;
use function sprintf;

abstract class AbstractDriverTest extends DbalTestCase
{
    public const EXCEPTION_CONNECTION                       = ConnectionException::class;
    public const EXCEPTION_CONSTRAINT_VIOLATION             = ConstraintViolationException::class;
    public const EXCEPTION_DATABASE_OBJECT_EXISTS           = DatabaseObjectExistsException::class;
    public const EXCEPTION_DATABASE_OBJECT_NOT_FOUND        = DatabaseObjectNotFoundException::class;
    public const EXCEPTION_DRIVER                           = DriverException::class;
    public const EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION = ForeignKeyConstraintViolationException::class;
    public const EXCEPTION_INVALID_FIELD_NAME               = InvalidFieldNameException::class;
    public const EXCEPTION_NON_UNIQUE_FIELD_NAME            = NonUniqueFieldNameException::class;
    public const EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION    = NotNullConstraintViolationException::class;
    public const EXCEPTION_READ_ONLY                        = ReadOnlyException::class;
    public const EXCEPTION_SERVER                           = ServerException::class;
    public const EXCEPTION_SYNTAX_ERROR                     = SyntaxErrorException::class;
    public const EXCEPTION_TABLE_EXISTS                     = TableExistsException::class;
    public const EXCEPTION_TABLE_NOT_FOUND                  = TableNotFoundException::class;
    public const EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION      = UniqueConstraintViolationException::class;
    public const EXCEPTION_DEADLOCK                         = DeadlockException::class;
    public const EXCEPTION_LOCK_WAIT_TIMEOUT                = LockWaitTimeoutException::class;

    /**
     * The driver mock under test.
     *
     * @var Driver
     */
    protected $driver;

    protected function setUp() : void
    {
        parent::setUp();

        $this->driver = $this->createDriver();
    }

    /**
     * @param int|string $errorCode
     *
     * @dataProvider exceptionConversionProvider
     */
    public function testConvertsException($errorCode, ?string $sqlState, ?string $message, string $expectedClass) : void
    {
        if (! $this->driver instanceof ExceptionConverterDriver) {
            $this->markTestSkipped('This test is only intended for exception converter drivers.');
        }

        /** @var DriverExceptionInterface|MockObject $driverException */
        $driverException = $this->getMockBuilder(DriverExceptionInterface::class)
            ->setConstructorArgs([$message])
            ->getMock();
        $driverException->method('getErrorCode')
            ->willReturn($errorCode);
        $driverException->method('getSQLState')
            ->willReturn($sqlState);

        $dbalMessage   = 'DBAL exception message';
        $dbalException = $this->driver->convertException($dbalMessage, $driverException);

        self::assertInstanceOf($expectedClass, $dbalException);

        self::assertSame($driverException->getErrorCode(), $dbalException->getErrorCode());
        self::assertSame($driverException->getSQLState(), $dbalException->getSQLState());
        self::assertSame($driverException, $dbalException->getPrevious());
        self::assertSame($dbalMessage, $dbalException->getMessage());
    }

    public function testCreatesDatabasePlatformForVersion() : void
    {
        if (! $this->driver instanceof VersionAwarePlatformDriver) {
            $this->markTestSkipped('This test is only intended for version aware platform drivers.');
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

    public function testThrowsExceptionOnCreatingDatabasePlatformsForInvalidVersion() : void
    {
        if (! $this->driver instanceof VersionAwarePlatformDriver) {
            $this->markTestSkipped('This test is only intended for version aware platform drivers.');
        }

        $this->expectException(DBALException::class);
        $this->driver->createDatabasePlatformForVersion('foo');
    }

    public function testReturnsDatabaseName() : void
    {
        $params = [
            'user'     => 'foo',
            'password' => 'bar',
            'dbname'   => 'baz',
        ];

        $connection = $this->getConnectionMock();

        $connection->expects($this->once())
            ->method('getParams')
            ->will($this->returnValue($params));

        self::assertSame($params['dbname'], $this->driver->getDatabase($connection));
    }

    public function testReturnsDatabasePlatform() : void
    {
        self::assertEquals($this->createPlatform(), $this->driver->getDatabasePlatform());
    }

    public function testReturnsSchemaManager() : void
    {
        $connection    = $this->getConnectionMock();
        $schemaManager = $this->driver->getSchemaManager($connection);

        self::assertEquals($this->createSchemaManager($connection), $schemaManager);

        $re = new ReflectionProperty($schemaManager, '_conn');
        $re->setAccessible(true);

        self::assertSame($connection, $re->getValue($schemaManager));
    }

    /**
     * Factory method for creating the driver instance under test.
     */
    abstract protected function createDriver() : Driver;

    /**
     * Factory method for creating the the platform instance return by the driver under test.
     *
     * The platform instance returned by this method must be the same as returned by
     * the driver's getDatabasePlatform() method.
     */
    abstract protected function createPlatform() : AbstractPlatform;

    /**
     * Factory method for creating the the schema manager instance return by the driver under test.
     *
     * The schema manager instance returned by this method must be the same as returned by
     * the driver's getSchemaManager() method.
     *
     * @param Connection $connection The underlying connection to use.
     */
    abstract protected function createSchemaManager(Connection $connection) : AbstractSchemaManager;

    protected function getConnectionMock() : Connection
    {
        return $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function getDatabasePlatformsForVersions() : array
    {
        return [];
    }

    /**
     * @return mixed[][]
     */
    public static function exceptionConversionProvider() : iterable
    {
        foreach (static::getExceptionConversionData() as $expectedClass => $items) {
            foreach ($items as $item) {
                yield array_merge($item, [$expectedClass]);
            }
        }

        yield ['foo', 'bar', 'baz', self::EXCEPTION_DRIVER];
    }

    /**
     * @return array<string,mixed[][]>
     */
    protected static function getExceptionConversionData() : array
    {
        return [];
    }
}

<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
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
use Exception;
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

    protected function setUp()
    {
        parent::setUp();

        $this->driver = $this->createDriver();
    }

    public function testConvertsException()
    {
        if (! $this->driver instanceof ExceptionConverterDriver) {
            $this->markTestSkipped('This test is only intended for exception converter drivers.');
        }

        $data = $this->getExceptionConversions();

        if (empty($data)) {
            $this->fail(
                sprintf(
                    'No test data found for test %s. You have to return test data from %s.',
                    static::class . '::' . __FUNCTION__,
                    static::class . '::getExceptionConversionData'
                )
            );
        }

        $driverException = new class extends Exception implements DriverExceptionInterface
        {
            public function __construct()
            {
                parent::__construct('baz');
            }

            /**
             * {@inheritDoc}
             */
            public function getErrorCode()
            {
                return 'foo';
            }

            /**
             * {@inheritDoc}
             */
            public function getSQLState()
            {
                return 'bar';
            }
        };

        $data[] = [$driverException, self::EXCEPTION_DRIVER];

        $message = 'DBAL exception message';

        foreach ($data as $item) {
            /** @var $driverException \Doctrine\DBAL\Driver\DriverException */
            [$driverException, $convertedExceptionClassName] = $item;

            $convertedException = $this->driver->convertException($message, $driverException);

            self::assertSame($convertedExceptionClassName, get_class($convertedException));

            self::assertSame($driverException->getErrorCode(), $convertedException->getErrorCode());
            self::assertSame($driverException->getSQLState(), $convertedException->getSQLState());
            self::assertSame($message, $convertedException->getMessage());
        }
    }

    public function testCreatesDatabasePlatformForVersion()
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

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testThrowsExceptionOnCreatingDatabasePlatformsForInvalidVersion()
    {
        if (! $this->driver instanceof VersionAwarePlatformDriver) {
            $this->markTestSkipped('This test is only intended for version aware platform drivers.');
        }

        $this->driver->createDatabasePlatformForVersion('foo');
    }

    public function testReturnsDatabaseName()
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

    public function testReturnsDatabasePlatform()
    {
        self::assertEquals($this->createPlatform(), $this->driver->getDatabasePlatform());
    }

    public function testReturnsSchemaManager()
    {
        $connection    = $this->getConnectionMock();
        $schemaManager = $this->driver->getSchemaManager($connection);

        self::assertEquals($this->createSchemaManager($connection), $schemaManager);
        self::assertAttributeSame($connection, '_conn', $schemaManager);
    }

    /**
     * Factory method for creating the driver instance under test.
     *
     * @return Driver
     */
    abstract protected function createDriver();

    /**
     * Factory method for creating the the platform instance return by the driver under test.
     *
     * The platform instance returned by this method must be the same as returned by
     * the driver's getDatabasePlatform() method.
     *
     * @return AbstractPlatform
     */
    abstract protected function createPlatform();

    /**
     * Factory method for creating the the schema manager instance return by the driver under test.
     *
     * The schema manager instance returned by this method must be the same as returned by
     * the driver's getSchemaManager() method.
     *
     * @param Connection $connection The underlying connection to use.
     *
     * @return AbstractSchemaManager
     */
    abstract protected function createSchemaManager(Connection $connection);

    protected function getConnectionMock()
    {
        return $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function getDatabasePlatformsForVersions()
    {
        return [];
    }

    protected function getExceptionConversionData()
    {
        return [];
    }

    private function getExceptionConversions()
    {
        $data = [];

        foreach ($this->getExceptionConversionData() as $convertedExceptionClassName => $errors) {
            foreach ($errors as $error) {
                $driverException = new class ($error[0], $error[1], $error[2])
                    extends Exception
                    implements DriverExceptionInterface
                {
                    /** @var mixed */
                    private $errorCode;

                    /** @var mixed */
                    private $sqlState;

                    public function __construct($errorCode, $sqlState, $message)
                    {
                        parent::__construct($message);

                        $this->errorCode = $errorCode;
                        $this->sqlState  = $sqlState;
                    }

                    /**
                     * {@inheritDoc}
                     */
                    public function getErrorCode()
                    {
                        return $this->errorCode;
                    }

                    /**
                     * {@inheritDoc}
                     */
                    public function getSQLState()
                    {
                        return $this->sqlState;
                    }
                };

                $data[] = [$driverException, $convertedExceptionClassName];
            }
        }

        return $data;
    }
}

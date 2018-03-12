<?php

namespace Doctrine\Tests\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Doctrine\Tests\DbalTestCase;

abstract class AbstractDriverTest extends DbalTestCase
{
    const EXCEPTION_CONNECTION = 'Doctrine\DBAL\Exception\ConnectionException';
    const EXCEPTION_CONSTRAINT_VIOLATION = 'Doctrine\DBAL\Exception\ConstraintViolationException';
    const EXCEPTION_DATABASE_OBJECT_EXISTS = 'Doctrine\DBAL\Exception\DatabaseObjectExistsException';
    const EXCEPTION_DATABASE_OBJECT_NOT_FOUND = 'Doctrine\DBAL\Exception\DatabaseObjectNotFoundException';
    const EXCEPTION_DRIVER = 'Doctrine\DBAL\Exception\DriverException';
    const EXCEPTION_FOREIGN_KEY_CONSTRAINT_VIOLATION = 'Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException';
    const EXCEPTION_INVALID_FIELD_NAME = 'Doctrine\DBAL\Exception\InvalidFieldNameException';
    const EXCEPTION_NON_UNIQUE_FIELD_NAME = 'Doctrine\DBAL\Exception\NonUniqueFieldNameException';
    const EXCEPTION_NOT_NULL_CONSTRAINT_VIOLATION = 'Doctrine\DBAL\Exception\NotNullConstraintViolationException';
    const EXCEPTION_READ_ONLY = 'Doctrine\DBAL\Exception\ReadOnlyException';
    const EXCEPTION_SERVER = 'Doctrine\DBAL\Exception\ServerException';
    const EXCEPTION_SYNTAX_ERROR = 'Doctrine\DBAL\Exception\SyntaxErrorException';
    const EXCEPTION_TABLE_EXISTS = 'Doctrine\DBAL\Exception\TableExistsException';
    const EXCEPTION_TABLE_NOT_FOUND = 'Doctrine\DBAL\Exception\TableNotFoundException';
    const EXCEPTION_UNIQUE_CONSTRAINT_VIOLATION = 'Doctrine\DBAL\Exception\UniqueConstraintViolationException';
    const EXCEPTION_DEADLOCK = 'Doctrine\DBAL\Exception\DeadlockException';
    const EXCEPTION_LOCK_WAIT_TIMEOUT = 'Doctrine\DBAL\Exception\LockWaitTimeoutException';

    /**
     * The driver mock under test.
     *
     * @var \Doctrine\DBAL\Driver
     */
    protected $driver;

    protected function setUp()
    {
        parent::setUp();

        $this->driver = $this->createDriver();
    }

    public function testConvertsException()
    {
        if ( ! $this->driver instanceof ExceptionConverterDriver) {
            $this->markTestSkipped('This test is only intended for exception converter drivers.');
        }

        $data = $this->getExceptionConversions();

        if (empty($data)) {
            $this->fail(
                sprintf(
                    'No test data found for test %s. You have to return test data from %s.',
                    get_class($this) . '::' . __FUNCTION__,
                    get_class($this) . '::getExceptionConversionData'
                )
            );
        }

        $driverException = new class extends \Exception implements DriverException
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

        $data[] = array($driverException, self::EXCEPTION_DRIVER);

        $message = 'DBAL exception message';

        foreach ($data as $item) {
            /** @var $driverException \Doctrine\DBAL\Driver\DriverException */
            list($driverException, $convertedExceptionClassName) = $item;

            $convertedException = $this->driver->convertException($message, $driverException);

            self::assertSame($convertedExceptionClassName, get_class($convertedException));

            self::assertSame($driverException->getErrorCode(), $convertedException->getErrorCode());
            self::assertSame($driverException->getSQLState(), $convertedException->getSQLState());
            self::assertSame($message, $convertedException->getMessage());
        }
    }

    public function testCreatesDatabasePlatformForVersion()
    {
        if ( ! $this->driver instanceof VersionAwarePlatformDriver) {
            $this->markTestSkipped('This test is only intended for version aware platform drivers.');
        }

        $data = $this->getDatabasePlatformsForVersions();

        self::assertNotEmpty(
            $data,
            sprintf(
                'No test data found for test %s. You have to return test data from %s.',
                get_class($this) . '::' . __FUNCTION__,
                get_class($this) . '::getDatabasePlatformsForVersions'
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
        if ( ! $this->driver instanceof VersionAwarePlatformDriver) {
            $this->markTestSkipped('This test is only intended for version aware platform drivers.');
        }

        $this->driver->createDatabasePlatformForVersion('foo');
    }

    public function testReturnsDatabaseName()
    {
        $params = array(
            'user'     => 'foo',
            'password' => 'bar',
            'dbname'   => 'baz',
        );

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
     * @return \Doctrine\DBAL\Driver
     */
    abstract protected function createDriver();

    /**
     * Factory method for creating the the platform instance return by the driver under test.
     *
     * The platform instance returned by this method must be the same as returned by
     * the driver's getDatabasePlatform() method.
     *
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform
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
     * @return \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    abstract protected function createSchemaManager(Connection $connection);

    protected function getConnectionMock()
    {
        return $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function getDatabasePlatformsForVersions()
    {
        return array();
    }

    protected function getExceptionConversionData()
    {
        return array();
    }

    private function getExceptionConversions()
    {
        $data = array();

        foreach ($this->getExceptionConversionData() as $convertedExceptionClassName => $errors) {
            foreach ($errors as $error) {
                $driverException = new class ($error[0], $error[1], $error[2])
                    extends \Exception
                    implements DriverException
                {
                    /**
                     * @var mixed
                     */
                    private $errorCode;

                    /**
                     * @var mixed
                     */
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

                $data[] = array($driverException, $convertedExceptionClassName);
            }
        }

        return $data;
    }
}

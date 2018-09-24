<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\DriverException as InnerDriverException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\Tests\DbalTestCase;
use Exception;
use stdClass;
use function chr;
use function fopen;
use function sprintf;

class DBALExceptionTest extends DbalTestCase
{
    public function testDriverExceptionDuringQueryAcceptsBinaryData()
    {
        /** @var Driver $driver */
        $driver = $this->createMock(Driver::class);
        $e      = DBALException::driverExceptionDuringQuery($driver, new Exception(), '', ['ABC', chr(128)]);
        self::assertContains('with params ["ABC", "\x80"]', $e->getMessage());
    }

    public function testDriverExceptionDuringQueryAcceptsResource()
    {
        /** @var Driver $driver */
        $driver = $this->createMock(Driver::class);
        $e      = DBALException::driverExceptionDuringQuery($driver, new Exception(), 'INSERT INTO file (`content`) VALUES (?)', [1 => fopen(__FILE__, 'r')]);
        self::assertContains('Resource', $e->getMessage());
    }

    public function testAvoidOverWrappingOnDriverException()
    {
        /** @var Driver $driver */
        $driver = $this->createMock(Driver::class);
        $inner  = new class extends Exception implements InnerDriverException
        {
            /**
             * {@inheritDoc}
             */
            public function getErrorCode()
            {
            }

            /**
             * {@inheritDoc}
             */
            public function getSQLState()
            {
            }
        };
        $ex     = new DriverException('', $inner);
        $e      = DBALException::driverExceptionDuringQuery($driver, $ex, '');
        self::assertSame($ex, $e);
    }

    public function testDriverRequiredWithUrl()
    {
        $url       = 'mysql://localhost';
        $exception = DBALException::driverRequired($url);

        self::assertInstanceOf(DBALException::class, $exception);
        self::assertSame(
            sprintf(
                "The options 'driver' or 'driverClass' are mandatory if a connection URL without scheme " .
                'is given to DriverManager::getConnection(). Given URL: %s',
                $url
            ),
            $exception->getMessage()
        );
    }

    /**
     * @group #2821
     */
    public function testInvalidPlatformTypeObject() : void
    {
        $exception = DBALException::invalidPlatformType(new stdClass());

        self::assertSame(
            "Option 'platform' must be a subtype of 'Doctrine\DBAL\Platforms\AbstractPlatform', instance of 'stdClass' given",
            $exception->getMessage()
        );
    }

    /**
     * @group #2821
     */
    public function testInvalidPlatformTypeScalar() : void
    {
        $exception = DBALException::invalidPlatformType('some string');

        self::assertSame(
            "Option 'platform' must be an object and subtype of 'Doctrine\DBAL\Platforms\AbstractPlatform'. Got 'string'",
            $exception->getMessage()
        );
    }
}

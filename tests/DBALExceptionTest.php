<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\DriverException as InnerDriverException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\DriverRequired;
use Doctrine\DBAL\Exception\InvalidPlatformType;
use Exception;
use PHPUnit\Framework\TestCase;
use stdClass;

use function chr;
use function fopen;
use function sprintf;

class DBALExceptionTest extends TestCase
{
    public function testDriverExceptionDuringQueryAcceptsBinaryData(): void
    {
        $driver = $this->createMock(Driver::class);
        $e      = DBALException::driverExceptionDuringQuery($driver, new Exception(), '', ['ABC', chr(128)]);
        self::assertStringContainsString('with params ["ABC", "\x80"]', $e->getMessage());
    }

    public function testDriverExceptionDuringQueryAcceptsResource(): void
    {
        $driver = $this->createMock(Driver::class);
        $e      = DBALException::driverExceptionDuringQuery($driver, new Exception(), 'INSERT INTO file (`content`) VALUES (?)', [1 => fopen(__FILE__, 'r')]);
        self::assertStringContainsString('Resource', $e->getMessage());
    }

    public function testAvoidOverWrappingOnDriverException(): void
    {
        $driver = $this->createMock(Driver::class);

        $inner = $this->createMock(InnerDriverException::class);

        $ex = new DriverException('', $inner);
        $e  = DBALException::driverExceptionDuringQuery($driver, $ex, '');
        self::assertSame($ex, $e);
    }

    public function testDriverRequiredWithUrl(): void
    {
        $url       = 'mysql://localhost';
        $exception = DriverRequired::new($url);

        self::assertSame(
            sprintf(
                'The options "driver" or "driverClass" are mandatory if a connection URL without scheme ' .
                'is given to DriverManager::getConnection(). Given URL "%s".',
                $url
            ),
            $exception->getMessage()
        );
    }

    /**
     * @group #2821
     */
    public function testInvalidPlatformTypeObject(): void
    {
        $exception = InvalidPlatformType::new(new stdClass());

        self::assertSame(
            'Option "platform" must be a subtype of Doctrine\DBAL\Platforms\AbstractPlatform, instance of stdClass given.',
            $exception->getMessage()
        );
    }

    /**
     * @group #2821
     */
    public function testInvalidPlatformTypeScalar(): void
    {
        $exception = InvalidPlatformType::new('some string');

        self::assertSame(
            'Option "platform" must be an object and subtype of Doctrine\DBAL\Platforms\AbstractPlatform. Got string.',
            $exception->getMessage()
        );
    }
}

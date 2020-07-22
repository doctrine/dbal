<?php

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\TestCase;
use stdClass;

use function sprintf;

class DBALExceptionTest extends TestCase
{
    public function testDriverRequiredWithUrl(): void
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

    public function testInvalidPlatformTypeObject(): void
    {
        $exception = DBALException::invalidPlatformType(new stdClass());

        self::assertSame(
            "Option 'platform' must be a subtype of 'Doctrine\DBAL\Platforms\AbstractPlatform', instance of 'stdClass' given",
            $exception->getMessage()
        );
    }

    public function testInvalidPlatformTypeScalar(): void
    {
        $exception = DBALException::invalidPlatformType('some string');

        self::assertSame(
            "Option 'platform' must be an object and subtype of 'Doctrine\DBAL\Platforms\AbstractPlatform'. Got 'string'",
            $exception->getMessage()
        );
    }
}

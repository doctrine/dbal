<?php

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use stdClass;

use function sprintf;

class ExceptionTest extends TestCase
{
    public function testDriverRequiredWithUrl(): void
    {
        $url       = 'mysql://localhost';
        $exception = Exception::driverRequired($url);

        self::assertSame(
            sprintf(
                "The options 'driver' or 'driverClass' are mandatory if a connection URL without scheme " .
                'is given to DriverManager::getConnection(). Given URL: %s',
                $url,
            ),
            $exception->getMessage(),
        );
    }

    public function testInvalidPlatformTypeObject(): void
    {
        $exception = Exception::invalidPlatformType(new stdClass());

        self::assertSame(
            "Option 'platform' must be a subtype of 'Doctrine\DBAL\Platforms\AbstractPlatform', "
                . "instance of 'stdClass' given",
            $exception->getMessage(),
        );
    }

    public function testInvalidPlatformTypeScalar(): void
    {
        $exception = Exception::invalidPlatformType('some string');

        self::assertSame(
            "Option 'platform' must be an object and subtype of 'Doctrine\DBAL\Platforms\AbstractPlatform'. "
                . "Got 'string'",
            $exception->getMessage(),
        );
    }
}

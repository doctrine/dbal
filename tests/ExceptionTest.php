<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Exception\DriverRequired;
use Doctrine\DBAL\Exception\InvalidPlatformType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;
use stdClass;

use function sprintf;

class ExceptionTest extends TestCase
{
    public function testDriverRequiredWithUrl(): void
    {
        $url       = 'mysql://localhost';
        $exception = DriverRequired::new($url);

        self::assertSame(
            sprintf(
                'The options "driver" or "driverClass" are mandatory if a connection URL without scheme ' .
                'is given to DriverManager::getConnection(). Given URL "%s".',
                $url,
            ),
            $exception->getMessage(),
        );
    }

    public function testInvalidPlatformTypeObject(): void
    {
        $exception = InvalidPlatformType::new(new stdClass());

        self::assertSame(
            'Option "platform" must be a subtype of Doctrine\DBAL\Platforms\AbstractPlatform, '
                . 'instance of stdClass given.',
            $exception->getMessage(),
        );
    }

    public function testInvalidPlatformTypeScalar(): void
    {
        $exception = InvalidPlatformType::new('some string');

        self::assertSame(
            'Option "platform" must be an object and subtype of ' . AbstractPlatform::class . '. Got string.',
            $exception->getMessage(),
        );
    }
}

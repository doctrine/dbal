<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Types\Exception;

use Doctrine\DBAL\Types\Exception\SerializationFailed;
use PHPUnit\Framework\TestCase;
use function json_encode;
use function json_last_error_msg;
use const NAN;

class SerializationFailedTest extends TestCase
{
    public function testNew() : void
    {
        $value   = NAN;
        $encoded = json_encode($value);

        $exception = SerializationFailed::new($value, 'json', json_last_error_msg());

        self::assertSame(
            'Could not convert PHP type "double" to "json". An error was triggered by the serialization: Inf and NaN cannot be JSON encoded',
            $exception->getMessage()
        );
    }
}

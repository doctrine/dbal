<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Mysqli\Exception\UnknownType;
use PHPUnit\Framework\TestCase;

final class UnknownTypeTest extends TestCase
{
    public function testNew(): void
    {
        $exception = UnknownType::new('9999');

        self::assertSame('Unknown type, 9999 given.', $exception->getMessage());
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types\Exception;

use Doctrine\DBAL\Types\Exception\UnknownColumnType;
use PHPUnit\Framework\TestCase;

class UnknownColumnTypeTest extends TestCase
{
    public function testNew(): void
    {
        $exception = UnknownColumnType::new('custom_type');

        self::assertSame('custom_type', $exception->getRequestedType());
        self::assertStringContainsString(
            'Unknown column type "custom_type" requested.',
            $exception->getMessage(),
        );
    }

    public function testWithContext(): void
    {
        $exception = UnknownColumnType::newWithContext('custom_type', 'some_table');

        self::assertSame('custom_type', $exception->getRequestedType());
        self::assertStringContainsString(
            'Unknown column type "custom_type" requested for table "some_table".',
            $exception->getMessage(),
        );
    }
}

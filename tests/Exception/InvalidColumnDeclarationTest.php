<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Exception;

use Doctrine\DBAL\Exception\InvalidColumnDeclaration;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnPrecisionRequired;
use PHPUnit\Framework\TestCase;

class InvalidColumnDeclarationTest extends TestCase
{
    public function testFromInvalidColumnTypeWithColumnName(): void
    {
        $exception = InvalidColumnDeclaration::fromInvalidColumnType('price', ColumnPrecisionRequired::new());

        self::assertSame('Column "price" has invalid type', $exception->getMessage());
    }

    public function testFromInvalidColumnTypeWithoutColumnName(): void
    {
        $exception = InvalidColumnDeclaration::fromInvalidColumnType(null, ColumnPrecisionRequired::new());

        self::assertSame('Column "<unnamed>" has invalid type', $exception->getMessage());
    }

    public function testFromInvalidColumnTypeWithEmptyColumnName(): void
    {
        $exception = InvalidColumnDeclaration::fromInvalidColumnType('', ColumnPrecisionRequired::new());

        self::assertSame('Column "<unnamed>" has invalid type', $exception->getMessage());
    }
}

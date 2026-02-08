<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Exception;

use Doctrine\DBAL\Exception\InvalidColumnDeclaration;
use Doctrine\DBAL\Exception\InvalidColumnType;
use PHPUnit\Framework\TestCase;

final class InvalidColumnDeclarationTest extends TestCase
{
    public function testFromInvalidColumnTypeWithName(): void
    {
        $previous = $this->createMock(InvalidColumnType::class);
        $exception = InvalidColumnDeclaration::fromInvalidColumnType('my_column', $previous);

        self::assertSame('Column "my_column" has invalid type', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testFromInvalidColumnTypeWithNullName(): void
    {
        $previous = $this->createMock(InvalidColumnType::class);
        $exception = InvalidColumnDeclaration::fromInvalidColumnType(null, $previous);

        self::assertSame('Column has invalid type', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }
}

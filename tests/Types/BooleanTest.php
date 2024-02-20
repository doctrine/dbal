<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BooleanType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BooleanTest extends TestCase
{
    private AbstractPlatform&MockObject $platform;
    private BooleanType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new BooleanType();
    }

    public function testBooleanConvertsToDatabaseValue(): void
    {
        $this->platform->expects(self::once())
            ->method('convertBooleansToDatabaseValue')
            ->with(true)
            ->willReturn(1);

        self::assertSame(1, $this->type->convertToDatabaseValue(true, $this->platform));
    }

    public function testBooleanConvertsToPHPValue(): void
    {
        $this->platform->expects(self::once())
            ->method('convertFromBoolean')
            ->with(0)
            ->willReturn(false);

        self::assertFalse($this->type->convertToPHPValue(0, $this->platform));
    }

    public function testBooleanNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

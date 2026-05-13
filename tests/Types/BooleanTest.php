<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BooleanType;
use Override;
use PHPUnit\Framework\TestCase;

class BooleanTest extends TestCase
{
    private BooleanType $type;

    #[Override]
    protected function setUp(): void
    {
        $this->type = new BooleanType();
    }

    public function testBooleanConvertsToDatabaseValue(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects(self::once())
            ->method('convertBooleansToDatabaseValue')
            ->with(true)
            ->willReturn(1);

        self::assertSame(1, $this->type->convertToDatabaseValue(true, $platform));
    }

    public function testBooleanConvertsToPHPValue(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects(self::once())
            ->method('convertFromBoolean')
            ->with(0)
            ->willReturn(false);

        self::assertFalse($this->type->convertToPHPValue(0, $platform));
    }

    public function testBooleanNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, self::createStub(AbstractPlatform::class)));
    }
}

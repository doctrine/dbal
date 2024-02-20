<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\FloatType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FloatTest extends TestCase
{
    private AbstractPlatform&MockObject $platform;
    private FloatType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new FloatType();
    }

    public function testFloatConvertsToPHPValue(): void
    {
        self::assertIsFloat($this->type->convertToPHPValue('5.5', $this->platform));
    }

    public function testFloatNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testFloatConvertToDatabaseValue(): void
    {
        self::assertIsFloat($this->type->convertToDatabaseValue(5.5, $this->platform));
    }

    public function testFloatNullConvertToDatabaseValue(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }
}

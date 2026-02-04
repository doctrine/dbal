<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DecimalType;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    private AbstractPlatform&Stub $platform;
    private DecimalType $type;

    protected function setUp(): void
    {
        $this->platform = self::createStub(AbstractPlatform::class);
        $this->type     = new DecimalType();
    }

    public function testDecimalConvertsToPHPValue(): void
    {
        self::assertIsString($this->type->convertToPHPValue('5.5', $this->platform));
    }

    public function testDecimalNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

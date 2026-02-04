<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class GuidTypeTest extends TestCase
{
    private AbstractPlatform&Stub $platform;
    private GuidType $type;

    protected function setUp(): void
    {
        $this->platform = self::createStub(AbstractPlatform::class);
        $this->type     = new GuidType();
    }

    public function testConvertToPHPValue(): void
    {
        self::assertIsString($this->type->convertToPHPValue('foo', $this->platform));
        self::assertIsString($this->type->convertToPHPValue('', $this->platform));
    }

    public function testNullConversion(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

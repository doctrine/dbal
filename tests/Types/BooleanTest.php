<?php

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BooleanType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BooleanTest extends TestCase
{
    /** @var AbstractPlatform&MockObject */
    private AbstractPlatform $platform;

    private BooleanType $type;

    protected function setUp(): void
    {
        $this->platform = $this->getMockForAbstractClass(AbstractPlatform::class);
        $this->type     = new BooleanType();
    }

    public function testBooleanConvertsToDatabaseValue(): void
    {
        self::assertIsInt($this->type->convertToDatabaseValue(1, $this->platform));
    }

    public function testBooleanConvertsToPHPValue(): void
    {
        self::assertIsBool($this->type->convertToPHPValue(0, $this->platform));
    }

    public function testBooleanNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

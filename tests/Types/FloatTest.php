<?php

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FloatTest extends TestCase
{
    /** @var AbstractPlatform|MockObject */
    private $platform;

    /** @var FloatType */
    private $type;

    protected function setUp() : void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = Type::getType('float');
    }

    public function testFloatConvertsToPHPValue() : void
    {
        self::assertIsFloat($this->type->convertToPHPValue('5.5', $this->platform));
    }

    public function testFloatNullConvertsToPHPValue() : void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testFloatConvertToDatabaseValue() : void
    {
        self::assertIsFloat($this->type->convertToDatabaseValue(5.5, $this->platform));
    }

    public function testFloatNullConvertToDatabaseValue() : void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }
}

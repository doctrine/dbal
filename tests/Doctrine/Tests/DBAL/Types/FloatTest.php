<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;

class FloatTest extends DbalTestCase
{
    /** @var MockPlatform */
    private $platform;

    /** @var Type */
    private $type;

    protected function setUp() : void
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('float');
    }

    public function testFloatConvertsToPHPValue()
    {
        self::assertIsFloat($this->type->convertToPHPValue('5.5', $this->platform));
    }

    public function testFloatNullConvertsToPHPValue()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testFloatConvertToDatabaseValue()
    {
        self::assertIsFloat($this->type->convertToDatabaseValue(5.5, $this->platform));
    }

    public function testFloatNullConvertToDatabaseValue()
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }
}

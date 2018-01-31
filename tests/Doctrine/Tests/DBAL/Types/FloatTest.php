<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class FloatTest extends \Doctrine\Tests\DbalTestCase
{
    protected $platform, $type;

    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type = Type::getType('float');
    }

    public function testFloatConvertsToPHPValue()
    {
        self::assertInternalType('float', $this->type->convertToPHPValue('5.5', $this->platform));
    }

    public function testFloatNullConvertsToPHPValue()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testFloatConvertToDatabaseValue()
    {
        self::assertInternalType('float', $this->type->convertToDatabaseValue(5.5, $this->platform));
    }

    public function testFloatNullConvertToDatabaseValue()
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }
}

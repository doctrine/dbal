<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class BooleanTest extends \Doctrine\Tests\DbalTestCase
{
    protected
        $platform,
        $type;

    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type = Type::getType('boolean');
    }

    public function testBooleanConvertsToDatabaseValue()
    {
        self::assertInternalType('integer', $this->type->convertToDatabaseValue(1, $this->platform));
    }

    public function testBooleanConvertsToPHPValue()
    {
        self::assertInternalType('bool', $this->type->convertToPHPValue(0, $this->platform));
    }

    public function testBooleanNullConvertsToPHPValue()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

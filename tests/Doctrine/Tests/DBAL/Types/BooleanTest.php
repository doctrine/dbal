<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class BooleanTest extends \Doctrine\Tests\DbalTestCase
{
    protected
        $_platform,
        $_type;

    protected function setUp()
    {
        $this->_platform = new MockPlatform();
        $this->_type = Type::getType('boolean');
    }

    public function testBooleanConvertsToDatabaseValue()
    {
        self::assertInternalType('integer', $this->_type->convertToDatabaseValue(1, $this->_platform));
    }

    public function testBooleanConvertsToPHPValue()
    {
        self::assertInternalType('bool', $this->_type->convertToPHPValue(0, $this->_platform));
    }

    public function testBooleanNullConvertsToPHPValue()
    {
        self::assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }
}

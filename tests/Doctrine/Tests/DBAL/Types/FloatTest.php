<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class FloatTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var MockPlatform
     */
    protected $_platform;

    /**
     * @var Type
     */
    protected $_type;

    protected function setUp()
    {
        $this->_platform = new MockPlatform();
        $this->_type = Type::getType('float');
    }

    public function testFloatConvertsToPHPValue()
    {
        self::assertInternalType('float', $this->_type->convertToPHPValue('5.5', $this->_platform));
    }

    public function testFloatNullConvertsToPHPValue()
    {
        self::assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }

    public function testFloatConvertToDatabaseValue()
    {
        self::assertInternalType('float', $this->_type->convertToDatabaseValue(5.5, $this->_platform));
    }

    public function testFloatNullConvertToDatabaseValue()
    {
        self::assertNull($this->_type->convertToDatabaseValue(null, $this->_platform));
    }
}

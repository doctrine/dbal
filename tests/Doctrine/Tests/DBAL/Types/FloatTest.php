<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

require_once __DIR__ . '/../../TestInit.php';

class FloatTest extends \Doctrine\Tests\DbalTestCase
{
    protected $_platform, $_type;

    protected function setUp()
    {
        $this->_platform = new MockPlatform();
        $this->_type = Type::getType('float');
    }

    public function testFloatConvertsToPHPValue()
    {
        $this->assertInternalType('float', $this->_type->convertToPHPValue('5.5', $this->_platform));
    }

    public function testFloatNullConvertsToPHPValue()
    {
        $this->assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }

    public function testFloatConvertToDatabaseValue()
    {
        $this->assertInternalType('float', $this->_type->convertToDatabaseValue(5.5, $this->_platform));
    }

    public function testFloatNullConvertToDatabaseValue()
    {
        $this->assertNull($this->_type->convertToDatabaseValue(null, $this->_platform));
    }
}

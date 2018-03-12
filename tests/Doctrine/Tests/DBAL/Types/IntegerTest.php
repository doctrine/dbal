<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class IntegerTest extends \Doctrine\Tests\DbalTestCase
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
        $this->_type = Type::getType('integer');
    }

    public function testIntegerConvertsToPHPValue()
    {
        self::assertInternalType('integer', $this->_type->convertToPHPValue('1', $this->_platform));
        self::assertInternalType('integer', $this->_type->convertToPHPValue('0', $this->_platform));
    }

    public function testIntegerNullConvertsToPHPValue()
    {
        self::assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }
}

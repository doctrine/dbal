<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class DecimalTest extends \Doctrine\Tests\DbalTestCase
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
        $this->_type = Type::getType('decimal');
    }

    public function testDecimalConvertsToPHPValue()
    {
        self::assertInternalType('string', $this->_type->convertToPHPValue('5.5', $this->_platform));
    }

    public function testDecimalNullConvertsToPHPValue()
    {
        self::assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }
}

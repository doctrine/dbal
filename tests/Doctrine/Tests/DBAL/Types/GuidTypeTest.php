<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;
use function get_class;

class GuidTypeTest extends DbalTestCase
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
        $this->_type = Type::getType('guid');
    }

    public function testConvertToPHPValue()
    {
        self::assertInternalType("string", $this->_type->convertToPHPValue("foo", $this->_platform));
        self::assertInternalType("string", $this->_type->convertToPHPValue("", $this->_platform));
    }

    public function testNullConversion()
    {
        self::assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }

    public function testNativeGuidSupport()
    {
        self::assertTrue($this->_type->requiresSQLCommentHint($this->_platform));

        $mock = $this->createMock(get_class($this->_platform));
        $mock->expects($this->any())
             ->method('hasNativeGuidType')
             ->will($this->returnValue(true));

        self::assertFalse($this->_type->requiresSQLCommentHint($mock));
    }
}

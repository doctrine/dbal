<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class StringTest extends \Doctrine\Tests\DbalTestCase
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
        $this->_type = Type::getType('string');
    }

    public function testReturnsSqlDeclarationFromPlatformVarchar()
    {
        self::assertEquals("DUMMYVARCHAR()", $this->_type->getSqlDeclaration(array(), $this->_platform));
    }

    public function testReturnsDefaultLengthFromPlatformVarchar()
    {
        self::assertEquals(255, $this->_type->getDefaultLength($this->_platform));
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

    public function testSQLConversion()
    {
        self::assertFalse($this->_type->canRequireSQLConversion(), "String type can never require SQL conversion to work.");
        self::assertEquals('t.foo', $this->_type->convertToDatabaseValueSQL('t.foo', $this->_platform));
        self::assertEquals('t.foo', $this->_type->convertToPHPValueSQL('t.foo', $this->_platform));
    }
}

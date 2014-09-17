<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

require_once __DIR__ . '/../../TestInit.php';

class StringTest extends \Doctrine\Tests\DbalTestCase
{
    protected
        $_platform,
        $_type;

    protected function setUp()
    {
        $this->_platform = new MockPlatform();
        $this->_type = Type::getType('string');
    }

    public function testReturnsSqlDeclarationFromPlatformVarchar()
    {
        $this->assertEquals("DUMMYVARCHAR()", $this->_type->getSqlDeclaration(array(), $this->_platform));
    }

    public function testReturnsDefaultLengthFromPlatformVarchar()
    {
        $this->assertEquals(255, $this->_type->getDefaultLength($this->_platform));
    }

    public function testConvertToPHPValue()
    {
        $this->assertInternalType("string", $this->_type->convertToPHPValue("foo", $this->_platform));
        $this->assertInternalType("string", $this->_type->convertToPHPValue("", $this->_platform));
    }

    public function testNullConversion()
    {
        $this->assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }

    public function testSQLConversion()
    {
        $this->assertFalse($this->_type->canRequireSQLConversion(), "String type can never require SQL conversion to work.");
        $this->assertEquals('t.foo', $this->_type->convertToDatabaseValueSQL('t.foo', $this->_platform));
        $this->assertEquals('t.foo', $this->_type->convertToPHPValueSQL('t.foo', $this->_platform));
    }
}

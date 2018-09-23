<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;

class StringTest extends DbalTestCase
{
    /** @var MockPlatform */
    private $platform;

    /** @var Type */
    private $type;

    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('string');
    }

    public function testReturnsSqlDeclarationFromPlatformVarchar()
    {
        self::assertEquals('DUMMYVARCHAR()', $this->type->getSqlDeclaration([], $this->platform));
    }

    public function testReturnsDefaultLengthFromPlatformVarchar()
    {
        self::assertEquals(255, $this->type->getDefaultLength($this->platform));
    }

    public function testConvertToPHPValue()
    {
        self::assertInternalType('string', $this->type->convertToPHPValue('foo', $this->platform));
        self::assertInternalType('string', $this->type->convertToPHPValue('', $this->platform));
    }

    public function testNullConversion()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testSQLConversion()
    {
        self::assertFalse($this->type->canRequireSQLConversion(), 'String type can never require SQL conversion to work.');
        self::assertEquals('t.foo', $this->type->convertToDatabaseValueSQL('t.foo', $this->platform));
        self::assertEquals('t.foo', $this->type->convertToPHPValueSQL('t.foo', $this->platform));
    }
}

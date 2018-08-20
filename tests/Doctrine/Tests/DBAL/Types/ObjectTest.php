<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use function serialize;

class ObjectTest extends \Doctrine\Tests\DbalTestCase
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
        $this->_type = Type::getType('object');
    }

    public function testObjectConvertsToDatabaseValue()
    {
        self::assertInternalType('string', $this->_type->convertToDatabaseValue(new \stdClass(), $this->_platform));
    }

    public function testObjectConvertsToPHPValue()
    {
        self::assertInternalType('object', $this->_type->convertToPHPValue(serialize(new \stdClass), $this->_platform));
    }

    public function testConversionFailure()
    {
        $this->expectException('Doctrine\DBAL\Types\ConversionException');
        $this->expectExceptionMessage("Could not convert database value to 'object' as an error was triggered by the unserialization: 'unserialize(): Error at offset 0 of 7 bytes'");
        $this->_type->convertToPHPValue('abcdefg', $this->_platform);
    }

    public function testNullConversion()
    {
        self::assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }

    /**
     * @group DBAL-73
     */
    public function testFalseConversion()
    {
        self::assertFalse($this->_type->convertToPHPValue(serialize(false), $this->_platform));
    }
}

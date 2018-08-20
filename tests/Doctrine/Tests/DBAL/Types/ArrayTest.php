<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use function serialize;

class ArrayTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var AbstractPlatform
     */
    protected $_platform;

    /**
     * @var Type
     */
    protected $_type;

    protected function setUp()
    {
        $this->_platform = new MockPlatform();
        $this->_type = Type::getType('array');
    }

    public function testArrayConvertsToDatabaseValue()
    {
        self::assertInternalType(
            'string',
            $this->_type->convertToDatabaseValue(array(), $this->_platform)
        );
    }

    public function testArrayConvertsToPHPValue()
    {
        self::assertInternalType(
            'array',
            $this->_type->convertToPHPValue(serialize(array()), $this->_platform)
        );
    }

    public function testConversionFailure()
    {
        $this->expectException('Doctrine\DBAL\Types\ConversionException');
        $this->expectExceptionMessage("Could not convert database value to 'array' as an error was triggered by the unserialization: 'unserialize(): Error at offset 0 of 7 bytes'");
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

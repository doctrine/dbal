<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use const E_ALL;
use const E_STRICT;
use function error_reporting;
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

    protected function tearDown()
    {
        error_reporting(-1); // reactive all error levels
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
        error_reporting( (E_ALL | E_STRICT) - \E_NOTICE );
        $this->expectException('Doctrine\DBAL\Types\ConversionException');
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

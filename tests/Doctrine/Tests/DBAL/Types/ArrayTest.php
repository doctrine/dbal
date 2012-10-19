<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks;

require_once __DIR__ . '/../../TestInit.php';

class ArrayTest extends \Doctrine\Tests\DbalTestCase
{
    protected
        $_platform,
        $_type;

    protected function setUp()
    {
        $this->_platform = new \Doctrine\Tests\DBAL\Mocks\MockPlatform();
        $this->_type = Type::getType('array');
    }

    public function tearDown()
    {
        error_reporting(-1); // reactive all error levels
    }


    public function testArrayConvertsToDatabaseValue()
    {
        $this->assertTrue(
            is_string($this->_type->convertToDatabaseValue(array(), $this->_platform))
        );
    }

    public function testArrayConvertsToPHPValue()
    {
        $this->assertTrue(
            is_array($this->_type->convertToPHPValue(serialize(array()), $this->_platform))
        );
    }

    public function testArrayConvertsBase64ToPHPValue()
    {
        $this->assertTrue(
            is_array($this->_type->convertToPHPValue(base64_encode(serialize(array())), $this->_platform))
        );
    }

    public function testConversionFailure()
    {
        error_reporting( (E_ALL | E_STRICT) - \E_NOTICE );
        $this->setExpectedException('Doctrine\DBAL\Types\ConversionException');
        $this->_type->convertToPHPValue('abcdefg', $this->_platform);
    }

    public function testNullConversion()
    {
        $this->assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }

    /**
     * @group DBAL-73
     */
    public function testFalseConversion()
    {
        $this->assertFalse($this->_type->convertToPHPValue(serialize(false), $this->_platform));
    }
}
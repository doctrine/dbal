<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks;

require_once __DIR__ . '/../../TestInit.php';
 
class ObjectTest extends \Doctrine\Tests\DbalTestCase
{
    protected
        $_platform,
        $_type;

    protected function setUp()
    {
        $this->_platform = new \Doctrine\Tests\DBAL\Mocks\MockPlatform();
        $this->_type = Type::getType('object');
    }

    public function tearDown()
    {
        error_reporting(-1); // reactive all error levels
    }

    public function testObjectConvertsToDatabaseValue()
    {
        $this->assertInternalType('string', $this->_type->convertToDatabaseValue(new \stdClass(), $this->_platform));
    }

    public function testObjectConvertsToPHPValue()
    {
        $this->assertInternalType('object', $this->_type->convertToPHPValue(serialize(new \stdClass), $this->_platform));
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
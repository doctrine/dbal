<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use const E_ALL;
use const E_STRICT;
use function error_reporting;
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

    protected function tearDown()
    {
        error_reporting(-1); // reactive all error levels
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

<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;

class SimpleArrayTest extends DbalTestCase
{
    /** @var AbstractPlatform */
    protected $_platform;

    /** @var Type */
    protected $_type;

    protected function setUp()
    {
        $this->_platform = new MockPlatform();
        $this->_type     = Type::getType('simple_array');
    }

    public function testEmptyArrayConvertsToNullToDatabaseValue()
    {
        self::assertInternalType(
            'null',
            $this->_type->convertToDatabaseValue([], $this->_platform)
        );
    }

    public function testArrayConvertsArrayToDatabaseValue()
    {
        self::assertInternalType(
            'string',
            $this->_type->convertToDatabaseValue(['one', 'two'], $this->_platform)
        );
    }

    public function testArrayConvertsStringToPHPValue()
    {
        self::assertInternalType(
            'array',
            $this->_type->convertToPHPValue('one,two', $this->_platform)
        );
    }

    public function testNullConvertsToPHPValue()
    {
        self::assertInternalType(
            'array',
            $this->_type->convertToPHPValue(null, $this->_platform)
        );
    }

    public function testArrayPassesThroughArrayForConvertToPHPValue()
    {
        self::assertInternalType(
            'array',
            $this->_type->convertToPHPValue([], $this->_platform)
        );
    }
}

<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class ArrayTest extends \Doctrine\Tests\DbalTestCase
{
    protected
        $platform,
        $type;

    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type = Type::getType('array');
    }

    protected function tearDown()
    {
        error_reporting(-1); // reactive all error levels
    }


    public function testArrayConvertsToDatabaseValue()
    {
        self::assertInternalType(
            'string',
            $this->type->convertToDatabaseValue(array(), $this->platform)
        );
    }

    public function testArrayConvertsToPHPValue()
    {
        self::assertInternalType(
            'array',
            $this->type->convertToPHPValue(serialize(array()), $this->platform)
        );
    }

    public function testConversionFailure()
    {
        error_reporting( (E_ALL | E_STRICT) - \E_NOTICE );
        $this->expectException('Doctrine\DBAL\Types\ConversionException');
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testNullConversion()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    /**
     * @group DBAL-73
     */
    public function testFalseConversion()
    {
        self::assertFalse($this->type->convertToPHPValue(serialize(false), $this->platform));
    }
}

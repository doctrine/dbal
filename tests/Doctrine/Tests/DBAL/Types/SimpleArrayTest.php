<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;

class SimpleArrayTest extends DbalTestCase
{
    /** @var AbstractPlatform */
    private $platform;

    /** @var Type */
    private $type;

    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('simple_array');
    }

    public function testArrayConvertsToDatabaseValue()
    {
        self::assertInternalType(
            'string',
            $this->type->convertToDatabaseValue(['one', 'two', 'three'], $this->platform)
        );
    }

    public function testArrayConvertsToPHPValue()
    {
        self::assertInternalType(
            'array',
            $this->type->convertToPHPValue('one,two,three', $this->platform)
        );
    }

    public function testArrayNormalizesToPHPValue()
    {
        self::assertInternalType(
            'array',
            $this->type->normalizeToPHPValue([], $this->platform)
        );

        self::assertInternalType(
            'null',
            $this->type->normalizeToPHPValue(null, $this->platform)
        );

        self::assertInternalType(
            'array',
            $this->type->convertToPHPValue('one,two,three', $this->platform)
        );
    }

    public function testNullConversion()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

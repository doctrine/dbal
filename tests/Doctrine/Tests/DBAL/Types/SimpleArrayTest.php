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

    protected function setUp() : void
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('simple_array');
    }

    public function testArrayConvertsToDatabaseValue() : void
    {
        self::assertIsString(
            $this->type->convertToDatabaseValue(['one', 'two', 'three'], $this->platform)
        );
    }

    public function testArrayConvertsToPHPValue() : void
    {
        self::assertIsArray(
            $this->type->convertToPHPValue('one,two,three', $this->platform)
        );
    }

    public function testArrayNormalizesToPHPValue() : void
    {
        self::assertIsArray(
            $this->type->normalizeToPHPValue([], $this->platform)
        );

        self::assertNull(
            $this->type->normalizeToPHPValue(null, $this->platform)
        );

        self::assertIsArray(
            $this->type->convertToPHPValue('one,two,three', $this->platform)
        );
    }

    public function testNullConversion() : void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

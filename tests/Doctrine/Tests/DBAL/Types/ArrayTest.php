<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;
use function serialize;

class ArrayTest extends DbalTestCase
{
    /** @var AbstractPlatform */
    private $platform;

    /** @var Type */
    private $type;

    protected function setUp() : void
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('array');
    }

    public function testArrayConvertsToDatabaseValue() : void
    {
        self::assertIsString($this->type->convertToDatabaseValue([], $this->platform));
    }

    public function testArrayConvertsToPHPValue() : void
    {
        self::assertIsArray($this->type->convertToPHPValue(serialize([]), $this->platform));
    }

    public function testArrayConvertsToPHPFailsWithArrayParameterValue() : void
    {
        $this->expectException(ConversionException::class);

        self::assertIsArray(
            $this->type->convertToPHPValue([], $this->platform)
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
            $this->type->convertToPHPValue(serialize([]), $this->platform)
        );
    }

    public function testArrayPassesThroughArrayForConvertToPHPValue() : void
    {
        self::assertIsArray(
            $this->type->convertToPHPValue(serialize([]), $this->platform)
        );
    }

    public function testConversionFailure() : void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage("Could not convert database value to 'array' as an error was triggered by the unserialization: 'unserialize(): Error at offset 0 of 7 bytes'");
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testNullConversion() : void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    /**
     * @group DBAL-73
     */
    public function testFalseConversion() : void
    {
        self::assertFalse($this->type->convertToPHPValue(serialize(false), $this->platform));
    }
}

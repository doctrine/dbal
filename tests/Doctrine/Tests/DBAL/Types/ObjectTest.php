<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;
use stdClass;
use function serialize;

class ObjectTest extends DbalTestCase
{
    /** @var MockPlatform */
    private $platform;

    /** @var Type */
    private $type;

    protected function setUp() : void
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('object');
    }

    public function testObjectConvertsToDatabaseValue() : void
    {
        self::assertIsString($this->type->convertToDatabaseValue(new stdClass(), $this->platform));
    }

    public function testObjectConvertsToPHPValue() : void
    {
        self::assertIsObject($this->type->convertToPHPValue(serialize(new stdClass()), $this->platform));
    }

    public function testExistingObjectPassesThroughForNormalizeToPHPValue() : void
    {
        self::assertIsObject($this->type->normalizeToPHPValue(new stdClass(), $this->platform));
    }

    public function testConversionFailure() : void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage("Could not convert database value to 'object' as an error was triggered by the unserialization: 'unserialize(): Error at offset 0 of 7 bytes'");
        $this->type->convertToPHPValue('abcdefg', $this->platform);
    }

    public function testNullConversion() : void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    /**
     * @group DBAL-73
     */
    public function testFalseConversion(): void
    {
        self::assertFalse($this->type->convertToPHPValue(serialize(false), $this->platform));
    }
}

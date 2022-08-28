<?php

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function fopen;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

class JsonTest extends TestCase
{
    /** @var AbstractPlatform&MockObject */
    protected AbstractPlatform $platform;

    protected JsonType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new JsonType();
    }

    public function testReturnsBindingType(): void
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testReturnsName(): void
    {
        self::assertSame(Types::JSON, $this->type->getName());
    }

    public function testReturnsSQLDeclaration(): void
    {
        $this->platform->expects(self::once())
            ->method('getJsonTypeDeclarationSQL')
            ->willReturn('TEST_JSON');

        self::assertSame('TEST_JSON', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testJsonNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testJsonEmptyStringConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue('', $this->platform));
    }

    public function testJsonStringConvertsToPHPValue(): void
    {
        $value         = ['foo' => 'bar', 'bar' => 'foo'];
        $databaseValue = json_encode($value, 0, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertEquals($value, $phpValue);
    }

    /** @dataProvider providerFailure */
    public function testConversionFailure(string $data): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToPHPValue($data, $this->platform);
    }

    /** @return mixed[][] */
    public static function providerFailure(): iterable
    {
        return [['a'], ['{']];
    }

    public function testJsonResourceConvertsToPHPValue(): void
    {
        $value         = ['foo' => 'bar', 'bar' => 'foo'];
        $databaseValue = fopen(
            'data://text/plain;base64,' . base64_encode(json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
            )),
            'r',
        );
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame($value, $phpValue);
    }

    public function testRequiresSQLCommentHint(): void
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }

    public function testPHPNullValueConvertsToJsonNull(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testPHPValueConvertsToJsonString(): void
    {
        $source        = ['foo' => 'bar', 'bar' => 'foo'];
        $databaseValue = $this->type->convertToDatabaseValue($source, $this->platform);

        self::assertSame('{"foo":"bar","bar":"foo"}', $databaseValue);
    }

    public function testPHPFloatValueConvertsToJsonString(): void
    {
        $source        = ['foo' => 11.4, 'bar' => 10.0];
        $databaseValue = $this->type->convertToDatabaseValue($source, $this->platform);

        self::assertSame('{"foo":11.4,"bar":10.0}', $databaseValue);
    }

    public function testSerializationFailure(): void
    {
        $object            = (object) [];
        $object->recursion = $object;

        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage(
            'Could not convert PHP type \'stdClass\' to \'json\', as an \'Recursion detected\' error'
            . ' was triggered by the serialization',
        );
        $this->type->convertToDatabaseValue($object, $this->platform);
    }
}

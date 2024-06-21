<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\JsonType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function fopen;

class JsonTest extends TestCase
{
    protected AbstractPlatform&MockObject $platform;
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
        $value = ['foo' => 'bar', 'bar' => 'foo'];

        $databaseValue = '{"foo":"bar","bar":"foo"}';

        $phpValue = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertEquals($value, $phpValue);
    }

    #[DataProvider('providerFailure')]
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
        $value = ['foo' => 'bar', 'bar' => 'foo'];

        $json = '{"foo":"bar","bar":"foo"}';

        $databaseValue = fopen('data://text/plain;base64,' . base64_encode($json), 'r');
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame($value, $phpValue);
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
            'Could not convert PHP type "stdClass" to "json". '
            . 'An error was triggered by the serialization: Recursion detected',
        );
        $this->type->convertToDatabaseValue($object, $this->platform);
    }
}

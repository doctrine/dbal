<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\JsonObjectType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

use function base64_encode;
use function fopen;

class JsonObjectTest extends TestCase
{
    protected AbstractPlatform&MockObject $platform;
    protected JsonObjectType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new JsonObjectType();
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

    #[DataProvider('providerJsonString')]
    public function testJsonStringConvertsToPHPValue(mixed $databaseValue, mixed $expectedValue): void
    {
        $phpValue = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertEquals($expectedValue, $phpValue);
    }

    /** @return mixed[][] */
    public static function providerJsonString(): iterable
    {
        $value         = new stdClass();
        $value->foo    = 'bar';
        $value->bar    = 'foo';
        $value->array  = [];
        $value->object = new stdClass();

        return [
            ['{"foo":"bar","bar":"foo","array":[],"object":{}}', $value],
            ['1', 1],
            ['["bar"]', ['bar']],
        ];
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
        $value         = new stdClass();
        $value->foo    = 'bar';
        $value->bar    = 'foo';
        $value->array  = [];
        $value->object = new stdClass();

        $json = '{"foo":"bar","bar":"foo","array":[],"object":{}}';

        $databaseValue = fopen('data://text/plain;base64,' . base64_encode($json), 'r');
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertEquals($value, $phpValue);
    }

    public function testPHPNullValueConvertsToJsonNull(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    #[DataProvider('providerPHPValue')]
    public function testPHPValueConvertsToJsonString(mixed $phpValue, mixed $expectedValue): void
    {
        $databaseValue = $this->type->convertToDatabaseValue($phpValue, $this->platform);

        self::assertSame($expectedValue, $databaseValue);
    }

    /** @return mixed[][] */
    public static function providerPHPValue(): iterable
    {
        $source         = new stdClass();
        $source->foo    = 'bar';
        $source->bar    = 'foo';
        $source->array  = [];
        $source->object = new stdClass();

        return [
            [$source, '{"foo":"bar","bar":"foo","array":[],"object":{}}'],
            [1, '1'],
            [['foo' => 'bar'], '{"foo":"bar"}'],
            [['bar'], '["bar"]'],
        ];
    }

    public function testPHPFloatValueConvertsToJsonString(): void
    {
        $source      = new stdClass();
        $source->foo = 11.4;
        $source->bar = 10.0;

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

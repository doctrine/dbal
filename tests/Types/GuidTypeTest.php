<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use stdClass;

class GuidTypeTest extends TestCase
{
    use VerifyDeprecations;

    private const DEPRECATION = 'https://github.com/doctrine/dbal/pull/7504';

    private AbstractPlatform&Stub $platform;
    private GuidType $type;

    protected function setUp(): void
    {
        $this->platform = self::createStub(AbstractPlatform::class);
        $this->type     = new GuidType();
    }

    public function testNullConversion(): void
    {
        $this->expectNoDeprecationWithIdentifier(self::DEPRECATION);

        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    #[DataProvider('validValuesProvider')]
    public function testValidValueIsPassedThrough(string $value): void
    {
        $this->expectNoDeprecationWithIdentifier(self::DEPRECATION);

        self::assertSame($value, $this->type->convertToDatabaseValue($value, $this->platform));
        self::assertSame($value, $this->type->convertToPHPValue($value, $this->platform));
    }

    /** @return iterable<string, array{string}> */
    public static function validValuesProvider(): iterable
    {
        yield 'canonical' => ['7c620eda-ea79-11eb-9a03-0242ac130003'];
        yield 'canonical in upper case' => ['7C620EDA-EA79-11EB-9A03-0242AC130003'];
        yield 'without hyphens' => ['7c620edaea7911eb9a030242ac130003'];
        yield 'with braces' => ['{7c620eda-ea79-11eb-9a03-0242ac130003}'];
        yield 'with hyphens after other groups' => ['7c620eda-ea7911eb-9a030242-ac130003'];
    }

    #[DataProvider('malformedValuesProvider')]
    public function testMalformedValueTriggersDeprecation(mixed $value): void
    {
        $this->expectDeprecationWithIdentifier(self::DEPRECATION);

        self::assertSame($value, $this->type->convertToDatabaseValue($value, $this->platform));
        self::assertSame($value, $this->type->convertToPHPValue($value, $this->platform));
    }

    /** @return iterable<string, array{mixed}> */
    public static function malformedValuesProvider(): iterable
    {
        yield 'arbitrary string' => ['foo'];
        yield 'empty string' => [''];
        yield 'single digit' => ['1'];
        yield 'unbalanced brace' => ['{7c620eda-ea79-11eb-9a03-0242ac130003'];
        yield 'one digit too few' => ['7c620eda-ea79-11eb-9a03-0242ac13000'];
        yield 'one digit too many' => ['7c620eda-ea79-11eb-9a03-0242ac1300031'];
        yield 'non hexadecimal digit' => ['7c620eda-ea79-11eb-9a03-0242ac13000z'];
        yield 'misplaced hyphen' => ['7c620ed-aea79-11eb-9a03-0242ac130003'];
        yield 'integer' => [0];
        yield 'float' => [1.2];
        yield 'boolean' => [true];
        yield 'array' => [[]];
        yield 'object' => [new stdClass()];
    }
}

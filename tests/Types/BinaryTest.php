<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\ConversionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function array_map;
use function base64_encode;
use function fopen;
use function implode;
use function range;

class BinaryTest extends TestCase
{
    protected AbstractPlatform&MockObject $platform;
    protected BinaryType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new BinaryType();
    }

    public function testReturnsBindingType(): void
    {
        self::assertSame(ParameterType::BINARY, $this->type->getBindingType());
    }

    public function testBinaryNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testBinaryStringConvertsToPHPValue(): void
    {
        $databaseValue = $this->getBinaryString();
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame($databaseValue, $phpValue);
    }

    public function testBinaryResourceConvertsToPHPValue(): void
    {
        $databaseValue = fopen('data://text/plain;base64,' . base64_encode('binary string'), 'r');
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame('binary string', $phpValue);
    }

    /**
     * Creates a binary string containing all possible byte values.
     */
    private function getBinaryString(): string
    {
        return implode(array_map('chr', range(0, 255)));
    }

    #[DataProvider('getInvalidDatabaseValues')]
    public function testThrowsConversionExceptionOnInvalidDatabaseValue(mixed $value): void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToPHPValue($value, $this->platform);
    }

    /** @return mixed[][] */
    public static function getInvalidDatabaseValues(): iterable
    {
        return [
            [false],
            [true],
            [0],
            [1],
            [-1],
            [0.0],
            [1.1],
            [-1.1],
        ];
    }
}

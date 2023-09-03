<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BigIntType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

class BigIntTypeTest extends TestCase
{
    /** @var MockObject&AbstractPlatform */
    private AbstractPlatform $platform;

    private BigIntType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new BigIntType();
    }

    public function testShouldConvertPhpIntMinToInteger(): void
    {
        self::assertSame(
            PHP_INT_MIN,
            $this->type->convertToPHPValue(PHP_INT_MIN, $this->platform),
        );
    }

    public function testShouldConvertPhpIntMaxToInteger(): void
    {
        self::assertSame(
            PHP_INT_MAX,
            $this->type->convertToPHPValue(PHP_INT_MAX, $this->platform),
        );
    }

    public function testShouldConvertPhpIntMinAsStringToInteger(): void
    {
        self::assertSame(
            PHP_INT_MIN,
            $this->type->convertToPHPValue((string) PHP_INT_MIN, $this->platform),
        );
    }

    public function testShouldConvertPhpIntMaxAsStringToInteger(): void
    {
        self::assertSame(
            PHP_INT_MAX,
            $this->type->convertToPHPValue((string) PHP_INT_MAX, $this->platform),
        );
    }

    public function testShouldConvertZeroIntegerToInteger(): void
    {
        self::assertSame(
            0,
            $this->type->convertToPHPValue(0, $this->platform),
        );
    }

    public function testShouldConvertZeroStringToInteger(): void
    {
        self::assertSame(
            0,
            $this->type->convertToPHPValue('0', $this->platform),
        );
    }

    public function testShouldConvertSafeNegativeValueToInteger(): void
    {
        self::assertSame(
            PHP_INT_MIN + 1,
            $this->type->convertToPHPValue(PHP_INT_MIN + 1, $this->platform),
        );
    }

    public function testShouldConvertSafePositiveValueToInteger(): void
    {
        self::assertSame(
            PHP_INT_MAX - 1,
            $this->type->convertToPHPValue(PHP_INT_MAX - 1, $this->platform),
        );
    }
}

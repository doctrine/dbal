<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\IntegerType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IntegerTest extends TestCase
{
    private AbstractPlatform&MockObject $platform;
    private IntegerType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new IntegerType();
    }

    public function testIntegerConvertsToPHPValue(): void
    {
        self::assertIsInt($this->type->convertToPHPValue('1', $this->platform));
        self::assertIsInt($this->type->convertToPHPValue('0', $this->platform));
    }

    public function testIntegerNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

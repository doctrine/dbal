<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DecimalTest extends DbalTestCase
{
    /** @var AbstractPlatform&MockObject */
    private $platform;

    /** @var DecimalType */
    private $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new DecimalType();
    }

    public function testDecimalConvertsToPHPValue(): void
    {
        self::assertIsString($this->type->convertToPHPValue('5.5', $this->platform));
    }

    public function testDecimalNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\SmallIntType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SmallIntTest extends TestCase
{
    private AbstractPlatform&MockObject $platform;
    private SmallIntType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new SmallIntType();
    }

    public function testSmallIntConvertsToPHPValue(): void
    {
        self::assertIsInt($this->type->convertToPHPValue('1', $this->platform));
        self::assertIsInt($this->type->convertToPHPValue('0', $this->platform));
    }

    public function testSmallIntNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

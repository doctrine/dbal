<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BlobType;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class BlobTest extends TestCase
{
    protected AbstractPlatform&Stub $platform;
    protected BlobType $type;

    protected function setUp(): void
    {
        $this->platform = self::createStub(AbstractPlatform::class);
        $this->type     = new BlobType();
    }

    public function testBlobNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

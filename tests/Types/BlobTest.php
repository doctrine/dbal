<?php

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BlobType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function chr;
use function fopen;
use function fwrite;
use function rewind;
use function stream_get_contents;

class BlobTest extends TestCase
{
    /** @var AbstractPlatform&MockObject */
    protected AbstractPlatform $platform;

    protected BlobType $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new BlobType();
    }

    public function testBlobNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testBinaryStringConvertsToPHPValue(): void
    {
        $databaseValue = $this->getBinaryString();
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertIsResource($phpValue);
        self::assertSame($databaseValue, stream_get_contents($phpValue));
    }

    public function testBinaryResourceConvertsToPHPValue(): void
    {
        $databaseValue = fopen('php://memory', 'r+');
        fwrite($databaseValue, $this->getBinaryString());
        rewind($databaseValue);
        $phpValue = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame($databaseValue, $phpValue);
    }

    /**
     * Creates a binary string containing all possible byte values.
     */
    private function getBinaryString(): string
    {
        $string = '';

        for ($i = 0; $i < 256; $i++) {
            $string .= chr($i);
        }

        return $string;
    }
}

<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use function base64_encode;
use function chr;
use function fopen;
use function stream_get_contents;

class BlobTest extends DbalTestCase
{
    /** @var AbstractPlatform|MockObject */
    protected $platform;

    /** @var BlobType */
    protected $type;

    /**
     * {@inheritdoc}
     */
    protected function setUp() : void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = Type::getType('blob');
    }

    public function testBlobNullConvertsToPHPValue() : void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testBinaryStringConvertsToPHPValue() : void
    {
        $databaseValue = $this->getBinaryString();
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertIsResource($phpValue);
        self::assertSame($databaseValue, stream_get_contents($phpValue));
    }

    public function testBinaryResourceConvertsToPHPValue() : void
    {
        $databaseValue = fopen('data://text/plain;base64,' . base64_encode($this->getBinaryString()), 'r');
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame($databaseValue, $phpValue);
    }

    /**
     * Creates a binary string containing all possible byte values.
     */
    private function getBinaryString() : string
    {
        $string = '';

        for ($i = 0; $i < 256; $i++) {
            $string .= chr($i);
        }

        return $string;
    }
}

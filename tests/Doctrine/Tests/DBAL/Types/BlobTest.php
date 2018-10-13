<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;
use function base64_encode;
use function chr;
use function fopen;
use function stream_get_contents;

class BlobTest extends DbalTestCase
{
    /** @var MockPlatform */
    protected $platform;

    /** @var BlobType */
    protected $type;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('blob');
    }

    public function testBlobNullConvertsToPHPValue()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testBinaryStringConvertsToPHPValue()
    {
        $databaseValue = $this->getBinaryString();
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertInternalType('resource', $phpValue);
        self::assertSame($databaseValue, stream_get_contents($phpValue));
    }

    public function testBinaryResourceConvertsToPHPValue()
    {
        $databaseValue = fopen('data://text/plain;base64,' . base64_encode($this->getBinaryString()), 'r');
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame($databaseValue, $phpValue);
    }

    /**
     * Creates a binary string containing all possible byte values.
     *
     * @return string
     */
    private function getBinaryString()
    {
        $string = '';

        for ($i = 0; $i < 256; $i++) {
            $string .= chr($i);
        }

        return $string;
    }
}

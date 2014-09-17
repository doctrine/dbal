<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

require_once __DIR__ . '/../../TestInit.php';

class BlobTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var \Doctrine\Tests\DBAL\Mocks\MockPlatform
     */
    protected $platform;

    /**
     * @var \Doctrine\DBAL\Types\BlobType
     */
    protected $type;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type = Type::getType('blob');
    }

    public function testBlobNullConvertsToPHPValue()
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testBinaryStringConvertsToPHPValue()
    {
        $databaseValue = $this->getBinaryString();
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        $this->assertInternalType('resource', $phpValue);
        $this->assertSame($databaseValue, stream_get_contents($phpValue));
    }

    public function testBinaryResourceConvertsToPHPValue()
    {
        $databaseValue = fopen('data://text/plain;base64,' . base64_encode($this->getBinaryString()), 'r');
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        $this->assertSame($databaseValue, $phpValue);
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

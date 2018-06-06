<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use function base64_encode;
use function chr;
use function fopen;
use function stream_get_contents;

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
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

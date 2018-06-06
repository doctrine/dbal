<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;

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
}

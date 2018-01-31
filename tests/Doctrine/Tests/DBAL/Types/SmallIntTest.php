<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class SmallIntTest extends \Doctrine\Tests\DbalTestCase
{
    protected
        $platform,
        $type;

    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type = Type::getType('smallint');
    }

    public function testSmallIntConvertsToPHPValue()
    {
        self::assertInternalType('integer', $this->type->convertToPHPValue('1', $this->platform));
        self::assertInternalType('integer', $this->type->convertToPHPValue('0', $this->platform));
    }

    public function testSmallIntNullConvertsToPHPValue()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

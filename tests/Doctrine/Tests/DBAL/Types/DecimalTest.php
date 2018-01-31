<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class DecimalTest extends \Doctrine\Tests\DbalTestCase
{
    protected
        $platform,
        $type;

    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type = Type::getType('decimal');
    }

    public function testDecimalConvertsToPHPValue()
    {
        self::assertInternalType('string', $this->type->convertToPHPValue('5.5', $this->platform));
    }

    public function testDecimalNullConvertsToPHPValue()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

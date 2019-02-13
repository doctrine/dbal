<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;

class IntegerTest extends DbalTestCase
{
    /** @var MockPlatform */
    private $platform;

    /** @var Type */
    private $type;

    protected function setUp() : void
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('integer');
    }

    public function testIntegerConvertsToPHPValue() : void
    {
        self::assertIsInt($this->type->convertToPHPValue('1', $this->platform));
        self::assertIsInt($this->type->convertToPHPValue('0', $this->platform));
    }

    public function testIntegerNullConvertsToPHPValue() : void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

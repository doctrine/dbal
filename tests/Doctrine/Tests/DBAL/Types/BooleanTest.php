<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;

class BooleanTest extends DbalTestCase
{
    /** @var MockPlatform */
    private $platform;

    /** @var Type */
    private $type;

    protected function setUp() : void
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('boolean');
    }

    public function testBooleanConvertsToDatabaseValue()
    {
        self::assertIsInt($this->type->convertToDatabaseValue(1, $this->platform));
    }

    public function testBooleanConvertsToPHPValue()
    {
        self::assertIsBool($this->type->convertToPHPValue(0, $this->platform));
    }

    public function testBooleanNullConvertsToPHPValue()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}

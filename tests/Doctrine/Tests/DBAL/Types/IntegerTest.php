<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class IntegerTest extends DbalTestCase
{
    /** @var AbstractPlatform|MockObject */
    private $platform;

    /** @var IntegerType */
    private $type;

    protected function setUp() : void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
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

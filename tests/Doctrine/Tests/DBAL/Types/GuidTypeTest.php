<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class GuidTypeTest extends DbalTestCase
{
    /** @var AbstractPlatform|MockObject */
    private $platform;

    /** @var GuidType */
    private $type;

    protected function setUp() : void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = Type::getType('guid');
    }

    public function testConvertToPHPValue() : void
    {
        self::assertIsString($this->type->convertToPHPValue('foo', $this->platform));
        self::assertIsString($this->type->convertToPHPValue('', $this->platform));
    }

    public function testNullConversion() : void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testNativeGuidSupport() : void
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform));

        $this->platform->expects($this->any())
             ->method('hasNativeGuidType')
             ->will($this->returnValue(true));

        self::assertFalse($this->type->requiresSQLCommentHint($this->platform));
    }
}

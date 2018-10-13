<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;
use function get_class;

class GuidTypeTest extends DbalTestCase
{
    /** @var MockPlatform */
    private $platform;

    /** @var Type */
    private $type;

    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('guid');
    }

    public function testConvertToPHPValue()
    {
        self::assertInternalType('string', $this->type->convertToPHPValue('foo', $this->platform));
        self::assertInternalType('string', $this->type->convertToPHPValue('', $this->platform));
    }

    public function testNullConversion()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testNativeGuidSupport()
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform));

        $mock = $this->createMock(get_class($this->platform));
        $mock->expects($this->any())
             ->method('hasNativeGuidType')
             ->will($this->returnValue(true));

        self::assertFalse($this->type->requiresSQLCommentHint($mock));
    }
}

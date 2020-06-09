<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StringTest extends TestCase
{
    /** @var AbstractPlatform|MockObject */
    private $platform;

    /** @var StringType */
    private $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new StringType();
    }

    public function testReturnsSQLDeclaration(): void
    {
        $this->platform->expects(self::once())
            ->method('getStringTypeDeclarationSQL')
            ->willReturn('TEST_VARCHAR');

        self::assertEquals('TEST_VARCHAR', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testConvertToPHPValue(): void
    {
        self::assertIsString($this->type->convertToPHPValue('foo', $this->platform));
        self::assertIsString($this->type->convertToPHPValue('', $this->platform));
    }

    public function testNullConversion(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testSQLConversion(): void
    {
        self::assertFalse($this->type->canRequireSQLConversion(), 'String type can never require SQL conversion to work.');
        self::assertEquals('t.foo', $this->type->convertToDatabaseValueSQL('t.foo', $this->platform));
        self::assertEquals('t.foo', $this->type->convertToPHPValueSQL('t.foo', $this->platform));
    }
}

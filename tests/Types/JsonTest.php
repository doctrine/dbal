<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function fopen;

class JsonTest extends TestCase
{
    /** @var AbstractPlatform|MockObject */
    protected $platform;

    /** @var JsonType */
    protected $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new JsonType();
    }

    public function testReturnsBindingType(): void
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testReturnsName(): void
    {
        self::assertSame(Types::JSON, $this->type->getName());
    }

    public function testReturnsSQLDeclaration(): void
    {
        $this->platform->expects(self::once())
            ->method('getJsonTypeDeclarationSQL')
            ->willReturn('TEST_JSON');

        self::assertSame('TEST_JSON', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testJsonNullConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testJsonEmptyStringConvertsToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue('', $this->platform));
    }

    public function testJsonStringConvertsToPHPValue(): void
    {
        $value = ['foo' => 'bar', 'bar' => 'foo'];

        $databaseValue = '{"foo":"bar","bar":"foo"}';

        $phpValue = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertEquals($value, $phpValue);
    }

    /** @dataProvider providerFailure */
    public function testConversionFailure(string $data): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToPHPValue($data, $this->platform);
    }

    /**
     * @return mixed[][]
     */
    public static function providerFailure(): iterable
    {
        return [['a'], ['{']];
    }

    public function testJsonResourceConvertsToPHPValue(): void
    {
        $value = ['foo' => 'bar', 'bar' => 'foo'];

        $json = '{"foo":"bar","bar":"foo"}';

        $databaseValue = fopen('data://text/plain;base64,' . base64_encode($json), 'r');
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame($value, $phpValue);
    }

    public function testRequiresSQLCommentHint(): void
    {
        self::assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}

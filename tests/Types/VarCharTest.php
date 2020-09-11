<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Driver\Encodings;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\VarCharType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VarCharTest extends TestCase
{
    /** @var AbstractPlatform|MockObject */
    private $platform;

    /** @var VarCharType */
    private $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type     = new VarCharType();
    }

    public function testReturnsBindingType(): void
    {
        self::assertEquals($this->type->getBindingType(), ParameterType::STRING | Encodings::ASCII);
    }

    /**
     * @param array<string, int|bool> $column
     *
     * @dataProvider sqlDeclarationDataProvider
     */
    public function testReturnsSQLDeclaration(string $expectedSql, array $column): void
    {
        $declarationSql = $this->type->getSQLDeclaration($column, $this->platform);
        self::assertEquals($expectedSql, $declarationSql);
    }

    /**
     * @return array<int, array<int, string|array<string, int|bool>>>
     */
    public static function sqlDeclarationDataProvider(): array
    {
        return [
            ['VARCHAR(12)', ['length' => 12]],
            ['CHAR(12)', ['length' => 12, 'fixed' => true]],
        ];
    }
}

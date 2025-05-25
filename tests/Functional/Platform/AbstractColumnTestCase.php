<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;

abstract class AbstractColumnTestCase extends FunctionalTestCase
{
    public function testVariableLengthStringNoLength(): void
    {
        $this->assertColumn(
            Column::editor()
                ->setTypeName(Types::STRING),
            'Test',
            ParameterType::STRING,
        );
    }

    #[DataProvider('string8Provider')]
    public function testVariableLengthStringWithLength(string $value): void
    {
        $this->assertColumn(
            Column::editor()
                ->setTypeName(Types::STRING)
                ->setLength(8),
            $value,
            ParameterType::STRING,
        );
    }

    #[DataProvider('string1Provider')]
    public function testFixedLengthStringNoLength(string $value): void
    {
        $this->assertColumn(
            Column::editor()
                ->setTypeName(Types::STRING)
                ->setFixed(true),
            $value,
            ParameterType::STRING,
        );
    }

    #[DataProvider('string8Provider')]
    public function testFixedLengthStringWithLength(string $value): void
    {
        $this->assertColumn(
            Column::editor()
                ->setTypeName(Types::STRING)
                ->setFixed(true)
                ->setLength(8),
            $value,
            ParameterType::STRING,
        );
    }

    /** @return iterable<string, array<int, mixed>> */
    public static function string1Provider(): iterable
    {
        return [
            'ansi' => ['Z'],
            'unicode' => ['Я'],
        ];
    }

    /** @return iterable<string, array<int, mixed>> */
    public static function string8Provider(): iterable
    {
        return [
            'ansi' => ['Doctrine'],
            'unicode' => ['Доктрина'],
        ];
    }

    public function testVariableLengthBinaryNoLength(): void
    {
        $this->assertColumn(
            Column::editor()
                ->setTypeName(Types::BINARY),
            "\x00\x01\x02\x03",
            ParameterType::BINARY,
        );
    }

    public function testVariableLengthBinaryWithLength(): void
    {
        $this->assertColumn(
            Column::editor()
                ->setTypeName(Types::BINARY)
                ->setLength(8),
            "\xCE\xC6\x6B\xDD\x9F\xD8\x07\xB4",
            ParameterType::BINARY,
        );
    }

    public function testFixedLengthBinaryNoLength(): void
    {
        $this->assertColumn(
            Column::editor()
                ->setTypeName(Types::BINARY)
                ->setFixed(true),
            "\xFF",
            ParameterType::BINARY,
        );
    }

    public function testFixedLengthBinaryWithLength(): void
    {
        $this->assertColumn(
            Column::editor()
                ->setTypeName(Types::BINARY)
                ->setFixed(true)
                ->setLength(8),
            "\xA0\x0A\x7B\x0E\xA4\x60\x78\xD8",
            ParameterType::BINARY,
        );
    }

    protected function requirePlatform(string $class): void
    {
        if ($this->connection->getDatabasePlatform() instanceof $class) {
            return;
        }

        self::markTestSkipped(sprintf('The test requires %s', $class));
    }

    protected function assertColumn(ColumnEditor $editor, string $value, ParameterType $bindType): void
    {
        $column = $editor
            ->setUnquotedName('val')
            ->create();

        $table = Table::editor()
            ->setUnquotedName('column_test')
            ->setColumns($column)
            ->create();

        $this->dropAndCreateTable($table);

        self::assertSame(1, $this->connection->insert('column_test', ['val' => $value], [$bindType]));

        self::assertSame($value, $column->getType()->convertToPHPValue(
            $this->connection->fetchOne('SELECT val FROM column_test'),
            $this->connection->getDatabasePlatform(),
        ));
    }
}

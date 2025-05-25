<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\Exception\InvalidColumnType\ColumnValuesRequired;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\EnumType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

final class EnumTypeTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $this->dropTableIfExists('my_enum_table');
    }

    public function testIntrospectEnum(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('This test requires MySQL or MariaDB.');
        }

        $this->connection->executeStatement(<<< 'SQL'
            CREATE TABLE my_enum_table (
                id BIGINT NOT NULL PRIMARY KEY,
                suit ENUM('hearts', 'diamonds', 'clubs', 'spades') NOT NULL DEFAULT 'hearts'
            );
            SQL);

        $schemaManager = $this->connection->createSchemaManager();
        $table         = $schemaManager->introspectTable('my_enum_table');

        self::assertCount(2, $table->getColumns());
        self::assertTrue($table->hasColumn('suit'));
        self::assertInstanceOf(EnumType::class, $table->getColumn('suit')->getType());
        self::assertSame(['hearts', 'diamonds', 'clubs', 'spades'], $table->getColumn('suit')->getValues());
        self::assertSame('hearts', $table->getColumn('suit')->getDefault());
    }

    public function testDeployEnum(): void
    {
        $table = Table::editor()
            ->setUnquotedName('my_enum_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::BIGINT)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('suit')
                    ->setTypeName(Types::ENUM)
                    ->setValues(['hearts', 'diamonds', 'clubs', 'spades'])
                    ->setDefaultValue('hearts')
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $schemaManager = $this->connection->createSchemaManager();

        $introspectedTable = $schemaManager->introspectTable('my_enum_table');

        self::assertTrue($schemaManager->createComparator()->compareTables($table, $introspectedTable)->isEmpty());

        $this->connection->insert('my_enum_table', ['id' => 1, 'suit' => 'hearts'], ['suit' => Types::ENUM]);
        $this->connection->insert(
            'my_enum_table',
            ['id' => 2, 'suit' => 'diamonds'],
            ['suit' => Type::getType(Types::ENUM)],
        );

        self::assertEquals(
            [[1, 'hearts'], [2, 'diamonds']],
            $this->connection->fetchAllNumeric('SELECT id, suit FROM my_enum_table ORDER BY id ASC'),
        );
    }

    public function testDeployEmptyEnum(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        $table = Table::editor()
            ->setUnquotedName('my_enum_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('suit')
                    ->setTypeName(Types::ENUM)
                    ->create(),
            )
            ->create();

        $this->expectException(ColumnValuesRequired::class);

        $schemaManager->createTable($table);
    }

    /** @param list<string> $expectedValues */
    #[DataProvider('provideEnumDefinitions')]
    public function testIntrospectEnumValues(string $definition, array $expectedValues): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('This test requires MySQL or MariaDB.');
        }

        $this->connection->executeStatement(<<< SQL
            CREATE TABLE my_enum_table (
                id BIGINT NOT NULL PRIMARY KEY,
                my_enum $definition DEFAULT NULL
            );
            SQL);

        $schemaManager = $this->connection->createSchemaManager();
        $table         = $schemaManager->introspectTable('my_enum_table');

        self::assertInstanceOf(EnumType::class, $table->getColumn('my_enum')->getType());
        self::assertSame($expectedValues, $table->getColumn('my_enum')->getValues());
        self::assertNull($table->getColumn('my_enum')->getDefault());
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function provideEnumDefinitions(): iterable
    {
        yield 'simple' => ['ENUM("a", "b", "c")', ['a', 'b', 'c']];
        yield 'empty first' => ['ENUM("", "a", "b", "c")', ['', 'a', 'b', 'c']];
        yield 'empty in the middle' => ['ENUM("a", "", "b", "c")', ['a', '', 'b', 'c']];
        yield 'empty last' => ['ENUM("a", "b", "c", "")', ['a', 'b', 'c', '']];
        yield 'with spaces' => ['ENUM("a b", "c d", "e f")', ['a b', 'c d', 'e f']];
        yield 'with quotes' => ['ENUM("a\'b", "c\'d", "e\'f")', ['a\'b', 'c\'d', 'e\'f']];
        yield 'with commas' => ['ENUM("a,b", "c,d", "e,f")', ['a,b', 'c,d', 'e,f']];
        yield 'with parentheses' => ['ENUM("(a)", "(b)", "(c)")', ['(a)', '(b)', '(c)']];
        yield 'with quotes and commas' => ['ENUM("a\'b", "c\'d", "e\'f")', ['a\'b', 'c\'d', 'e\'f']];
        yield 'with quotes and parentheses' => ['ENUM("(a)", "(b)", "(c)")', ['(a)', '(b)', '(c)']];
        yield 'with commas and parentheses' => ['ENUM("(a,b)", "(c,d)", "(e,f)")', ['(a,b)', '(c,d)', '(e,f)']];
        yield 'with quotes, commas and parentheses'
            => ['ENUM("(a\'b)", "(c\'d)", "(e\'f)")', ['(a\'b)', '(c\'d)', '(e\'f)']];
    }
}

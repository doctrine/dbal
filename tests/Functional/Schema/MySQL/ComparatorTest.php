<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\MySQL;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

final class ComparatorTest extends FunctionalTestCase
{
    private AbstractPlatform $platform;

    private AbstractSchemaManager $schemaManager;

    private Comparator $comparator;

    #[Override]
    protected function setUp(): void
    {
        $this->platform = $this->connection->getDatabasePlatform();

        if (! $this->platform instanceof AbstractMySQLPlatform) {
            self::markTestSkipped();
        }

        $this->schemaManager = $this->connection->createSchemaManager();
        $this->comparator    = $this->schemaManager->createComparator();
    }

    #[DataProvider('lobColumnProvider')]
    public function testLobLengthIncrementWithinLimit(string $type, int $length): void
    {
        $table = $this->createLobTable($type, $length - 1);
        $table = $this->setBlobLength($table, $length);

        self::assertTrue(ComparatorTestUtils::diffFromActualToDesiredTable(
            $this->schemaManager,
            $this->comparator,
            $table,
        )->isEmpty());

        self::assertTrue(ComparatorTestUtils::diffFromDesiredToActualTable(
            $this->schemaManager,
            $this->comparator,
            $table,
        )->isEmpty());
    }

    #[DataProvider('lobColumnProvider')]
    public function testLobLengthIncrementOverLimit(string $type, int $length): void
    {
        $table = $this->createLobTable($type, $length);
        $table = $this->setBlobLength($table, $length + 1);
        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }

    /**
     * A column indexed by a prefix of its value cannot serve a foreign key, so MySQL indexes the
     * referencing columns itself even though the declared index leads with them.
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html
     *
     * @throws Exception
     */
    public function testForeignKeyIsIndexedDespiteAnIndexPrefixingItsLastColumn(): void
    {
        $table = $this->createTableWithAForeignKeyIndexedByAPrefix();

        $introspected = $this->schemaManager->introspectTable($table->getObjectName());

        self::assertIndexedColumnListEquals(
            [
                new IndexedColumn(UnqualifiedName::unquoted('parent_id'), null),
                new IndexedColumn(UnqualifiedName::unquoted('parent_code'), null),
            ],
            $introspected->getIndex('prefix_fk')->getIndexedColumns(),
        );
    }

    /** @throws Exception */
    public function testTheIndexMySQLAddsForSuchAForeignKeyIsNoDifference(): void
    {
        $table = $this->createTableWithAForeignKeyIndexedByAPrefix();

        self::assertTrue(ComparatorTestUtils::diffFromActualToDesiredTable(
            $this->schemaManager,
            $this->comparator,
            $table,
        )->isEmpty());
    }

    /** @throws Exception */
    private function createTableWithAForeignKeyIndexedByAPrefix(): Table
    {
        $this->dropTableIfExists('prefix_child');
        $this->dropTableIfExists('prefix_parent');

        $this->schemaManager->createTable(
            Table::editor()
                ->setUnquotedName('prefix_parent')
                ->setColumns($this->intColumn('id'), $this->stringColumn('code'))
                ->setPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()
                        ->setUnquotedColumnNames('id', 'code')
                        ->create(),
                )
                ->create(),
        );

        $table = Table::editor()
            ->setUnquotedName('prefix_child')
            ->setColumns($this->intColumn('parent_id'), $this->stringColumn('parent_code'))
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('prefix_idx')
                    ->addUnquotedColumnName('parent_id')
                    ->addUnquotedColumnName('parent_code', 10)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('prefix_fk')
                    ->setUnquotedReferencingColumnNames('parent_id', 'parent_code')
                    ->setUnquotedReferencedTableName('prefix_parent')
                    ->setUnquotedReferencedColumnNames('id', 'code')
                    ->create(),
            )
            ->create();

        $this->schemaManager->createTable($table);

        return $table;
    }

    /** @param non-empty-string $name */
    private function intColumn(string $name): Column
    {
        return Column::editor()
            ->setUnquotedName($name)
            ->setTypeName(Types::INTEGER)
            ->create();
    }

    /** @param non-empty-string $name */
    private function stringColumn(string $name): Column
    {
        return Column::editor()
            ->setUnquotedName($name)
            ->setTypeName(Types::STRING)
            ->setLength(64)
            ->create();
    }

    /** @return iterable<array{string,int}> */
    public static function lobColumnProvider(): iterable
    {
        yield [Types::BLOB, AbstractMySQLPlatform::LENGTH_LIMIT_TINYBLOB];
        yield [Types::BLOB, AbstractMySQLPlatform::LENGTH_LIMIT_BLOB];
        yield [Types::BLOB, AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMBLOB];

        yield [Types::TEXT, AbstractMySQLPlatform::LENGTH_LIMIT_TINYTEXT];
        yield [Types::TEXT, AbstractMySQLPlatform::LENGTH_LIMIT_TEXT];
        yield [Types::TEXT, AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMTEXT];
    }

    /** @throws Exception */
    private function createLobTable(string $typeName, int $length): Table
    {
        $table = Table::editor()
            ->setUnquotedName('comparator_test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('lob')
                    ->setTypeName($typeName)
                    ->setLength($length)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        return $table;
    }

    /** @throws Exception */
    private function setBlobLength(Table $table, int $length): Table
    {
        return $table->edit()
            ->modifyColumnByUnquotedName('lob', static function (ColumnEditor $editor) use ($length): void {
                $editor->setLength($length);
            })
            ->create();
    }

    public function testExplicitDefaultCollation(): void
    {
        $table = $this->createCollationTable()
            ->edit()
            ->modifyColumnByUnquotedName('id', static function (ColumnEditor $editor): void {
                $editor->setCollation('utf8mb4_general_ci');
            })
            ->create();

        self::assertTrue(ComparatorTestUtils::diffFromActualToDesiredTable(
            $this->schemaManager,
            $this->comparator,
            $table,
        )->isEmpty());

        self::assertTrue(ComparatorTestUtils::diffFromDesiredToActualTable(
            $this->schemaManager,
            $this->comparator,
            $table,
        )->isEmpty());
    }

    public function testChangeColumnCharsetAndCollation(): void
    {
        $table = $this->createCollationTable()
            ->edit()
            ->modifyColumnByUnquotedName('id', static function (ColumnEditor $editor): void {
                $editor
                    ->setCharset('latin1')
                    ->setCollation('latin1_bin');
            })
            ->create();

        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }

    public function testChangeColumnCollation(): void
    {
        $table = $this->createCollationTable()
            ->edit()
            ->modifyColumnByUnquotedName('id', static function (ColumnEditor $editor): void {
                $editor->setCollation('utf8mb4_bin');
            })
            ->create();

        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }

    /**
     * @param array<string,string> $tableOptions
     * @param ?non-empty-string    $columnCharset
     * @param ?non-empty-string    $columnCollation
     */
    #[DataProvider('tableAndColumnOptionsProvider')]
    public function testTableAndColumnOptions(
        array $tableOptions,
        ?string $columnCharset,
        ?string $columnCollation,
    ): void {
        $table = Table::editor()
            ->setUnquotedName('comparator_test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setCharset($columnCharset)
                    ->setCollation($columnCollation)
                    ->create(),
            )
            ->setOptions($tableOptions)
            ->create();

        $this->dropAndCreateTable($table);

        self::assertTrue(ComparatorTestUtils::diffFromActualToDesiredTable(
            $this->schemaManager,
            $this->comparator,
            $table,
        )->isEmpty());

        self::assertTrue(ComparatorTestUtils::diffFromDesiredToActualTable(
            $this->schemaManager,
            $this->comparator,
            $table,
        )->isEmpty());
    }

    /** @return iterable<string,array{array<string,string>,?non-empty-string,?non-empty-string}> */
    public static function tableAndColumnOptionsProvider(): iterable
    {
        yield "Column collation explicitly set to its table's default" => [
            [],
            null,
            'utf8mb4_general_ci',
        ];

        yield "Column charset implicitly set to a value matching its table's charset" => [
            ['charset' => 'utf8mb4'],
            null,
            'utf8mb4_general_ci',
        ];

        yield "Column collation reset to the collation's default matching its table's charset" => [
            ['collation' => 'utf8mb4_unicode_ci'],
            'utf8mb4',
            null,
        ];
    }

    public function testMariaDb1043NativeJsonUpgradeDetected(): void
    {
        if (! $this->platform instanceof AbstractMySQLPlatform) {
            self::markTestSkipped();
        }

        $table = Table::editor()
            ->setUnquotedName('mariadb_json_upgrade')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('json_col')
                    ->setTypeName(Types::JSON)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        // Revert column to old LONGTEXT declaration
        $sql = 'ALTER TABLE mariadb_json_upgrade CHANGE json_col json_col LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'';
        $this->connection->executeStatement($sql);

        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }

    private function createCollationTable(): Table
    {
        $table = Table::editor()
            ->setUnquotedName('comparator_test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->create(),
            )
            ->setOptions([
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
            ])
            ->create();

        $this->dropAndCreateTable($table);

        return $table;
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\MySQL;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

final class ComparatorTest extends FunctionalTestCase
{
    private AbstractPlatform $platform;

    private AbstractSchemaManager $schemaManager;

    private Comparator $comparator;

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

    public function testSimpleArrayTypeNonChangeNotDetected(): void
    {
        $table = Table::editor()
            ->setUnquotedName('comparator_test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('simple_array_col')
                    ->setTypeName(Types::SIMPLE_ARRAY)
                    ->setLength(255)
                    ->create(),
            )
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
        if (! $this->platform instanceof MariaDBPlatform && ! $this->platform instanceof MySQL80Platform) {
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

    public function testIndexPrefixLengthEqualToColumnLengthGeneratesNoDiff(): void
    {
        // https://github.com/doctrine/dbal/issues/5989 MySQL silently drops such a prefix on creation
        $table = new Table('comparator_test');
        $table->addColumn('my_col', Types::STRING, ['length' => 20]);
        $table->addIndex(['my_col'], 'idx_col', [], ['lengths' => [20]]);

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

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
        $this->setBlobLength($table, $length);

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
        $this->setBlobLength($table, $length + 1);
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
    private function createLobTable(string $type, int $length): Table
    {
        $table = new Table('comparator_test');
        $table->addColumn('lob', $type)->setLength($length);

        $this->dropAndCreateTable($table);

        return $table;
    }

    /** @throws Exception */
    private function setBlobLength(Table $table, int $length): void
    {
        $table->getColumn('lob')->setLength($length);
    }

    public function testExplicitDefaultCollation(): void
    {
        [$table, $column] = $this->createCollationTable();
        $column->setPlatformOption('collation', 'utf8mb4_general_ci');

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
        [$table, $column] = $this->createCollationTable();
        $column->setPlatformOption('charset', 'latin1');
        $column->setPlatformOption('collation', 'latin1_bin');

        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }

    public function testChangeColumnCollation(): void
    {
        [$table, $column] = $this->createCollationTable();
        $column->setPlatformOption('collation', 'utf8mb4_bin');

        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }

    /**
     * @param array<string,string> $tableOptions
     * @param array<string,string> $columnOptions
     */
    #[DataProvider('tableAndColumnOptionsProvider')]
    public function testTableAndColumnOptions(array $tableOptions, array $columnOptions): void
    {
        $table = new Table('comparator_test', [], [], [], [], $tableOptions);
        $table->addColumn('name', Types::STRING, [
            'length' => 32,
            'platformOptions' => $columnOptions,
        ]);

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
        $table = new Table('comparator_test');

        $table->addColumn('simple_array_col', Types::SIMPLE_ARRAY, ['length' => 255]);
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

    /** @return iterable<string,array{array<string,string>,array<string,string>}> */
    public static function tableAndColumnOptionsProvider(): iterable
    {
        yield "Column collation explicitly set to its table's default" => [
            [],
            ['collation' => 'utf8mb4_unicode_ci'],
        ];

        yield "Column charset implicitly set to a value matching its table's charset" => [
            ['charset' => 'utf8mb4'],
            ['collation' => 'utf8mb4_unicode_ci'],
        ];

        yield "Column collation reset to the collation's default matching its table's charset" => [
            ['collation' => 'utf8mb4_unicode_ci'],
            ['charset' => 'utf8mb4'],
        ];
    }

    public function testMariaDb1043NativeJsonUpgradeDetected(): void
    {
        if (! $this->platform instanceof MariaDBPlatform && ! $this->platform instanceof MySQL80Platform) {
            self::markTestSkipped();
        }

        $table = new Table('mariadb_json_upgrade');

        $table->addColumn('json_col', Types::JSON);
        $this->dropAndCreateTable($table);

        // Revert column to old LONGTEXT declaration
        $sql = 'ALTER TABLE mariadb_json_upgrade CHANGE json_col json_col LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'';
        $this->connection->executeStatement($sql);

        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }

    /**
     * @return array{Table,Column}
     *
     * @throws Exception
     */
    private function createCollationTable(): array
    {
        $table = new Table('comparator_test');
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collation', 'utf8mb4_general_ci');
        $column = $table->addColumn('id', Types::STRING, ['length' => 32]);
        $this->dropAndCreateTable($table);

        return [$table, $column];
    }
}

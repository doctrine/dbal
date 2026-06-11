<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\Types;
use Override;

class OracleSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    #[Override]
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof OraclePlatform;
    }

    /**
     * Oracle currently stores VARBINARY columns as RAW (fixed-size)
     */
    #[Override]
    protected function assertVarBinaryColumnIsValid(Table $table, string $columnName, int $expectedLength): void
    {
        $column = $table->getColumn($columnName);
        self::assertInstanceOf(BinaryType::class, $column->getType());
        self::assertSame($expectedLength, $column->getLength());
    }

    public function testAlterTableColumnNotNull(): void
    {
        $table = Table::editor()
            ->setUnquotedName('list_table_column_notnull')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        [$id, $foo, $bar] = $this->schemaManager->introspectTableColumnsByUnquotedName('list_table_column_notnull');

        self::assertTrue($id->getNotnull());
        self::assertTrue($foo->getNotnull());
        self::assertTrue($bar->getNotnull());

        $diffTable = $table->edit()
            ->modifyColumnByUnquotedName('foo', static function (ColumnEditor $editor): void {
                $editor->setNotNull(false);
            })
            ->modifyColumnByUnquotedName('bar', static function (ColumnEditor $editor): void {
                $editor->setLength(1024);
            })
            ->create();

        $diff = $this->schemaManager->createComparator()
            ->compareTables($table, $diffTable);

        $this->schemaManager->alterTable($diff);

        [$id, $foo, $bar] = $this->schemaManager->introspectTableColumnsByUnquotedName('list_table_column_notnull');

        self::assertTrue($id->getNotnull());
        self::assertFalse($foo->getNotnull());
        self::assertTrue($bar->getNotnull());
    }

    public function testListTableColumnsSameTableNamesInDifferentSchemas(): void
    {
        $table = $this->createListTableColumns();
        $this->dropAndCreateTable($table);

        $otherTable = Table::editor()
            ->setName($table->getObjectName())
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->create(),
            )
            ->create();

        $connection    = TestUtil::getPrivilegedConnection();
        $schemaManager = $connection->createSchemaManager();

        try {
            $schemaManager->dropTable(
                $otherTable->getObjectName()
                    ->toSQL($connection->getDatabasePlatform()),
            );
        } catch (DatabaseObjectNotFoundException) {
        }

        $schemaManager->createTable($otherTable);
        $connection->close();

        $columns = $this->schemaManager->introspectTableColumns(
            $table->getObjectName(),
        );

        self::assertCount(7, $columns);
    }

    public function testListTableDateTypeColumns(): void
    {
        $table = Table::editor()
            ->setUnquotedName('tbl_date')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_date')
                    ->setTypeName(Types::DATE_MUTABLE)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_datetime')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_datetimetz')
                    ->setTypeName(Types::DATETIMETZ_MUTABLE)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        [$colDate, $colDateTime, $colDateTimeTz]
            = $this->schemaManager->introspectTableColumnsByUnquotedName('tbl_date');

        self::assertInstanceOf(DateType::class, $colDate->getType());
        self::assertInstanceOf(DateTimeType::class, $colDateTime->getType());
        self::assertInstanceOf(DateTimeTzType::class, $colDateTimeTz->getType());
    }

    #[Override]
    public function testCreateAndListSequences(): void
    {
        self::markTestSkipped(
            "Skipped for uppercase letters are contained in sequences' names. Fix the schema manager in 3.0.",
        );
    }

    public function testQuotedTableNameRemainsQuotedInSchema(): void
    {
        $table = Table::editor()
            ->setQuotedName('tester')
            ->setColumns(
                Column::editor()
                    ->setQuotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setQuotedName('name')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $schemaManager = $this->connection->createSchemaManager();

        $oldSchema = $schemaManager->introspectSchema();
        $newSchema = $oldSchema->edit()
            ->modifyTableByUnquotedName('tester', static function (TableEditor $editor): void {
                $editor->dropColumnByUnquotedName('name');
            })
            ->create();

        $diff = $schemaManager->createComparator()
            ->compareSchemas($oldSchema, $newSchema);

        $schemaManager->alterSchema($diff);

        $columns = $schemaManager->introspectTableColumnsByQuotedName('tester');
        self::assertCount(1, $columns);
    }

    #[Override]
    public function getExpectedDefaultSchemaName(): ?string
    {
        return null;
    }
}

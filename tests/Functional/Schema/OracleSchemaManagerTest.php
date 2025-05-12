<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\Types;

class OracleSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof OraclePlatform;
    }

    /**
     * Oracle currently stores VARBINARY columns as RAW (fixed-size)
     */
    protected function assertVarBinaryColumnIsValid(Table $table, string $columnName, int $expectedLength): void
    {
        $column = $table->getColumn($columnName);
        self::assertInstanceOf(BinaryType::class, $column->getType());
        self::assertSame($expectedLength, $column->getLength());
    }

    public function testAlterTableColumnNotNull(): void
    {
        $tableName = 'list_table_column_notnull';
        $table     = new Table($tableName, [
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
        ]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertTrue($columns['id']->getNotnull());
        self::assertTrue($columns['foo']->getNotnull());
        self::assertTrue($columns['bar']->getNotnull());

        $diffTable = clone $table;
        $diffTable->modifyColumn('foo', ['notnull' => false]);
        $diffTable->modifyColumn('bar', ['length' => 1024]);

        $diff = $this->schemaManager->createComparator()
            ->compareTables($table, $diffTable);

        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertTrue($columns['id']->getNotnull());
        self::assertFalse($columns['foo']->getNotnull());
        self::assertTrue($columns['bar']->getNotnull());
    }

    public function testListTableColumnsSameTableNamesInDifferentSchemas(): void
    {
        $table = $this->createListTableColumns();
        $this->dropAndCreateTable($table);

        $otherTable = new Table($table->getName(), [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->create(),
        ]);

        $connection    = TestUtil::getPrivilegedConnection();
        $schemaManager = $connection->createSchemaManager();

        try {
            $schemaManager->dropTable($otherTable->getName());
        } catch (DatabaseObjectNotFoundException) {
        }

        $schemaManager->createTable($otherTable);
        $connection->close();

        $columns = $this->schemaManager->listTableColumns($table->getName());
        self::assertCount(7, $columns);
    }

    public function testListTableDateTypeColumns(): void
    {
        $table = new Table('tbl_date', [
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
        ]);

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('tbl_date');

        self::assertInstanceOf(DateType::class, $columns['col_date']->getType());
        self::assertInstanceOf(DateTimeType::class, $columns['col_datetime']->getType());
        self::assertInstanceOf(DateTimeTzType::class, $columns['col_datetimetz']->getType());
    }

    public function testCreateAndListSequences(): void
    {
        self::markTestSkipped(
            "Skipped for uppercase letters are contained in sequences' names. Fix the schema manager in 3.0.",
        );
    }

    public function testQuotedTableNameRemainsQuotedInSchema(): void
    {
        $table = new Table('"tester"', [
            Column::editor()
                ->setQuotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setQuotedName('name')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->create(),
        ]);

        $this->dropAndCreateTable($table);

        $schemaManager = $this->connection->createSchemaManager();

        $oldSchema = $schemaManager->introspectSchema();
        $newSchema = clone $oldSchema;

        $newSchema->getTable('"tester"')->dropColumn('"name"');
        $diff = $schemaManager->createComparator()
            ->compareSchemas($oldSchema, $newSchema);

        $schemaManager->alterSchema($diff);

        $columns = $schemaManager->listTableColumns('"tester"');
        self::assertCount(1, $columns);
    }

    public function getExpectedDefaultSchemaName(): ?string
    {
        return null;
    }
}

<?php

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\Types;

use function array_map;

class OracleSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof OraclePlatform;
    }

    public function testRenameTable(): void
    {
        $this->createTestTable('list_tables_test');
        $this->dropTableIfExists('list_tables_test_new_name');
        $this->schemaManager->renameTable('list_tables_test', 'list_tables_test_new_name');

        $tables = $this->schemaManager->listTables();

        self::assertHasTable($tables);
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

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider \Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils::comparatorProvider
     */
    public function testAlterTableColumnNotNull(callable $comparatorFactory): void
    {
        $tableName = 'list_table_column_notnull';
        $table     = new Table($tableName);

        $table->addColumn('id', 'integer');
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'string');
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertTrue($columns['id']->getNotnull());
        self::assertTrue($columns['foo']->getNotnull());
        self::assertTrue($columns['bar']->getNotnull());

        $diffTable = clone $table;
        $diffTable->changeColumn('foo', ['notnull' => false]);
        $diffTable->changeColumn('bar', ['length' => 1024]);

        $diff = $comparatorFactory($this->schemaManager)->diffTable($table, $diffTable);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertTrue($columns['id']->getNotnull());
        self::assertFalse($columns['foo']->getNotnull());
        self::assertTrue($columns['bar']->getNotnull());
    }

    public function testListTableDetailsWithDifferentIdentifierQuotingRequirements(): void
    {
        $primaryTableName    = '"Primary_Table"';
        $offlinePrimaryTable = new Table($primaryTableName);
        $offlinePrimaryTable->addColumn(
            '"Id"',
            'integer',
            ['autoincrement' => true, 'comment' => 'Explicit casing.']
        );
        $offlinePrimaryTable->addColumn('select', 'integer', ['comment' => 'Reserved keyword.']);
        $offlinePrimaryTable->addColumn('foo', 'integer', ['comment' => 'Implicit uppercasing.']);
        $offlinePrimaryTable->addColumn('BAR', 'integer');
        $offlinePrimaryTable->addColumn('"BAZ"', 'integer');
        $offlinePrimaryTable->addIndex(['select'], 'from');
        $offlinePrimaryTable->addIndex(['foo'], 'foo_index');
        $offlinePrimaryTable->addIndex(['BAR'], 'BAR_INDEX');
        $offlinePrimaryTable->addIndex(['"BAZ"'], 'BAZ_INDEX');
        $offlinePrimaryTable->setPrimaryKey(['"Id"']);

        $foreignTableName    = 'foreign';
        $offlineForeignTable = new Table($foreignTableName);
        $offlineForeignTable->addColumn('id', 'integer', ['autoincrement' => true]);
        $offlineForeignTable->addColumn('"Fk"', 'integer');
        $offlineForeignTable->addIndex(['"Fk"'], '"Fk_index"');
        $offlineForeignTable->addForeignKeyConstraint(
            $primaryTableName,
            ['"Fk"'],
            ['"Id"'],
            [],
            '"Primary_Table_Fk"'
        );
        $offlineForeignTable->setPrimaryKey(['id']);

        $this->dropTableIfExists($foreignTableName);
        $this->dropTableIfExists($primaryTableName);

        $this->schemaManager->createTable($offlinePrimaryTable);
        $this->schemaManager->createTable($offlineForeignTable);

        $onlinePrimaryTable = $this->schemaManager->listTableDetails($primaryTableName);
        $onlineForeignTable = $this->schemaManager->listTableDetails($foreignTableName);

        $platform = $this->schemaManager->getDatabasePlatform();

        // Primary table assertions
        self::assertSame($primaryTableName, $onlinePrimaryTable->getQuotedName($platform));

        self::assertTrue($onlinePrimaryTable->hasColumn('"Id"'));
        self::assertSame('"Id"', $onlinePrimaryTable->getColumn('"Id"')->getQuotedName($platform));
        self::assertTrue($onlinePrimaryTable->hasPrimaryKey());

        $primaryKey = $onlinePrimaryTable->getPrimaryKey();

        self::assertNotNull($primaryKey);
        self::assertSame(['"Id"'], $primaryKey->getQuotedColumns($platform));

        self::assertTrue($onlinePrimaryTable->hasColumn('select'));
        self::assertSame('"select"', $onlinePrimaryTable->getColumn('select')->getQuotedName($platform));

        self::assertTrue($onlinePrimaryTable->hasColumn('foo'));
        self::assertSame('FOO', $onlinePrimaryTable->getColumn('foo')->getQuotedName($platform));

        self::assertTrue($onlinePrimaryTable->hasColumn('BAR'));
        self::assertSame('BAR', $onlinePrimaryTable->getColumn('BAR')->getQuotedName($platform));

        self::assertTrue($onlinePrimaryTable->hasColumn('"BAZ"'));
        self::assertSame('BAZ', $onlinePrimaryTable->getColumn('"BAZ"')->getQuotedName($platform));

        self::assertTrue($onlinePrimaryTable->hasIndex('from'));
        self::assertTrue($onlinePrimaryTable->getIndex('from')->hasColumnAtPosition('"select"'));
        self::assertSame(['"select"'], $onlinePrimaryTable->getIndex('from')->getQuotedColumns($platform));

        self::assertTrue($onlinePrimaryTable->hasIndex('foo_index'));
        self::assertTrue($onlinePrimaryTable->getIndex('foo_index')->hasColumnAtPosition('foo'));
        self::assertSame(['FOO'], $onlinePrimaryTable->getIndex('foo_index')->getQuotedColumns($platform));

        self::assertTrue($onlinePrimaryTable->hasIndex('BAR_INDEX'));
        self::assertTrue($onlinePrimaryTable->getIndex('BAR_INDEX')->hasColumnAtPosition('BAR'));
        self::assertSame(['BAR'], $onlinePrimaryTable->getIndex('BAR_INDEX')->getQuotedColumns($platform));

        self::assertTrue($onlinePrimaryTable->hasIndex('BAZ_INDEX'));
        self::assertTrue($onlinePrimaryTable->getIndex('BAZ_INDEX')->hasColumnAtPosition('"BAZ"'));
        self::assertSame(['BAZ'], $onlinePrimaryTable->getIndex('BAZ_INDEX')->getQuotedColumns($platform));

        // Foreign table assertions
        self::assertTrue($onlineForeignTable->hasColumn('id'));
        self::assertSame('ID', $onlineForeignTable->getColumn('id')->getQuotedName($platform));
        self::assertTrue($onlineForeignTable->hasPrimaryKey());

        $primaryKey = $onlineForeignTable->getPrimaryKey();

        self::assertNotNull($primaryKey);
        self::assertSame(['ID'], $primaryKey->getQuotedColumns($platform));

        self::assertTrue($onlineForeignTable->hasColumn('"Fk"'));
        self::assertSame('"Fk"', $onlineForeignTable->getColumn('"Fk"')->getQuotedName($platform));

        self::assertTrue($onlineForeignTable->hasIndex('"Fk_index"'));
        self::assertTrue($onlineForeignTable->getIndex('"Fk_index"')->hasColumnAtPosition('"Fk"'));
        self::assertSame(['"Fk"'], $onlineForeignTable->getIndex('"Fk_index"')->getQuotedColumns($platform));

        self::assertTrue($onlineForeignTable->hasForeignKey('"Primary_Table_Fk"'));
        self::assertSame(
            $primaryTableName,
            $onlineForeignTable->getForeignKey('"Primary_Table_Fk"')->getQuotedForeignTableName($platform)
        );
        self::assertSame(
            ['"Fk"'],
            $onlineForeignTable->getForeignKey('"Primary_Table_Fk"')->getQuotedLocalColumns($platform)
        );
        self::assertSame(
            ['"Id"'],
            $onlineForeignTable->getForeignKey('"Primary_Table_Fk"')->getQuotedForeignColumns($platform)
        );
    }

    public function testListTableColumnsSameTableNamesInDifferentSchemas(): void
    {
        $table = $this->createListTableColumns();
        $this->dropAndCreateTable($table);

        $otherTable = new Table($table->getName());
        $otherTable->addColumn('id', Types::STRING);

        $connection    = TestUtil::getPrivilegedConnection();
        $schemaManager = $connection->getSchemaManager();

        try {
            $schemaManager->dropTable($otherTable->getName());
        } catch (DatabaseObjectNotFoundException $e) {
        }

        $schemaManager->createTable($otherTable);
        $connection->close();

        $columns = $this->schemaManager->listTableColumns($table->getName(), $this->connection->getDatabase());
        self::assertCount(7, $columns);
    }

    public function testListTableIndexesPrimaryKeyConstraintNameDiffersFromIndexName(): void
    {
        $table = new Table('list_table_indexes_pk_id_test');
        $table->setSchemaConfig($this->schemaManager->createSchemaConfig());
        $table->addColumn('id', 'integer', ['notnull' => true]);
        $table->addUniqueIndex(['id'], 'id_unique_index');
        $this->dropAndCreateTable($table);

        $this->schemaManager->createIndex(
            new Index('id_pk_id_index', ['id'], true, true),
            'list_table_indexes_pk_id_test'
        );

        $tableIndexes = $this->schemaManager->listTableIndexes('list_table_indexes_pk_id_test');

        self::assertArrayHasKey('primary', $tableIndexes, 'listTableIndexes() has to return a "primary" array key.');
        self::assertEquals(['id'], array_map('strtolower', $tableIndexes['primary']->getColumns()));
        self::assertTrue($tableIndexes['primary']->isUnique());
        self::assertTrue($tableIndexes['primary']->isPrimary());
    }

    public function testListTableDateTypeColumns(): void
    {
        $table = new Table('tbl_date');
        $table->addColumn('col_date', 'date');
        $table->addColumn('col_datetime', 'datetime');
        $table->addColumn('col_datetimetz', 'datetimetz');

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('tbl_date');

        self::assertSame('date', $columns['col_date']->getType()->getName());
        self::assertSame('datetime', $columns['col_datetime']->getType()->getName());
        self::assertSame('datetimetz', $columns['col_datetimetz']->getType()->getName());
    }

    public function testCreateAndListSequences(): void
    {
        self::markTestSkipped(
            "Skipped for uppercase letters are contained in sequences' names. Fix the schema manager in 3.0."
        );
    }
}

<?php

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\View;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_map;
use function array_merge;
use function array_pop;
use function array_unshift;
use function assert;
use function count;
use function preg_match;
use function strtolower;

class PostgreSQLSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof PostgreSQLPlatform;
    }

    public function testGetSearchPath(): void
    {
        $expected = ['public'];

        $params = $this->connection->getParams();

        if (isset($params['user'])) {
            array_unshift($expected, $params['user']);
        }

        self::assertEquals($expected, $this->schemaManager->getSchemaSearchPaths());
    }

    public function testGetSchemaNames(): void
    {
        assert($this->schemaManager instanceof PostgreSQLSchemaManager);

        $names = $this->schemaManager->getSchemaNames();

        self::assertContains('public', $names, 'The public schema should be found.');
    }

    public function testSupportDomainTypeFallback(): void
    {
        $createDomainTypeSQL = 'CREATE DOMAIN MyMoney AS DECIMAL(18,2)';
        $this->connection->executeStatement($createDomainTypeSQL);

        $createTableSQL = 'CREATE TABLE domain_type_test (id INT PRIMARY KEY, value MyMoney)';
        $this->connection->executeStatement($createTableSQL);

        $table = $this->connection->getSchemaManager()->listTableDetails('domain_type_test');
        self::assertInstanceOf(DecimalType::class, $table->getColumn('value')->getType());

        Type::addType('MyMoney', MoneyType::class);
        $this->connection->getDatabasePlatform()->registerDoctrineTypeMapping('MyMoney', 'MyMoney');

        $table = $this->connection->getSchemaManager()->listTableDetails('domain_type_test');
        self::assertInstanceOf(MoneyType::class, $table->getColumn('value')->getType());
    }

    public function testDetectsAutoIncrement(): void
    {
        $autoincTable = new Table('autoinc_table');
        $column       = $autoincTable->addColumn('id', 'integer');
        $column->setAutoincrement(true);
        $this->dropAndCreateTable($autoincTable);
        $autoincTable = $this->schemaManager->listTableDetails('autoinc_table');

        self::assertTrue($autoincTable->getColumn('id')->getAutoincrement());
    }

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider \Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils::comparatorProvider
     */
    public function testAlterTableAutoIncrementAdd(callable $comparatorFactory): void
    {
        // see https://github.com/doctrine/dbal/issues/4745
        try {
            $this->schemaManager->dropSequence('autoinc_table_add_id_seq');
        } catch (DatabaseObjectNotFoundException $e) {
        }

        $tableFrom = new Table('autoinc_table_add');
        $tableFrom->addColumn('id', 'integer');
        $this->dropAndCreateTable($tableFrom);
        $tableFrom = $this->schemaManager->listTableDetails('autoinc_table_add');
        self::assertFalse($tableFrom->getColumn('id')->getAutoincrement());

        $tableTo = new Table('autoinc_table_add');
        $column  = $tableTo->addColumn('id', 'integer');
        $column->setAutoincrement(true);

        $platform = $this->schemaManager->getDatabasePlatform();
        $diff     = $comparatorFactory($this->schemaManager)->diffTable($tableFrom, $tableTo);
        self::assertNotFalse($diff);

        $sql = $platform->getAlterTableSQL($diff);
        self::assertEquals([
            'CREATE SEQUENCE autoinc_table_add_id_seq',
            "SELECT setval('autoinc_table_add_id_seq', (SELECT MAX(id) FROM autoinc_table_add))",
            "ALTER TABLE autoinc_table_add ALTER id SET DEFAULT nextval('autoinc_table_add_id_seq')",
        ], $sql);

        $this->schemaManager->alterTable($diff);
        $tableFinal = $this->schemaManager->listTableDetails('autoinc_table_add');
        self::assertTrue($tableFinal->getColumn('id')->getAutoincrement());
    }

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider \Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils::comparatorProvider
     */
    public function testAlterTableAutoIncrementDrop(callable $comparatorFactory): void
    {
        $tableFrom = new Table('autoinc_table_drop');
        $column    = $tableFrom->addColumn('id', 'integer');
        $column->setAutoincrement(true);
        $this->dropAndCreateTable($tableFrom);
        $tableFrom = $this->schemaManager->listTableDetails('autoinc_table_drop');
        self::assertTrue($tableFrom->getColumn('id')->getAutoincrement());

        $tableTo = new Table('autoinc_table_drop');
        $tableTo->addColumn('id', 'integer');

        $platform = $this->schemaManager->getDatabasePlatform();
        $diff     = $comparatorFactory($this->schemaManager)->diffTable($tableFrom, $tableTo);
        self::assertNotFalse($diff);

        self::assertEquals(
            ['ALTER TABLE autoinc_table_drop ALTER id DROP DEFAULT'],
            $platform->getAlterTableSQL($diff)
        );

        $this->schemaManager->alterTable($diff);
        $tableFinal = $this->schemaManager->listTableDetails('autoinc_table_drop');
        self::assertFalse($tableFinal->getColumn('id')->getAutoincrement());
    }

    public function testTableWithSchema(): void
    {
        $this->connection->executeStatement('CREATE SCHEMA nested');

        $nestedRelatedTable = new Table('nested.schemarelated');
        $column             = $nestedRelatedTable->addColumn('id', 'integer');
        $column->setAutoincrement(true);
        $nestedRelatedTable->setPrimaryKey(['id']);

        $nestedSchemaTable = new Table('nested.schematable');
        $column            = $nestedSchemaTable->addColumn('id', 'integer');
        $column->setAutoincrement(true);
        $nestedSchemaTable->setPrimaryKey(['id']);
        $nestedSchemaTable->addForeignKeyConstraint($nestedRelatedTable, ['id'], ['id']);

        $this->schemaManager->createTable($nestedRelatedTable);
        $this->schemaManager->createTable($nestedSchemaTable);

        $tables = $this->schemaManager->listTableNames();
        self::assertContains('nested.schematable', $tables, 'The table should be detected with its non-public schema.');

        $nestedSchemaTable = $this->schemaManager->listTableDetails('nested.schematable');
        self::assertTrue($nestedSchemaTable->hasColumn('id'));

        $primaryKey = $nestedSchemaTable->getPrimaryKey();

        self::assertNotNull($primaryKey);
        self::assertEquals(['id'], $primaryKey->getColumns());

        $relatedFks = $nestedSchemaTable->getForeignKeys();
        self::assertCount(1, $relatedFks);
        $relatedFk = array_pop($relatedFks);
        self::assertEquals('nested.schemarelated', $relatedFk->getForeignTableName());
    }

    public function testReturnQuotedAssets(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS dbal91_something');

        $sql = 'create table dbal91_something'
            . ' (id integer CONSTRAINT id_something PRIMARY KEY NOT NULL, "table" integer)';
        $this->connection->executeStatement($sql);

        $sql = 'ALTER TABLE dbal91_something ADD CONSTRAINT something_input'
            . ' FOREIGN KEY( "table" ) REFERENCES dbal91_something ON UPDATE CASCADE;';
        $this->connection->executeStatement($sql);

        $table = $this->schemaManager->listTableDetails('dbal91_something');

        self::assertEquals(
            [
                'CREATE TABLE dbal91_something (id INT NOT NULL, "table" INT DEFAULT NULL, PRIMARY KEY(id))',
                'CREATE INDEX IDX_A9401304ECA7352B ON dbal91_something ("table")',
            ],
            $this->connection->getDatabasePlatform()->getCreateTableSQL($table)
        );
    }

    public function testFilterSchemaExpression(): void
    {
        $testTable = new Table('dbal204_test_prefix');
        $testTable->addColumn('id', 'integer');
        $this->dropAndCreateTable($testTable);

        $testTable = new Table('dbal204_without_prefix');
        $testTable->addColumn('id', 'integer');
        $this->dropAndCreateTable($testTable);

        $this->markConnectionNotReusable();

        $this->connection->getConfiguration()->setSchemaAssetsFilter(static function (string $name): bool {
            return preg_match('#^dbal204_#', $name) === 1;
        });
        $names = $this->schemaManager->listTableNames();
        self::assertCount(2, $names);

        $this->connection->getConfiguration()->setSchemaAssetsFilter(static function (string $name): bool {
            return preg_match('#^dbal204_test#', $name) === 1;
        });
        $names = $this->schemaManager->listTableNames();
        self::assertCount(1, $names);
    }

    public function testListForeignKeys(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            self::markTestSkipped('Does not support foreign key constraints.');
        }

        $fkOptions   = ['SET NULL', 'SET DEFAULT', 'NO ACTION', 'CASCADE', 'RESTRICT'];
        $foreignKeys = [];
        $fkTable     = $this->getTestTable('test_create_fk1');
        for ($i = 0; $i < count($fkOptions); $i++) {
            $fkTable->addColumn('foreign_key_test' . $i, 'integer');
            $foreignKeys[] = new ForeignKeyConstraint(
                ['foreign_key_test' . $i],
                'test_create_fk2',
                ['id'],
                'foreign_key_test' . $i . '_fk',
                ['onDelete' => $fkOptions[$i]]
            );
        }

        $this->dropAndCreateTable($fkTable);
        $this->createTestTable('test_create_fk2');

        foreach ($foreignKeys as $foreignKey) {
            $this->schemaManager->createForeignKey($foreignKey, 'test_create_fk1');
        }

        $fkeys = $this->schemaManager->listTableForeignKeys('test_create_fk1');
        self::assertEquals(count($foreignKeys), count($fkeys));

        for ($i = 0; $i < count($fkeys); $i++) {
            self::assertEquals(['foreign_key_test' . $i], array_map('strtolower', $fkeys[$i]->getLocalColumns()));
            self::assertEquals(['id'], array_map('strtolower', $fkeys[$i]->getForeignColumns()));
            self::assertEquals('test_create_fk2', strtolower($fkeys[0]->getForeignTableName()));
            if ($foreignKeys[$i]->getOption('onDelete') === 'NO ACTION') {
                self::assertFalse($fkeys[$i]->hasOption('onDelete'));
            } else {
                self::assertEquals($foreignKeys[$i]->getOption('onDelete'), $fkeys[$i]->getOption('onDelete'));
            }
        }
    }

    public function testDefaultValueCharacterVarying(): void
    {
        $testTable = new Table('dbal511_default');
        $testTable->addColumn('id', 'integer');
        $testTable->addColumn('def', 'string', ['default' => 'foo']);
        $testTable->setPrimaryKey(['id']);
        $this->dropAndCreateTable($testTable);

        $databaseTable = $this->schemaManager->listTableDetails($testTable->getName());

        self::assertEquals('foo', $databaseTable->getColumn('def')->getDefault());
    }

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider \Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils::comparatorProvider
     */
    public function testBooleanDefault(callable $comparatorFactory): void
    {
        $table = new Table('ddc2843_bools');
        $table->addColumn('id', 'integer');
        $table->addColumn('checked', 'boolean', ['default' => false]);

        $this->dropAndCreateTable($table);

        $databaseTable = $this->schemaManager->listTableDetails($table->getName());

        $diff = $comparatorFactory($this->schemaManager)->diffTable($table, $databaseTable);

        self::assertFalse($diff);
    }

    /**
     * PostgreSQL stores BINARY columns as BLOB
     */
    protected function assertBinaryColumnIsValid(Table $table, string $columnName, int $expectedLength): void
    {
        self::assertInstanceOf(BlobType::class, $table->getColumn($columnName)->getType());
    }

    /**
     * PostgreSQL stores VARBINARY columns as BLOB
     */
    protected function assertVarBinaryColumnIsValid(Table $table, string $columnName, int $expectedLength): void
    {
        self::assertInstanceOf(BlobType::class, $table->getColumn($columnName)->getType());
    }

    public function testListQuotedTable(): void
    {
        $offlineTable = new Table('user');
        $offlineTable->addColumn('id', 'integer');
        $offlineTable->addColumn('username', 'string');
        $offlineTable->addColumn('fk', 'integer');
        $offlineTable->setPrimaryKey(['id']);
        $offlineTable->addForeignKeyConstraint($offlineTable, ['fk'], ['id']);

        $this->dropAndCreateTable($offlineTable);

        $onlineTable = $this->schemaManager->listTableDetails('"user"');

        $comparator = new Comparator();

        self::assertFalse($comparator->diffTable($offlineTable, $onlineTable));
    }

    public function testListTableDetailsWhenCurrentSchemaNameQuoted(): void
    {
        $this->connection->executeStatement('CREATE SCHEMA "001_test"');
        $this->connection->executeStatement('SET search_path TO "001_test"');

        try {
            $this->testListQuotedTable();
        } finally {
            $this->connection->close();
        }
    }

    public function testListTablesExcludesViews(): void
    {
        $this->createTestTable('list_tables_excludes_views');

        $name = 'list_tables_excludes_views_test_view';
        $sql  = 'SELECT * from list_tables_excludes_views';

        $view = new View($name, $sql);

        $this->schemaManager->createView($view);

        $tables = $this->schemaManager->listTables();

        $foundTable = false;
        foreach ($tables as $table) {
            self::assertInstanceOf(Table::class, $table, 'No Table instance was found in tables array.');
            if (strtolower($table->getName()) !== 'list_tables_excludes_views_test_view') {
                continue;
            }

            $foundTable = true;
        }

        self::assertFalse($foundTable, 'View "list_tables_excludes_views_test_view" must not be found in table list');
    }

    public function testPartialIndexes(): void
    {
        $offlineTable = new Table('person');
        $offlineTable->addColumn('id', 'integer');
        $offlineTable->addColumn('name', 'string');
        $offlineTable->addColumn('email', 'string');
        $offlineTable->addUniqueIndex(['id', 'name'], 'simple_partial_index', ['where' => '(id IS NULL)']);

        $this->dropAndCreateTable($offlineTable);

        $onlineTable = $this->schemaManager->listTableDetails('person');

        $comparator = new Comparator();

        self::assertFalse($comparator->diffTable($offlineTable, $onlineTable));
        self::assertTrue($onlineTable->hasIndex('simple_partial_index'));
        self::assertTrue($onlineTable->getIndex('simple_partial_index')->hasOption('where'));
        self::assertSame('(id IS NULL)', $onlineTable->getIndex('simple_partial_index')->getOption('where'));
    }

    public function testJsonbColumn(): void
    {
        $table = new Table('test_jsonb');
        $table->addColumn('foo', Types::JSON)->setPlatformOption('jsonb', true);
        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('test_jsonb');

        self::assertSame(Types::JSON, $columns['foo']->getType()->getName());
        self::assertTrue(true, $columns['foo']->getPlatformOption('jsonb'));
    }

    public function testListNegativeColumnDefaultValue(): void
    {
        $table = new Table('test_default_negative');
        $table->addColumn('col_smallint', 'smallint', ['default' => -1]);
        $table->addColumn('col_integer', 'integer', ['default' => -1]);
        $table->addColumn('col_bigint', 'bigint', ['default' => -1]);
        $table->addColumn('col_float', 'float', ['default' => -1.1]);
        $table->addColumn('col_decimal', 'decimal', ['default' => -1.1]);
        $table->addColumn('col_string', 'string', ['default' => '(-1)']);

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('test_default_negative');

        self::assertEquals(-1, $columns['col_smallint']->getDefault());
        self::assertEquals(-1, $columns['col_integer']->getDefault());
        self::assertEquals(-1, $columns['col_bigint']->getDefault());
        self::assertEquals(-1.1, $columns['col_float']->getDefault());
        self::assertEquals(-1.1, $columns['col_decimal']->getDefault());
        self::assertEquals('(-1)', $columns['col_string']->getDefault());
    }

    /**
     * @return mixed[][]
     */
    public static function serialTypes(): iterable
    {
        return [
            ['integer'],
            ['bigint'],
        ];
    }

    /**
     * @dataProvider serialTypes
     */
    public function testAutoIncrementCreatesSerialDataTypesWithoutADefaultValue(string $type): void
    {
        $tableName = 'test_serial_type_' . $type;

        $table = new Table($tableName);
        $table->addColumn('id', $type, ['autoincrement' => true, 'notnull' => false]);

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertNull($columns['id']->getDefault());
    }

    /**
     * @dataProvider serialTypes
     */
    public function testAutoIncrementCreatesSerialDataTypesWithoutADefaultValueEvenWhenDefaultIsSet(string $type): void
    {
        $tableName = 'test_serial_type_with_default_' . $type;

        $table = new Table($tableName);
        $table->addColumn('id', $type, ['autoincrement' => true, 'notnull' => false, 'default' => 1]);

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertNull($columns['id']->getDefault());
    }

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider autoIncrementTypeMigrations
     */
    public function testAlterTableAutoIncrementIntToBigInt(
        callable $comparatorFactory,
        string $from,
        string $to,
        string $expected
    ): void {
        $tableFrom = new Table('autoinc_type_modification');
        $column    = $tableFrom->addColumn('id', $from);
        $column->setAutoincrement(true);
        $this->dropAndCreateTable($tableFrom);
        $tableFrom = $this->schemaManager->listTableDetails('autoinc_type_modification');
        self::assertTrue($tableFrom->getColumn('id')->getAutoincrement());

        $tableTo = new Table('autoinc_type_modification');
        $column  = $tableTo->addColumn('id', $to);
        $column->setAutoincrement(true);

        $diff = $comparatorFactory($this->schemaManager)->diffTable($tableFrom, $tableTo);
        self::assertInstanceOf(TableDiff::class, $diff);
        self::assertSame(
            ['ALTER TABLE autoinc_type_modification ALTER id TYPE ' . $expected],
            $this->connection->getDatabasePlatform()->getAlterTableSQL($diff)
        );

        $this->schemaManager->alterTable($diff);
        $tableFinal = $this->schemaManager->listTableDetails('autoinc_type_modification');
        self::assertTrue($tableFinal->getColumn('id')->getAutoincrement());
    }

    /**
     * @return iterable<mixed[]>
     */
    public static function autoIncrementTypeMigrations(): iterable
    {
        foreach (ComparatorTestUtils::comparatorProvider() as $comparatorArguments) {
            foreach (
                [
                    'int -> bigint' => ['integer', 'bigint', 'BIGINT'],
                    'bigint -> int' => ['bigint', 'integer', 'INT'],
                ] as $testArguments
            ) {
                yield array_merge($comparatorArguments, $testArguments);
            }
        }
    }
}

class MoneyType extends Type
{
    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'MyMoney';
    }

    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return 'MyMoney';
    }
}

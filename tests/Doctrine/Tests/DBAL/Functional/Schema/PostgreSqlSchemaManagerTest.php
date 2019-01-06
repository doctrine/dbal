<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Schema;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\Type;
use function array_map;
use function array_pop;
use function count;
use function strtolower;

class PostgreSqlSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function tearDown()
    {
        parent::tearDown();

        if (! $this->connection) {
            return;
        }

        $this->connection->getConfiguration()->setFilterSchemaAssetsExpression(null);
    }

    /**
     * @group DBAL-177
     */
    public function testGetSearchPath()
    {
        $params = $this->connection->getParams();

        $paths = $this->schemaManager->getSchemaSearchPaths();
        self::assertEquals([$params['user'], 'public'], $paths);
    }

    /**
     * @group DBAL-244
     */
    public function testGetSchemaNames()
    {
        $names = $this->schemaManager->getSchemaNames();

        self::assertInternalType('array', $names);
        self::assertNotEmpty($names);
        self::assertContains('public', $names, 'The public schema should be found.');
    }

    /**
     * @group DBAL-21
     */
    public function testSupportDomainTypeFallback()
    {
        $createDomainTypeSQL = 'CREATE DOMAIN MyMoney AS DECIMAL(18,2)';
        $this->connection->exec($createDomainTypeSQL);

        $createTableSQL = 'CREATE TABLE domain_type_test (id INT PRIMARY KEY, value MyMoney)';
        $this->connection->exec($createTableSQL);

        $table = $this->connection->getSchemaManager()->listTableDetails('domain_type_test');
        self::assertInstanceOf(DecimalType::class, $table->getColumn('value')->getType());

        Type::addType('MyMoney', MoneyType::class);
        $this->connection->getDatabasePlatform()->registerDoctrineTypeMapping('MyMoney', 'MyMoney');

        $table = $this->connection->getSchemaManager()->listTableDetails('domain_type_test');
        self::assertInstanceOf(MoneyType::class, $table->getColumn('value')->getType());
    }

    /**
     * @group DBAL-37
     */
    public function testDetectsAutoIncrement()
    {
        $autoincTable = new Table('autoinc_table');
        $column       = $autoincTable->addColumn('id', 'integer');
        $column->setAutoincrement(true);
        $this->schemaManager->createTable($autoincTable);
        $autoincTable = $this->schemaManager->listTableDetails('autoinc_table');

        self::assertTrue($autoincTable->getColumn('id')->getAutoincrement());
    }

    /**
     * @group DBAL-37
     */
    public function testAlterTableAutoIncrementAdd()
    {
        $tableFrom = new Table('autoinc_table_add');
        $column    = $tableFrom->addColumn('id', 'integer');
        $this->schemaManager->createTable($tableFrom);
        $tableFrom = $this->schemaManager->listTableDetails('autoinc_table_add');
        self::assertFalse($tableFrom->getColumn('id')->getAutoincrement());

        $tableTo = new Table('autoinc_table_add');
        $column  = $tableTo->addColumn('id', 'integer');
        $column->setAutoincrement(true);

        $c    = new Comparator();
        $diff = $c->diffTable($tableFrom, $tableTo);
        $sql  = $this->connection->getDatabasePlatform()->getAlterTableSQL($diff);
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
     * @group DBAL-37
     */
    public function testAlterTableAutoIncrementDrop()
    {
        $tableFrom = new Table('autoinc_table_drop');
        $column    = $tableFrom->addColumn('id', 'integer');
        $column->setAutoincrement(true);
        $this->schemaManager->createTable($tableFrom);
        $tableFrom = $this->schemaManager->listTableDetails('autoinc_table_drop');
        self::assertTrue($tableFrom->getColumn('id')->getAutoincrement());

        $tableTo = new Table('autoinc_table_drop');
        $column  = $tableTo->addColumn('id', 'integer');

        $c    = new Comparator();
        $diff = $c->diffTable($tableFrom, $tableTo);
        self::assertInstanceOf(TableDiff::class, $diff, 'There should be a difference and not false being returned from the table comparison');
        self::assertEquals(['ALTER TABLE autoinc_table_drop ALTER id DROP DEFAULT'], $this->connection->getDatabasePlatform()->getAlterTableSQL($diff));

        $this->schemaManager->alterTable($diff);
        $tableFinal = $this->schemaManager->listTableDetails('autoinc_table_drop');
        self::assertFalse($tableFinal->getColumn('id')->getAutoincrement());
    }

    /**
     * @group DBAL-75
     */
    public function testTableWithSchema()
    {
        $this->connection->exec('CREATE SCHEMA nested');

        $nestedRelatedTable = new Table('nested.schemarelated');
        $column             = $nestedRelatedTable->addColumn('id', 'integer');
        $column->setAutoincrement(true);
        $nestedRelatedTable->setPrimaryKey(['id']);

        $nestedSchemaTable = new Table('nested.schematable');
        $column            = $nestedSchemaTable->addColumn('id', 'integer');
        $column->setAutoincrement(true);
        $nestedSchemaTable->setPrimaryKey(['id']);
        $nestedSchemaTable->addUnnamedForeignKeyConstraint($nestedRelatedTable, ['id'], ['id']);

        $this->schemaManager->createTable($nestedRelatedTable);
        $this->schemaManager->createTable($nestedSchemaTable);

        $tables = $this->schemaManager->listTableNames();
        self::assertContains('nested.schematable', $tables, 'The table should be detected with its non-public schema.');

        $nestedSchemaTable = $this->schemaManager->listTableDetails('nested.schematable');
        self::assertTrue($nestedSchemaTable->hasColumn('id'));
        self::assertEquals(['id'], $nestedSchemaTable->getPrimaryKey()->getColumns());

        $relatedFks = $nestedSchemaTable->getForeignKeys();
        self::assertCount(1, $relatedFks);
        $relatedFk = array_pop($relatedFks);
        self::assertEquals('nested.schemarelated', $relatedFk->getForeignTableName());
    }

    /**
     * @group DBAL-91
     * @group DBAL-88
     */
    public function testReturnQuotedAssets()
    {
        $sql = 'create table dbal91_something ( id integer  CONSTRAINT id_something PRIMARY KEY NOT NULL  ,"table"   integer );';
        $this->connection->exec($sql);

        $sql = 'ALTER TABLE dbal91_something ADD CONSTRAINT something_input FOREIGN KEY( "table" ) REFERENCES dbal91_something ON UPDATE CASCADE;';
        $this->connection->exec($sql);

        $table = $this->schemaManager->listTableDetails('dbal91_something');

        self::assertEquals(
            [
                'CREATE TABLE dbal91_something (id INT NOT NULL, "table" INT DEFAULT NULL, PRIMARY KEY(id))',
                'CREATE INDEX IDX_A9401304ECA7352B ON dbal91_something ("table")',
            ],
            $this->connection->getDatabasePlatform()->getCreateTableSQL($table)
        );
    }

    /**
     * @group DBAL-204
     */
    public function testFilterSchemaExpression()
    {
        $testTable = new Table('dbal204_test_prefix');
        $column    = $testTable->addColumn('id', 'integer');
        $this->schemaManager->createTable($testTable);
        $testTable = new Table('dbal204_without_prefix');
        $column    = $testTable->addColumn('id', 'integer');
        $this->schemaManager->createTable($testTable);

        $this->connection->getConfiguration()->setFilterSchemaAssetsExpression('#^dbal204_#');
        $names = $this->schemaManager->listTableNames();
        self::assertCount(2, $names);

        $this->connection->getConfiguration()->setFilterSchemaAssetsExpression('#^dbal204_test#');
        $names = $this->schemaManager->listTableNames();
        self::assertCount(1, $names);
    }

    public function testListForeignKeys()
    {
        if (! $this->connection->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('Does not support foreign key constraints.');
        }

        $fkOptions   = ['SET NULL', 'SET DEFAULT', 'NO ACTION','CASCADE', 'RESTRICT'];
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
        $this->schemaManager->dropAndCreateTable($fkTable);
        $this->createTestTable('test_create_fk2');

        foreach ($foreignKeys as $foreignKey) {
            $this->schemaManager->createForeignKey($foreignKey, 'test_create_fk1');
        }
        $fkeys = $this->schemaManager->listTableForeignKeys('test_create_fk1');
        self::assertEquals(count($foreignKeys), count($fkeys), "Table 'test_create_fk1' has to have " . count($foreignKeys) . ' foreign keys.');
        for ($i = 0; $i < count($fkeys); $i++) {
            self::assertEquals(['foreign_key_test' . $i], array_map('strtolower', $fkeys[$i]->getLocalColumns()));
            self::assertEquals(['id'], array_map('strtolower', $fkeys[$i]->getForeignColumns()));
            self::assertEquals('test_create_fk2', strtolower($fkeys[0]->getForeignTableName()));
            if ($foreignKeys[$i]->getOption('onDelete') === 'NO ACTION') {
                self::assertFalse($fkeys[$i]->hasOption('onDelete'), 'Unexpected option: ' . $fkeys[$i]->getOption('onDelete'));
            } else {
                self::assertEquals($foreignKeys[$i]->getOption('onDelete'), $fkeys[$i]->getOption('onDelete'));
            }
        }
    }

    /**
     * @group DBAL-511
     */
    public function testDefaultValueCharacterVarying()
    {
        $testTable = new Table('dbal511_default');
        $testTable->addColumn('id', 'integer');
        $testTable->addColumn('def', 'string', ['default' => 'foo']);
        $testTable->setPrimaryKey(['id']);

        $this->schemaManager->createTable($testTable);

        $databaseTable = $this->schemaManager->listTableDetails($testTable->getName());

        self::assertEquals('foo', $databaseTable->getColumn('def')->getDefault());
    }

    /**
     * @group DDC-2843
     */
    public function testBooleanDefault()
    {
        $table = new Table('ddc2843_bools');
        $table->addColumn('id', 'integer');
        $table->addColumn('checked', 'boolean', ['default' => false]);

        $this->schemaManager->createTable($table);

        $databaseTable = $this->schemaManager->listTableDetails($table->getName());

        $c    = new Comparator();
        $diff = $c->diffTable($table, $databaseTable);

        self::assertFalse($diff);
    }

    public function testListTableWithBinary()
    {
        $tableName = 'test_binary_table';

        $table = new Table($tableName);
        $table->addColumn('id', 'integer');
        $table->addColumn('column_varbinary', 'binary', []);
        $table->addColumn('column_binary', 'binary', ['fixed' => true]);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $table = $this->schemaManager->listTableDetails($tableName);

        self::assertInstanceOf(BlobType::class, $table->getColumn('column_varbinary')->getType());
        self::assertFalse($table->getColumn('column_varbinary')->getFixed());

        self::assertInstanceOf(BlobType::class, $table->getColumn('column_binary')->getType());
        self::assertFalse($table->getColumn('column_binary')->getFixed());
    }

    public function testListQuotedTable()
    {
        $offlineTable = new Schema\Table('user');
        $offlineTable->addColumn('id', 'integer');
        $offlineTable->addColumn('username', 'string');
        $offlineTable->addColumn('fk', 'integer');
        $offlineTable->setPrimaryKey(['id']);
        $offlineTable->addForeignKeyConstraint($offlineTable, ['fk'], ['id']);

        $this->schemaManager->dropAndCreateTable($offlineTable);

        $onlineTable = $this->schemaManager->listTableDetails('"user"');

        $comparator = new Schema\Comparator();

        self::assertFalse($comparator->diffTable($offlineTable, $onlineTable));
    }

    public function testListTablesExcludesViews()
    {
        $this->createTestTable('list_tables_excludes_views');

        $name = 'list_tables_excludes_views_test_view';
        $sql  = 'SELECT * from list_tables_excludes_views';

        $view = new Schema\View($name, $sql);

        $this->schemaManager->dropAndCreateView($view);

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

    /**
     * @group DBAL-1033
     */
    public function testPartialIndexes()
    {
        $offlineTable = new Schema\Table('person');
        $offlineTable->addColumn('id', 'integer');
        $offlineTable->addColumn('name', 'string');
        $offlineTable->addColumn('email', 'string');
        $offlineTable->addUniqueIndex(['id', 'name'], 'simple_partial_index', ['where' => '(id IS NULL)']);

        $this->schemaManager->dropAndCreateTable($offlineTable);

        $onlineTable = $this->schemaManager->listTableDetails('person');

        $comparator = new Schema\Comparator();

        self::assertFalse($comparator->diffTable($offlineTable, $onlineTable));
        self::assertTrue($onlineTable->hasIndex('simple_partial_index'));
        self::assertTrue($onlineTable->getIndex('simple_partial_index')->hasOption('where'));
        self::assertSame('(id IS NULL)', $onlineTable->getIndex('simple_partial_index')->getOption('where'));
    }

    /**
     * @dataProvider jsonbColumnTypeProvider
     */
    public function testJsonbColumn(string $type) : void
    {
        if (! $this->schemaManager->getDatabasePlatform() instanceof PostgreSQL94Platform) {
            $this->markTestSkipped('Requires PostgresSQL 9.4+');
            return;
        }

        $table = new Schema\Table('test_jsonb');
        $table->addColumn('foo', $type)->setPlatformOption('jsonb', true);
        $this->schemaManager->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('test_jsonb');

        self::assertSame($type, $columns['foo']->getType()->getName());
        self::assertTrue(true, $columns['foo']->getPlatformOption('jsonb'));
    }

    /**
     * @return mixed[][]
     */
    public function jsonbColumnTypeProvider() : array
    {
        return [
            [Type::JSON],
            [Type::JSON_ARRAY],
        ];
    }

    /**
     * @group DBAL-2427
     */
    public function testListNegativeColumnDefaultValue()
    {
        $table = new Schema\Table('test_default_negative');
        $table->addColumn('col_smallint', 'smallint', ['default' => -1]);
        $table->addColumn('col_integer', 'integer', ['default' => -1]);
        $table->addColumn('col_bigint', 'bigint', ['default' => -1]);
        $table->addColumn('col_float', 'float', ['default' => -1.1]);
        $table->addColumn('col_decimal', 'decimal', ['default' => -1.1]);
        $table->addColumn('col_string', 'string', ['default' => '(-1)']);

        $this->schemaManager->dropAndCreateTable($table);

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
    public static function serialTypes() : array
    {
        return [
            ['integer'],
            ['bigint'],
        ];
    }

    /**
     * @dataProvider serialTypes
     * @group 2906
     */
    public function testAutoIncrementCreatesSerialDataTypesWithoutADefaultValue(string $type) : void
    {
        $tableName = 'test_serial_type_' . $type;

        $table = new Schema\Table($tableName);
        $table->addColumn('id', $type, ['autoincrement' => true, 'notnull' => false]);

        $this->schemaManager->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertNull($columns['id']->getDefault());
    }

    /**
     * @dataProvider serialTypes
     * @group 2906
     */
    public function testAutoIncrementCreatesSerialDataTypesWithoutADefaultValueEvenWhenDefaultIsSet(string $type) : void
    {
        $tableName = 'test_serial_type_with_default_' . $type;

        $table = new Schema\Table($tableName);
        $table->addColumn('id', $type, ['autoincrement' => true, 'notnull' => false, 'default' => 1]);

        $this->schemaManager->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertNull($columns['id']->getDefault());
    }

    /**
     * @group 2916
     * @dataProvider autoIncrementTypeMigrations
     */
    public function testAlterTableAutoIncrementIntToBigInt(string $from, string $to, string $expected) : void
    {
        $tableFrom = new Table('autoinc_type_modification');
        $column    = $tableFrom->addColumn('id', $from);
        $column->setAutoincrement(true);
        $this->schemaManager->dropAndCreateTable($tableFrom);
        $tableFrom = $this->schemaManager->listTableDetails('autoinc_type_modification');
        self::assertTrue($tableFrom->getColumn('id')->getAutoincrement());

        $tableTo = new Table('autoinc_type_modification');
        $column  = $tableTo->addColumn('id', $to);
        $column->setAutoincrement(true);

        $c    = new Comparator();
        $diff = $c->diffTable($tableFrom, $tableTo);
        self::assertInstanceOf(TableDiff::class, $diff, 'There should be a difference and not false being returned from the table comparison');
        self::assertSame(['ALTER TABLE autoinc_type_modification ALTER id TYPE ' . $expected], $this->connection->getDatabasePlatform()->getAlterTableSQL($diff));

        $this->schemaManager->alterTable($diff);
        $tableFinal = $this->schemaManager->listTableDetails('autoinc_type_modification');
        self::assertTrue($tableFinal->getColumn('id')->getAutoincrement());
    }

    /**
     * @return mixed[][]
     */
    public function autoIncrementTypeMigrations() : array
    {
        return [
            'int->bigint' => ['integer', 'bigint', 'BIGINT'],
            'bigint->int' => ['bigint', 'integer', 'INT'],
        ];
    }
}

class MoneyType extends Type
{
    public function getName()
    {
        return 'MyMoney';
    }

    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'MyMoney';
    }
}

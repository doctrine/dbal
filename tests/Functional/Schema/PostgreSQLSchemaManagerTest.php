<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\View;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\JsonbType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;
use function version_compare;

class PostgreSQLSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    #[Override]
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof PostgreSQLPlatform;
    }

    public function testGetSchemaNames(): void
    {
        $this->assertUnqualifiedNameListContainsQuotedName(
            'public',
            $this->schemaManager->introspectSchemaNames(),
        );
    }

    public function testSupportDomainTypeFallback(): void
    {
        $createDomainTypeSQL = 'CREATE DOMAIN MyMoney AS DECIMAL(18,2)';
        $this->connection->executeStatement($createDomainTypeSQL);

        $createTableSQL = 'CREATE TABLE domain_type_test (id INT PRIMARY KEY, value MyMoney)';
        $this->connection->executeStatement($createTableSQL);

        $table = $this->connection->createSchemaManager()->introspectTableByUnquotedName('domain_type_test');
        self::assertInstanceOf(DecimalType::class, $table->getColumn('value')->getType());

        Type::addType('MyMoney', MoneyType::class);
        $this->connection->getDatabasePlatform()->registerDoctrineTypeMapping('MyMoney', 'MyMoney');

        $table = $this->connection->createSchemaManager()->introspectTableByUnquotedName('domain_type_test');
        self::assertInstanceOf(MoneyType::class, $table->getColumn('value')->getType());
    }

    public function testAlterTableAutoIncrementAdd(): void
    {
        $tableFrom = Table::editor()
            ->setUnquotedName('autoinc_table_add')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($tableFrom);
        $tableFrom = $this->schemaManager->introspectTableByUnquotedName('autoinc_table_add');
        self::assertFalse($tableFrom->getColumn('id')->getAutoincrement());

        $tableTo = Table::editor()
            ->setUnquotedName('autoinc_table_add')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->create();

        $platform = $this->connection->getDatabasePlatform();
        $diff     = $this->schemaManager->createComparator()
            ->compareTables($tableFrom, $tableTo);

        $this->schemaManager->alterTable($diff);
        $tableFinal = $this->schemaManager->introspectTableByUnquotedName('autoinc_table_add');
        self::assertTrue($tableFinal->getColumn('id')->getAutoincrement());
    }

    public function testAlterTableAutoIncrementDrop(): void
    {
        $tableFrom = Table::editor()
            ->setUnquotedName('autoinc_table_drop')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($tableFrom);
        $tableFrom = $this->schemaManager->introspectTableByUnquotedName('autoinc_table_drop');
        self::assertTrue($tableFrom->getColumn('id')->getAutoincrement());

        $tableTo = Table::editor()
            ->setUnquotedName('autoinc_table_drop')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $platform = $this->connection->getDatabasePlatform();
        $diff     = $this->schemaManager->createComparator()
            ->compareTables($tableFrom, $tableTo);

        $this->schemaManager->alterTable($diff);
        $tableFinal = $this->schemaManager->introspectTableByUnquotedName('autoinc_table_drop');
        self::assertFalse($tableFinal->getColumn('id')->getAutoincrement());
    }

    public function testListSameTableNameColumnsWithDifferentSchema(): void
    {
        $this->connection->executeStatement('CREATE SCHEMA another');
        $table = Table::editor()
            ->setUnquotedName('table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::TEXT)
                    ->create(),
            )
            ->create();

        $this->schemaManager->createTable($table);

        $anotherSchemaTable = Table::editor()
            ->setUnquotedName('table', 'another')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::TEXT)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('email')
                    ->setTypeName(Types::TEXT)
                    ->create(),
            )
            ->create();

        $this->schemaManager->createTable($anotherSchemaTable);

        $table = $this->schemaManager->introspectTableByUnquotedName('table');
        self::assertCount(2, $table->getColumns());
        self::assertTrue($table->hasColumn('id'));
        self::assertInstanceOf(IntegerType::class, $table->getColumn('id')->getType());
        self::assertTrue($table->hasColumn('name'));

        $anotherSchemaTable = $this->schemaManager->introspectTableByUnquotedName('table', 'another');
        self::assertCount(2, $anotherSchemaTable->getColumns());
        self::assertTrue($anotherSchemaTable->hasColumn('id'));
        self::assertInstanceOf(TextType::class, $anotherSchemaTable->getColumn('id')->getType());
        self::assertTrue($anotherSchemaTable->hasColumn('email'));
    }

    public function testDefaultValueCharacterVarying(): void
    {
        $testTable = Table::editor()
            ->setUnquotedName('dbal511_default')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('def')
                    ->setTypeName(Types::STRING)
                    ->setDefaultValue('foo')
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($testTable);

        $databaseTable = $this->schemaManager->introspectTable($testTable->getObjectName());

        self::assertEquals('foo', $databaseTable->getColumn('def')->getDefault());
    }

    public function testJsonDefaultValue(): void
    {
        $testTable = Table::editor()
            ->setUnquotedName('test_json')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::JSON)
                    ->setDefaultValue('{"key": "value with a single quote \' in string value"}')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($testTable);

        [$column] = $this->schemaManager->introspectTableColumnsByUnquotedName('test_json');

        self::assertSame(Type::getType(Types::JSON), $column->getType());
        self::assertSame('{"key": "value with a single quote \' in string value"}', $column->getDefault());
    }

    public function testBooleanDefault(): void
    {
        $table = Table::editor()
            ->setUnquotedName('ddc2843_bools')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('checked')
                    ->setTypeName(Types::BOOLEAN)
                    ->setDefaultValue(false)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $databaseTable = $this->schemaManager->introspectTable($table->getObjectName());

        self::assertTrue(
            $this->schemaManager->createComparator()
                ->compareTables($table, $databaseTable)
                ->isEmpty(),
        );
    }

    public function testGeneratedColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('ddc6198_generated_always_as')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('idIsOdd')
                    ->setTypeName(Types::BOOLEAN)
                    ->setColumnDefinition('boolean GENERATED ALWAYS AS (id % 2 = 1) STORED')
                    ->setNotNull(false)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $databaseTable = $this->schemaManager->introspectTable($table->getObjectName());

        self::assertTrue(
            $this->schemaManager->createComparator()
                ->compareTables($table, $databaseTable)
                ->isEmpty(),
        );
    }

    /**
     * PostgreSQL stores BINARY columns as BLOB
     */
    #[Override]
    protected function assertBinaryColumnIsValid(Table $table, string $columnName, int $expectedLength): void
    {
        self::assertInstanceOf(BlobType::class, $table->getColumn($columnName)->getType());
    }

    /**
     * PostgreSQL stores VARBINARY columns as BLOB
     */
    #[Override]
    protected function assertVarBinaryColumnIsValid(Table $table, string $columnName, int $expectedLength): void
    {
        self::assertInstanceOf(BlobType::class, $table->getColumn($columnName)->getType());
    }

    /**
     * Although this test would pass in isolation on any platform, we keep it here for the following reasons:
     *
     * 1. The DBAL currently doesn't properly drop tables in the namespaces that need to be quoted
     *    (@see testListTableDetailsWhenCurrentSchemaNameQuoted()).
     * 2. The schema returned by {@see AbstractSchemaManager::introspectSchema()} doesn't contain views, so
     *    {@see AbstractSchemaManager::dropSchemaObjects()} cannot drop the tables that have dependent views
     *    (@see testListTablesExcludesViews()).
     * 3. In the case of inheritance, PHPUnit runs the tests declared immediately in the test class
     *    and then runs the tests declared in the parent.
     *
     * This test needs to be executed before the ones it conflicts with, so it has to be declared in the same class.
     */
    public function testDropWithAutoincrement(): void
    {
        $this->dropTableIfExists('test_autoincrement');

        $table = Table::editor()
            ->setUnquotedName('test_autoincrement')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $schema = new Schema([$table]);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);

        $schema = $schemaManager->introspectSchema();
        $schemaManager->dropSchemaObjects($schema);

        self::assertFalse($schemaManager->tablesExist(['test_autoincrement']));
    }

    public function testListTableDetailsWhenCurrentSchemaNameQuoted(): void
    {
        $this->connection->executeStatement('CREATE SCHEMA "001_test"');
        $this->connection->executeStatement('SET search_path TO "001_test"');
        $this->markConnectionNotReusable();

        $this->testIntrospectReservedKeywordTableViaListTableDetails();
    }

    public function testListTablesExcludesViews(): void
    {
        $this->createTestTable('list_tables_excludes_views');

        $name = 'list_tables_excludes_views_test_view';
        $sql  = 'SELECT * from list_tables_excludes_views';

        $view = View::editor()
            ->setUnquotedName($name)
            ->setSQL($sql)
            ->create();

        $this->schemaManager->createView($view);

        $tables = $this->schemaManager->introspectTables();

        $foundTable = $this->findObjectByName($tables, $view->getObjectName());

        self::assertNull($foundTable, 'View "list_tables_excludes_views_test_view" must not be found in table list');
    }

    public function testPartialIndexes(): void
    {
        $offlineTable = Table::editor()
            ->setUnquotedName('person')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('email')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('simple_partial_index')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('id', 'name')
                    ->setPredicate('(id IS NULL)')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($offlineTable);

        $onlineTable = $this->schemaManager->introspectTableByUnquotedName('person');

        self::assertTrue(
            $this->schemaManager->createComparator()
                ->compareTables($offlineTable, $onlineTable)
                ->isEmpty(),
        );
        self::assertTrue($onlineTable->hasIndex('simple_partial_index'));
        self::assertSame('(id IS NULL)', $onlineTable->getIndex('simple_partial_index')->getPredicate());
    }

    public function testJsonbColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_jsonb')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::JSONB)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        [$column] = $this->schemaManager->introspectTableColumnsByUnquotedName('test_jsonb');

        self::assertInstanceOf(JsonbType::class, $column->getType());
    }

    public function testListNegativeColumnDefaultValue(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_default_negative')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_smallint')
                    ->setTypeName(Types::SMALLINT)
                    ->setDefaultValue(-1)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_integer')
                    ->setTypeName(Types::INTEGER)
                    ->setDefaultValue(-1)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_bigint')
                    ->setTypeName(Types::BIGINT)
                    ->setDefaultValue(-1)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_float')
                    ->setTypeName(Types::FLOAT)
                    ->setDefaultValue(-1.1)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_smallfloat')
                    ->setTypeName(Types::SMALLFLOAT)
                    ->setDefaultValue(-1.1)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_decimal')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(2)
                    ->setScale(1)
                    ->setDefaultValue(-1.1)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_string')
                    ->setTypeName(Types::STRING)
                    ->setDefaultValue('(-1)')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        [$colSmallInt, $colInteger, $colBigInt, $colFloat, $colSmallFloat, $colDecimal, $colString]
            = $this->schemaManager->introspectTableColumnsByUnquotedName('test_default_negative');

        self::assertEquals(-1, $colSmallInt->getDefault());
        self::assertEquals(-1, $colInteger->getDefault());
        self::assertEquals(-1, $colBigInt->getDefault());
        self::assertEquals(-1.1, $colFloat->getDefault());
        self::assertEquals(-1.1, $colSmallFloat->getDefault());
        self::assertEquals(-1.1, $colDecimal->getDefault());
        self::assertEquals('(-1)', $colString->getDefault());
    }

    /** @return mixed[][] */
    public static function serialTypes(): iterable
    {
        return [
            [Types::INTEGER],
            [Types::BIGINT],
        ];
    }

    #[DataProvider('serialTypes')]
    public function testAutoIncrementCreatesSerialDataTypesWithoutADefaultValue(string $typeName): void
    {
        $tableName = 'test_serial_type_' . $typeName;

        $table = Table::editor()
            ->setUnquotedName($tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName($typeName)
                    ->setAutoincrement(true)
                    ->setNotNull(false)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        [$id] = $this->schemaManager->introspectTableColumnsByUnquotedName($tableName);

        self::assertNull($id->getDefault());
    }

    #[DataProvider('serialTypes')]
    public function testAutoIncrementCreatesSerialDataTypesWithoutADefaultValueEvenWhenDefaultIsSet(string $type): void
    {
        $tableName = 'test_serial_type_with_default_' . $type;

        $table = Table::editor()
            ->setUnquotedName($tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName($type)
                    ->setAutoincrement(true)
                    ->setNotNull(false)
                    ->setDefaultValue(1)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        [$id] = $this->schemaManager->introspectTableColumnsByUnquotedName($tableName);

        self::assertNull($id->getDefault());
    }

    #[DataProvider('autoIncrementTypeMigrations')]
    public function testAlterTableAutoIncrementIntToBigInt(string $from, string $to, string $expected): void
    {
        $table = Table::editor()
            ->setUnquotedName('autoinc_type_modification')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName($from)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $oldTable = $this->schemaManager->introspectTableByUnquotedName('autoinc_type_modification');
        self::assertTrue($oldTable->getColumn('id')->getAutoincrement());

        $newTable = Table::editor()
            ->setUnquotedName('autoinc_type_modification')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName($to)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->create();

        $diff = $this->schemaManager->createComparator()
            ->compareTables($oldTable, $newTable);

        $this->schemaManager->alterTable($diff);
        $tableFinal = $this->schemaManager->introspectTableByUnquotedName('autoinc_type_modification');
        self::assertTrue($tableFinal->getColumn('id')->getAutoincrement());
    }

    public function testListTableColumnsOidConflictWithNonTableObject(): void
    {
        if (version_compare($this->connection->getServerVersion(), '12.0', '<')) {
            self::markTestSkipped('Manually setting the Oid is not supported in Postgres 11 and earlier');
        }

        $table = 'test_list_table_columns_oid_conflicts';
        $this->connection->executeStatement(sprintf('CREATE TABLE IF NOT EXISTS %s(id INT NOT NULL)', $table));
        [$beforeColumn] = $this->schemaManager->introspectTableColumnsByUnquotedName($table);
        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('id'),
            $beforeColumn->getObjectName(),
        );

        $this->connection->executeStatement('CREATE EXTENSION IF NOT EXISTS pg_prewarm');
        $originalTableOid = $this->connection->fetchOne(
            'SELECT oid FROM pg_class WHERE pg_class.relname = ?',
            [$table],
        );

        $getConflictingOidSql = <<<'SQL'
SELECT objid
FROM pg_depend
JOIN pg_extension as ex on ex.oid = pg_depend.refobjid
WHERE ex.extname = 'pg_prewarm'
ORDER BY objid
LIMIT 1
SQL;
        $conflictingOid       = $this->connection->fetchOne($getConflictingOidSql);

        $this->connection->executeStatement(
            'UPDATE pg_attribute SET attrelid = ? WHERE attrelid = ?',
            [$conflictingOid, $originalTableOid],
        );
        $this->connection->executeStatement(
            'UPDATE pg_description SET objoid = ? WHERE objoid = ?',
            [$conflictingOid, $originalTableOid],
        );
        $this->connection->executeStatement(
            'UPDATE pg_class SET oid = ? WHERE oid = ?',
            [$conflictingOid, $originalTableOid],
        );

        [$afterColumn] = $this->schemaManager->introspectTableColumnsByUnquotedName($table);

        // revert to the database to original state prior to asserting result
        $this->connection->executeStatement(
            'UPDATE pg_attribute SET attrelid = ? WHERE attrelid = ?',
            [$originalTableOid, $conflictingOid],
        );
        $this->connection->executeStatement(
            'UPDATE pg_description SET objoid = ? WHERE objoid = ?',
            [$originalTableOid, $conflictingOid],
        );
        $this->connection->executeStatement(
            'UPDATE pg_class SET oid = ? WHERE oid = ?',
            [$originalTableOid, $conflictingOid],
        );
        $this->connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $table));
        $this->connection->executeStatement('DROP EXTENSION IF EXISTS pg_prewarm');

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('id'),
            $beforeColumn->getObjectName(),
        );
    }

    /** @return iterable<mixed[]> */
    public static function autoIncrementTypeMigrations(): iterable
    {
        return [
            'int->bigint' => ['integer', 'bigint', 'BIGINT'],
            'bigint->int' => ['bigint', 'integer', 'INT'],
        ];
    }

    public function testPartitionTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS partitioned_table');
        $this->connection->executeStatement(
            'CREATE TABLE partitioned_table (id INT) PARTITION BY LIST (id);',
        );
        $this->connection->executeStatement('CREATE TABLE partition PARTITION OF partitioned_table FOR VALUES IN (1);');
        try {
            $this->schemaManager->introspectTableByUnquotedName('partition');
        } catch (TableDoesNotExist $e) {
        }

        self::assertNotNull($e ?? null, 'Partition table should not be introspected');

        $tableFrom = $this->schemaManager->introspectTableByUnquotedName('partitioned_table');

        $tableTo = $this->schemaManager->introspectTableByUnquotedName('partitioned_table');
        $tableTo = $tableTo->edit()
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $diff = $this->schemaManager->createComparator()->compareTables($tableFrom, $tableTo);

        $this->schemaManager->alterTable($diff);

        $tableFinal = $this->schemaManager->introspectTableByUnquotedName('partitioned_table');
        self::assertTrue($tableFinal->hasColumn('id'));
        self::assertTrue($tableFinal->hasColumn('foo'));

        $partitionedTableCount = (int) ($this->connection->fetchOne(
            "select count(*) as count from pg_class where relname = 'partitioned_table' and relkind = 'p'",
        ));
        self::assertSame(1, $partitionedTableCount);

        $partitionsCount = (int) ($this->connection->fetchOne(
            <<<'SQL'
            select count(*) as count
            from pg_class parent
            inner join pg_inherits on pg_inherits.inhparent = parent.oid
            inner join pg_class child on pg_inherits.inhrelid = child.oid
                and child.relkind = 'r'
                and child.relname = 'partition'
            where parent.relname = 'partitioned_table' and parent.relkind = 'p';
            SQL,
        ));
        self::assertSame(1, $partitionsCount);
    }

    /** @link https://www.postgresql.org/docs/current/ddl-schemas.html#DDL-SCHEMAS-PUBLIC */
    #[Override]
    public function getExpectedDefaultSchemaName(): string
    {
        return 'public';
    }
}

class MoneyType extends Type
{
    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'MyMoney';
    }
}

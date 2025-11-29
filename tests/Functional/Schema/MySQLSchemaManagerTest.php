<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DatabaseRequired;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Schema\MySQL\CustomType;
use Doctrine\DBAL\Tests\Functional\Schema\MySQL\PointType;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\SmallFloatType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;

use function array_keys;

class MySQLSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    use VerifyDeprecations;

    public static function setUpBeforeClass(): void
    {
        Type::addType('point', PointType::class);
    }

    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof AbstractMySQLPlatform;
    }

    public function testFulltextIndex(): void
    {
        $index = Index::editor()
            ->setUnquotedName('f_index')
            ->setType(IndexType::FULLTEXT)
            ->setUnquotedColumnNames('text')
            ->create();

        $table = Table::editor()
            ->setUnquotedName('fulltext_index')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('text')
                    ->setTypeName(Types::TEXT)
                    ->create(),
            )
            ->setIndexes($index)
            ->setOptions(['engine' => 'MyISAM'])
            ->create();

        $index = $table->getIndex('f_index');
        $index->addFlag('fulltext');

        $this->dropAndCreateTable($table);

        $this->assertIndexListEquals(
            [$index],
            $this->schemaManager->introspectTableIndexesByUnquotedName('fulltext_index'),
        );
    }

    public function testSpatialIndex(): void
    {
        $index = Index::editor()
            ->setUnquotedName('s_index')
            ->setType(IndexType::SPATIAL)
            ->setUnquotedColumnNames('point')
            ->create();

        $table = Table::editor()
            ->setUnquotedName('spatial_index')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('point')
                    ->setTypeName('point')
                    ->create(),
            )
            ->setIndexes($index)
            ->setOptions(['engine' => 'MyISAM'])
            ->create();

        $index = $table->getIndex('s_index');
        $index->addFlag('spatial');

        $this->dropAndCreateTable($table);

        // see https://github.com/doctrine/dbal/issues/4983
        $this->markConnectionNotReusable();

        $this->assertIndexListEquals(
            [$index],
            $this->schemaManager->introspectTableIndexesByUnquotedName('spatial_index'),
        );
    }

    public function testIndexWithLength(): void
    {
        $index = Index::editor()
            ->setUnquotedName('text_index')
            ->setColumns(
                new IndexedColumn(UnqualifiedName::unquoted('text'), 128),
            )
            ->create();

        $table = Table::editor()
            ->setUnquotedName('index_length')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('text')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
            )
            ->setIndexes($index)
            ->create();

        $this->dropAndCreateTable($table);

        $this->assertIndexListEquals(
            [$index],
            $this->schemaManager->introspectTableIndexesByUnquotedName('index_length'),
        );
    }

    public function testDropPrimaryKeyWithAutoincrementColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('drop_primary_key')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id', 'foo')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $diffTable = $table->edit()
            ->dropPrimaryKeyConstraint()
            ->create();

        $diffTable->dropPrimaryKey();

        $diff = $this->schemaManager->createComparator()
            ->compareTables($table, $diffTable);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6841');

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTableByUnquotedName('drop_primary_key');

        self::assertNull($table->getPrimaryKey());
        self::assertFalse($table->getColumn('id')->getAutoincrement());
    }

    public function testDoesNotPropagateDefaultValuesForUnsupportedColumnTypes(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof MariaDBPlatform) {
            self::markTestSkipped(
                'MariaDB supports default values for BLOB and TEXT columns and will propagate values',
            );
        }

        $table = Table::editor()
            ->setUnquotedName('text_blob_default_value')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('def_text')
                    ->setTypeName(Types::TEXT)
                    ->setDefaultValue('def')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('def_text_null')
                    ->setTypeName(Types::TEXT)
                    ->setNotNull(false)
                    ->setDefaultValue('def')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('def_blob')
                    ->setTypeName(Types::BLOB)
                    ->setDefaultValue('def')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('def_blob_null')
                    ->setTypeName(Types::BLOB)
                    ->setNotNull(false)
                    ->setDefaultValue('def')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTableByUnquotedName('text_blob_default_value');

        self::assertNull($onlineTable->getColumn('def_text')->getDefault());
        self::assertNull($onlineTable->getColumn('def_text_null')->getDefault());
        self::assertFalse($onlineTable->getColumn('def_text_null')->getNotnull());
        self::assertNull($onlineTable->getColumn('def_blob')->getDefault());
        self::assertNull($onlineTable->getColumn('def_blob_null')->getDefault());
        self::assertFalse($onlineTable->getColumn('def_blob_null')->getNotnull());

        self::assertTrue(
            $this->schemaManager->createComparator()
                ->compareTables($table, $onlineTable)
                ->isEmpty(),
        );
    }

    public function testColumnCharset(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_column_charset')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::TEXT)
                    ->setCharset('ascii')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::TEXT)
                    ->setCharset('latin1')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        [$id, $foo, $bar] = $this->schemaManager->introspectTableColumnsByUnquotedName('test_column_charset');

        self::assertNull($id->getCharset());
        self::assertEquals('ascii', $foo->getCharset());
        self::assertEquals('latin1', $bar->getCharset());
    }

    public function testAlterColumnCharset(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_alter_column_charset')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_text')
                    ->setTypeName(Types::TEXT)
                    ->setCharset('utf8')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $diffTable = $table->edit()
            ->modifyColumnByUnquotedName('col_text', static function (ColumnEditor $editor): void {
                $editor->setCharset('ascii');
            })
            ->create();

        $diff = $this->schemaManager->createComparator()
            ->compareTables($table, $diffTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTableByUnquotedName('test_alter_column_charset');

        self::assertEquals('ascii', $table->getColumn('col_text')->getCharset());
    }

    public function testColumnCharsetChange(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_column_charset_change')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_string')
                    ->setTypeName(Types::STRING)
                    ->setCharset('utf8')
                    ->setLength(100)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $diffTable = $table->edit()
            ->modifyColumnByUnquotedName('col_string', static function (ColumnEditor $editor): void {
                $editor->setCharset('ascii');
            })
            ->create();

        $diff = $this->schemaManager->createComparator()
            ->compareTables($table, $diffTable);

        $this->schemaManager->alterTable($diff);

        self::assertEquals(
            'ascii',
            $this->schemaManager->introspectTableByUnquotedName('test_column_charset_change')
                ->getColumn('col_string')
                ->getCharset(),
        );
    }

    public function testColumnCollation(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_collation')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('text')
                    ->setTypeName(Types::TEXT)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::TEXT)
                    ->setCollation('latin1_swedish_ci')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::TEXT)
                    ->setCollation('utf8mb4_general_ci')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('baz')
                    ->setTypeName(Types::TEXT)
                    ->setCollation('binary')
                    ->create(),
            )
            ->setOptions([
                'charset' => 'latin1',
                'collation' => 'latin1_swedish_ci',
            ])
            ->create();

        $this->dropAndCreateTable($table);

        [$id, $text, $foo, $bar, $baz]
            = $this->schemaManager->introspectTableColumnsByUnquotedName('test_collation');

        self::assertNull($id->getCollation());
        self::assertEquals('latin1_swedish_ci', $text->getCollation());
        self::assertEquals('latin1_swedish_ci', $foo->getCollation());
        self::assertEquals('utf8mb4_general_ci', $bar->getCollation());
        self::assertInstanceOf(BlobType::class, $baz->getType());
    }

    public function testListLobTypeColumns(): void
    {
        $table = Table::editor()
            ->setUnquotedName('lob_type_columns')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_tinytext')
                    ->setTypeName(Types::TEXT)
                    ->setLength(AbstractMySQLPlatform::LENGTH_LIMIT_TINYTEXT)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_text')
                    ->setTypeName(Types::TEXT)
                    ->setLength(AbstractMySQLPlatform::LENGTH_LIMIT_TEXT)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_mediumtext')
                    ->setTypeName(Types::TEXT)
                    ->setLength(AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMTEXT)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_longtext')
                    ->setTypeName(Types::TEXT)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_tinyblob')
                    ->setTypeName(Types::TEXT)
                    ->setLength(AbstractMySQLPlatform::LENGTH_LIMIT_TINYBLOB)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_blob')
                    ->setTypeName(Types::BLOB)
                    ->setLength(AbstractMySQLPlatform::LENGTH_LIMIT_BLOB)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_mediumblob')
                    ->setTypeName(Types::BLOB)
                    ->setLength(AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMBLOB)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_longblob')
                    ->setTypeName(Types::BLOB)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $platform = $this->connection->getDatabasePlatform();

        [$colTinyText, $colText, $colMediumText, $colLongText, $colTinyBlob, $colBlob, $colMediumBlob, $colLongBlob]
            = $this->schemaManager->introspectTableColumnsByUnquotedName('lob_type_columns');

        self::assertSame(
            $platform->getClobTypeDeclarationSQL($table->getColumn('col_tinytext')->toArray()),
            $platform->getClobTypeDeclarationSQL($colTinyText->toArray()),
        );
        self::assertSame(
            $platform->getClobTypeDeclarationSQL($table->getColumn('col_text')->toArray()),
            $platform->getClobTypeDeclarationSQL($colText->toArray()),
        );
        self::assertSame(
            $platform->getClobTypeDeclarationSQL($table->getColumn('col_mediumtext')->toArray()),
            $platform->getClobTypeDeclarationSQL($colMediumText->toArray()),
        );
        self::assertSame(
            $platform->getClobTypeDeclarationSQL($table->getColumn('col_longtext')->toArray()),
            $platform->getClobTypeDeclarationSQL($colLongText->toArray()),
        );
        self::assertSame(
            $platform->getBlobTypeDeclarationSQL($table->getColumn('col_tinyblob')->toArray()),
            $platform->getBlobTypeDeclarationSQL($colTinyBlob->toArray()),
        );
        self::assertSame(
            $platform->getBlobTypeDeclarationSQL($table->getColumn('col_blob')->toArray()),
            $platform->getBlobTypeDeclarationSQL($colBlob->toArray()),
        );
        self::assertSame(
            $platform->getBlobTypeDeclarationSQL($table->getColumn('col_mediumblob')->toArray()),
            $platform->getBlobTypeDeclarationSQL($colMediumBlob->toArray()),
        );
        self::assertSame(
            $platform->getBlobTypeDeclarationSQL($table->getColumn('col_longblob')->toArray()),
            $platform->getBlobTypeDeclarationSQL($colLongBlob->toArray()),
        );
    }

    public function testDiffListGuidTableColumn(): void
    {
        $offlineTable = Table::editor()
            ->setUnquotedName('list_guid_table_column')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_guid')
                    ->setTypeName(Types::GUID)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($offlineTable);

        $onlineTable = $this->schemaManager->introspectTableByUnquotedName('list_guid_table_column');

        self::assertTrue(
            $this->schemaManager
                ->createComparator()
                ->compareTables($onlineTable, $offlineTable)
                ->isEmpty(),
            'No differences should be detected with the offline vs online schema.',
        );
    }

    public function testListDecimalTypeColumns(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_list_decimal_columns')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(10)
                    ->setScale(6)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_unsigned')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(10)
                    ->setScale(6)
                    ->setUnsigned(true)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        [$signed, $unsigned] = $this->schemaManager->introspectTableColumnsByUnquotedName('test_list_decimal_columns');

        self::assertFalse($signed->getUnsigned());
        self::assertTrue($unsigned->getUnsigned());
    }

    public function testListUnsignedFloatTypeColumns(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_unsigned_float_columns')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_unsigned')
                    ->setTypeName(Types::FLOAT)
                    ->setUnsigned(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_smallfloat_unsigned')
                    ->setTypeName(Types::SMALLFLOAT)
                    ->setUnsigned(true)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        [$colUnsigned, $colSmallFloatUnsigned]
            = $this->schemaManager->introspectTableColumnsByUnquotedName('test_unsigned_float_columns');

        self::assertInstanceOf(FloatType::class, $colUnsigned->getType());
        self::assertInstanceOf(SmallFloatType::class, $colSmallFloatUnsigned->getType());
        self::assertTrue($colUnsigned->getUnsigned());
        self::assertTrue($colSmallFloatUnsigned->getUnsigned());
    }

    public function testConfigurableLengthColumns(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_configurable_length_columns')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_binary')
                    ->setTypeName(Types::BINARY)
                    ->setLength(16)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        [$column] = $this->schemaManager->introspectTableColumnsByUnquotedName('test_configurable_length_columns');

        self::assertInstanceOf(BinaryType::class, $column->getType());
        self::assertSame(16, $column->getLength());
    }

    public function testJsonColumnType(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_mysql_json')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_json')
                    ->setTypeName(Types::JSON)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        [$column] = $this->schemaManager->introspectTableColumnsByUnquotedName('test_mysql_json');

        self::assertInstanceOf(JsonType::class, $column->getType());
    }

    public function testColumnDefaultCurrentTimestamp(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $currentTimeStamp    = new CurrentTimestamp();
        $currentTimeStampSQL = $currentTimeStamp->toSQL($platform);

        $table = Table::editor()
            ->setUnquotedName('test_column_defaults_current_timestamp')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_datetime')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setDefaultValue($currentTimeStamp)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_datetime_nullable')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setDefaultValue($currentTimeStamp)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTableByUnquotedName('test_column_defaults_current_timestamp');
        self::assertSame($currentTimeStampSQL, $onlineTable->getColumn('col_datetime')->getDefault());
        self::assertSame($currentTimeStampSQL, $onlineTable->getColumn('col_datetime_nullable')->getDefault());

        self::assertTrue(
            $this->schemaManager
                ->createComparator()
                ->compareTables($table, $onlineTable)
                ->isEmpty(),
        );
    }

    public function testColumnDefaultsAreValid(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_column_defaults_are_valid')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_datetime_null')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_int')
                    ->setTypeName(Types::INTEGER)
                    ->setDefaultValue(1)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_neg_int')
                    ->setTypeName(Types::INTEGER)
                    ->setDefaultValue(-1)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_string')
                    ->setTypeName(Types::STRING)
                    ->setLength(1)
                    ->setDefaultValue('A')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_decimal')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(6)
                    ->setScale(3)
                    ->setDefaultValue(-2.3)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_date')
                    ->setTypeName(Types::DATE_MUTABLE)
                    ->setDefaultValue('2012-12-12')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->connection->executeStatement(
            'INSERT INTO test_column_defaults_are_valid () VALUES()',
        );

        $row = $this->connection->fetchAssociative('SELECT * FROM test_column_defaults_are_valid');
        self::assertNotFalse($row);

        self::assertNull($row['col_datetime_null']);
        self::assertSame('2012-12-12', $row['col_date']);
        self::assertSame('A', $row['col_string']);
        self::assertEquals(1, $row['col_int']);
        self::assertEquals(-1, $row['col_neg_int']);
        self::assertEquals('-2.300', $row['col_decimal']);
    }

    /**
     * MariaDB 10.2+ does support CURRENT_TIME and CURRENT_DATE as
     * column default values for time and date columns.
     * (Not supported on Mysql as of 5.7.19)
     *
     * Note that MariaDB 10.2+, when storing default in information_schema,
     * silently change CURRENT_TIMESTAMP as 'current_timestamp()',
     * CURRENT_TIME as 'currtime()' and CURRENT_DATE as 'currdate()'.
     * This test also ensure proper aliasing to not trigger a table diff.
     */
    public function testColumnDefaultValuesCurrentTimeAndDate(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof MariaDBPlatform) {
            self::markTestSkipped('Only relevant for MariaDB.');
        }

        $platform = $this->connection->getDatabasePlatform();

        $currentTimestampSql = $platform->getCurrentTimestampSQL();
        $currentTimeSql      = $platform->getCurrentTimeSQL();
        $currentDateSql      = $platform->getCurrentDateSQL();

        $table = Table::editor()
            ->setUnquotedName('test_column_defaults_current_time_and_date')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col_datetime')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setDefaultValue($currentTimestampSql)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_date')
                    ->setTypeName(Types::DATE_MUTABLE)
                    ->setDefaultValue($currentDateSql)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col_time')
                    ->setTypeName(Types::TIME_MUTABLE)
                    ->setDefaultValue($currentTimeSql)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTableByUnquotedName(
            'test_column_defaults_current_time_and_date',
        );

        self::assertSame($currentTimestampSql, $onlineTable->getColumn('col_datetime')->getDefault());
        self::assertSame($currentDateSql, $onlineTable->getColumn('col_date')->getDefault());
        self::assertSame($currentTimeSql, $onlineTable->getColumn('col_time')->getDefault());

        self::assertTrue(
            $this->schemaManager
                ->createComparator()
                ->compareTables($table, $onlineTable)
                ->isEmpty(),
        );
    }

    public function testEnsureTableOptionsAreReflectedInMetadata(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS test_table_metadata');

        $sql = <<<'SQL'
CREATE TABLE test_table_metadata(
  col1 INT NOT NULL AUTO_INCREMENT PRIMARY KEY
)
COLLATE utf8mb4_general_ci
ENGINE InnoDB
ROW_FORMAT DYNAMIC
COMMENT 'This is a test'
AUTO_INCREMENT=42
PARTITION BY HASH (col1)
SQL;

        $this->connection->executeStatement($sql);
        $onlineTable = $this->schemaManager->introspectTableByUnquotedName('test_table_metadata');

        self::assertEquals('InnoDB', $onlineTable->getOption('engine'));
        self::assertEquals('utf8mb4_general_ci', $onlineTable->getOption('collation'));
        self::assertEquals(42, $onlineTable->getOption('autoincrement'));
        self::assertEquals('This is a test', $onlineTable->getOption('comment'));
        self::assertEquals([
            'row_format' => 'DYNAMIC',
            'partitioned' => true,
        ], $onlineTable->getOption('create_options'));
    }

    public function testEnsureTableWithoutOptionsAreReflectedInMetadata(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS test_table_empty_metadata');

        $this->connection->executeStatement('CREATE TABLE test_table_empty_metadata(col1 INT NOT NULL)');
        $onlineTable = $this->schemaManager->introspectTableByUnquotedName('test_table_empty_metadata');

        self::assertNotEmpty($onlineTable->getOption('engine'));
        // collation could be set to default or not set, information_schema indicate a possibly null value
        self::assertFalse($onlineTable->hasOption('autoincrement'));
        self::assertEquals('', $onlineTable->getOption('comment'));
        self::assertEquals([], $onlineTable->getOption('create_options'));
    }

    public function testColumnIntrospection(): void
    {
        $tableEditor = Table::editor()
            ->setUnquotedName('test_column_introspection');

        $doctrineTypes = array_keys(Type::getTypesMap());

        foreach ($doctrineTypes as $type) {
            $columnEditor = Column::editor()
                ->setUnquotedName('col_' . $type)
                ->setTypeName($type);

            $tableEditor->addColumn(
                (match ($type) {
                    Types::ENUM => $columnEditor->setValues(['foo', 'bar']),
                    default => $columnEditor
                        ->setLength(8)
                        ->setPrecision(8)
                        ->setScale(2),
                })->create(),
            );
        }

        $table = $tableEditor->create();

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTableByUnquotedName('test_column_introspection');

        $diff = $this->schemaManager->createComparator()->compareTables($table, $onlineTable);

        self::assertTrue($diff->isEmpty(), 'Tables should be identical.');
    }

    public function testListTableColumnsThrowsDatabaseRequired(): void
    {
        $params = TestUtil::getConnectionParams();
        unset($params['dbname']);

        $connection    = DriverManager::getConnection($params);
        $schemaManager = $connection->createSchemaManager();

        $this->expectException(DatabaseRequired::class);

        $schemaManager->introspectTableColumnsByUnquotedName('users');
    }

    public function testSchemaDiffWithCustomColumnTypeWhileDatabaseTypeDiffers(): void
    {
        Type::addType('custom_type', CustomType::class);

        $metadataTable = Table::editor()
            ->setUnquotedName('table_with_custom_type')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col1')
                    ->setTypeName('custom_type')
                    ->create(),
            )
            ->create();

        self::assertSame(
            ['CREATE TABLE table_with_custom_type (col1 INT NOT NULL)'],
            $this->connection->getDatabasePlatform()->getCreateTableSQL($metadataTable),
        );

        $this->connection->executeStatement('DROP TABLE IF EXISTS table_with_custom_type');

        $this->connection->executeStatement('CREATE TABLE table_with_custom_type (col1 VARCHAR(255) NOT NULL)');
        $onlineTable = $this->schemaManager->introspectTable('table_with_custom_type');

        $comparator = $this->schemaManager->createComparator();
        $tablesDiff = $comparator->compareTables($onlineTable, $metadataTable);

        self::assertSame(
            ['ALTER TABLE table_with_custom_type CHANGE col1 col1 INT NOT NULL'],
            $this->connection->getDatabasePlatform()->getAlterTableSQL($tablesDiff),
            ' The column should be changed from VARCHAR TO INT',
        );
    }

    public function testMigrateOldBoolean(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS table_with_old_boolean');
        $this->connection->executeStatement(
            'CREATE TABLE table_with_old_boolean (val TINYINT(1) NOT NULL)',
        );

        $onlineTable = $this->schemaManager->introspectTableByUnquotedName('table_with_old_boolean');
        $targetTable = Table::editor()
            ->setUnquotedName('table_with_old_boolean')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('val')
                    ->setTypeName(Types::BOOLEAN)
                    ->create(),
            )
            ->create();

        $diff = $this->schemaManager->createComparator()->compareTables($onlineTable, $targetTable);

        self::assertTrue($diff->isEmpty(), 'Tables should be identical.');
    }

    public function getExpectedDefaultSchemaName(): ?string
    {
        return null;
    }
}

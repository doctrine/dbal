<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use DateTime;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DatabaseRequired;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Schema\MySQL\CustomType;
use Doctrine\DBAL\Tests\Functional\Schema\MySQL\PointType;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_keys;

class MySQLSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    public static function setUpBeforeClass(): void
    {
        Type::addType('point', PointType::class);
    }

    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof AbstractMySQLPlatform;
    }

    public function testSwitchPrimaryKeyColumns(): void
    {
        $tableOld = new Table('switch_primary_key_columns');
        $tableOld->addColumn('foo_id', Types::INTEGER);
        $tableOld->addColumn('bar_id', Types::INTEGER);

        $this->schemaManager->createTable($tableOld);
        $tableFetched = $this->schemaManager->introspectTable('switch_primary_key_columns');
        $tableNew     = clone $tableFetched;
        $tableNew->setPrimaryKey(['bar_id', 'foo_id']);

        $diff = $this->schemaManager->createComparator()
            ->compareTables($tableFetched, $tableNew);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('switch_primary_key_columns');

        $primaryKey = $table->getPrimaryKey();
        self::assertNotNull($primaryKey);
        self::assertSame(['bar_id', 'foo_id'], $primaryKey->getColumns());
    }

    public function testFulltextIndex(): void
    {
        $table = new Table('fulltext_index');
        $table->addColumn('text', Types::TEXT);
        $table->addIndex(['text'], 'f_index');
        $table->addOption('engine', 'MyISAM');

        $index = $table->getIndex('f_index');
        $index->addFlag('fulltext');

        $this->dropAndCreateTable($table);

        $indexes = $this->schemaManager->listTableIndexes('fulltext_index');
        self::assertArrayHasKey('f_index', $indexes);
        self::assertTrue($indexes['f_index']->hasFlag('fulltext'));
    }

    public function testSpatialIndex(): void
    {
        $table = new Table('spatial_index');
        $table->addColumn('point', 'point');
        $table->addIndex(['point'], 's_index');
        $table->addOption('engine', 'MyISAM');

        $index = $table->getIndex('s_index');
        $index->addFlag('spatial');

        $this->dropAndCreateTable($table);

        // see https://github.com/doctrine/dbal/issues/4983
        $this->markConnectionNotReusable();

        $indexes = $this->schemaManager->listTableIndexes('spatial_index');
        self::assertArrayHasKey('s_index', $indexes);
        self::assertTrue($indexes['s_index']->hasFlag('spatial'));
        self::assertSame([0 => null], $indexes['s_index']->getOption('lengths'));
    }

    public function testIndexWithLength(): void
    {
        $table = new Table('index_length');
        $table->addColumn('text', Types::STRING, ['length' => 255]);
        $table->addIndex(['text'], 'text_index', [], ['lengths' => [128]]);

        $this->dropAndCreateTable($table);

        $indexes = $this->schemaManager->listTableIndexes('index_length');
        self::assertArrayHasKey('text_index', $indexes);
        self::assertSame([128], $indexes['text_index']->getOption('lengths'));
    }

    public function testAlterTableAddPrimaryKey(): void
    {
        $table = new Table('alter_table_add_pk');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('foo', Types::INTEGER);
        $table->addIndex(['id'], 'idx_id');

        $this->schemaManager->createTable($table);

        $diffTable = clone $table;

        $diffTable->dropIndex('idx_id');
        $diffTable->setPrimaryKey(['id']);

        $diff = $this->schemaManager->createComparator()
            ->compareTables($table, $diffTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('alter_table_add_pk');

        self::assertFalse($table->hasIndex('idx_id'));
        self::assertNotNull($table->getPrimaryKey());
    }

    public function testDropPrimaryKeyWithAutoincrementColumn(): void
    {
        $table = new Table('drop_primary_key');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('foo', Types::INTEGER);
        $table->setPrimaryKey(['id', 'foo']);

        $this->dropAndCreateTable($table);

        $diffTable = clone $table;

        $diffTable->dropPrimaryKey();

        $diff = $this->schemaManager->createComparator()
            ->compareTables($table, $diffTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable('drop_primary_key');

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

        $table = new Table('text_blob_default_value');
        $table->addColumn('def_text', Types::TEXT, ['default' => 'def']);
        $table->addColumn('def_text_null', Types::TEXT, ['notnull' => false, 'default' => 'def']);
        $table->addColumn('def_blob', Types::BLOB, ['default' => 'def']);
        $table->addColumn('def_blob_null', Types::BLOB, ['notnull' => false, 'default' => 'def']);

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTable('text_blob_default_value');

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
        $table = new Table('test_column_charset');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('foo', Types::TEXT)->setPlatformOption('charset', 'ascii');
        $table->addColumn('bar', Types::TEXT)->setPlatformOption('charset', 'latin1');
        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('test_column_charset');

        self::assertFalse($columns['id']->hasPlatformOption('charset'));
        self::assertEquals('ascii', $columns['foo']->getPlatformOption('charset'));
        self::assertEquals('latin1', $columns['bar']->getPlatformOption('charset'));
    }

    public function testAlterColumnCharset(): void
    {
        $tableName = 'test_alter_column_charset';

        $table = new Table($tableName);
        $table->addColumn('col_text', Types::TEXT)->setPlatformOption('charset', 'utf8');

        $this->dropAndCreateTable($table);

        $diffTable = clone $table;
        $diffTable->getColumn('col_text')->setPlatformOption('charset', 'ascii');

        $diff = $this->schemaManager->createComparator()
            ->compareTables($table, $diffTable);

        $this->schemaManager->alterTable($diff);

        $table = $this->schemaManager->introspectTable($tableName);

        self::assertEquals('ascii', $table->getColumn('col_text')->getPlatformOption('charset'));
    }

    public function testColumnCharsetChange(): void
    {
        $table = new Table('test_column_charset_change');
        $table->addColumn('col_string', Types::STRING)
            ->setLength(100)
            ->setNotnull(true)
            ->setPlatformOption('charset', 'utf8');
        $this->dropAndCreateTable($table);

        $diffTable = clone $table;
        $diffTable->getColumn('col_string')->setPlatformOption('charset', 'ascii');

        $diff = $this->schemaManager->createComparator()
            ->compareTables($table, $diffTable);

        $this->schemaManager->alterTable($diff);

        self::assertEquals(
            'ascii',
            $this->schemaManager->introspectTable('test_column_charset_change')
                ->getColumn('col_string')
                ->getPlatformOption('charset'),
        );
    }

    public function testColumnCollation(): void
    {
        $table = new Table('test_collation');
        $table->addOption('collation', 'latin1_swedish_ci');
        $table->addOption('charset', 'latin1');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('text', Types::TEXT);
        $table->addColumn('foo', Types::TEXT)->setPlatformOption('collation', 'latin1_swedish_ci');
        $table->addColumn('bar', Types::TEXT)->setPlatformOption('collation', 'utf8mb4_general_ci');
        $table->addColumn('baz', Types::TEXT)->setPlatformOption('collation', 'binary');
        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('test_collation');

        self::assertArrayNotHasKey('collation', $columns['id']->getPlatformOptions());
        self::assertEquals('latin1_swedish_ci', $columns['text']->getPlatformOption('collation'));
        self::assertEquals('latin1_swedish_ci', $columns['foo']->getPlatformOption('collation'));
        self::assertEquals('utf8mb4_general_ci', $columns['bar']->getPlatformOption('collation'));
        self::assertInstanceOf(BlobType::class, $columns['baz']->getType());
    }

    public function testListLobTypeColumns(): void
    {
        $tableName = 'lob_type_columns';
        $table     = new Table($tableName);

        $table->addColumn('col_tinytext', Types::TEXT, ['length' => AbstractMySQLPlatform::LENGTH_LIMIT_TINYTEXT]);
        $table->addColumn('col_text', Types::TEXT, ['length' => AbstractMySQLPlatform::LENGTH_LIMIT_TEXT]);
        $table->addColumn('col_mediumtext', Types::TEXT, ['length' => AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMTEXT]);
        $table->addColumn('col_longtext', Types::TEXT);

        $table->addColumn('col_tinyblob', Types::TEXT, ['length' => AbstractMySQLPlatform::LENGTH_LIMIT_TINYBLOB]);
        $table->addColumn('col_blob', Types::BLOB, ['length' => AbstractMySQLPlatform::LENGTH_LIMIT_BLOB]);
        $table->addColumn('col_mediumblob', Types::BLOB, ['length' => AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMBLOB]);
        $table->addColumn('col_longblob', Types::BLOB);

        $this->dropAndCreateTable($table);

        $platform      = $this->connection->getDatabasePlatform();
        $onlineColumns = $this->schemaManager->listTableColumns($tableName);

        self::assertSame(
            $platform->getClobTypeDeclarationSQL($table->getColumn('col_tinytext')->toArray()),
            $platform->getClobTypeDeclarationSQL($onlineColumns['col_tinytext']->toArray()),
        );
        self::assertSame(
            $platform->getClobTypeDeclarationSQL($table->getColumn('col_text')->toArray()),
            $platform->getClobTypeDeclarationSQL($onlineColumns['col_text']->toArray()),
        );
        self::assertSame(
            $platform->getClobTypeDeclarationSQL($table->getColumn('col_mediumtext')->toArray()),
            $platform->getClobTypeDeclarationSQL($onlineColumns['col_mediumtext']->toArray()),
        );
        self::assertSame(
            $platform->getClobTypeDeclarationSQL($table->getColumn('col_longtext')->toArray()),
            $platform->getClobTypeDeclarationSQL($onlineColumns['col_longtext']->toArray()),
        );

        self::assertSame(
            $platform->getBlobTypeDeclarationSQL($table->getColumn('col_tinyblob')->toArray()),
            $platform->getBlobTypeDeclarationSQL($onlineColumns['col_tinyblob']->toArray()),
        );
        self::assertSame(
            $platform->getBlobTypeDeclarationSQL($table->getColumn('col_blob')->toArray()),
            $platform->getBlobTypeDeclarationSQL($onlineColumns['col_blob']->toArray()),
        );
        self::assertSame(
            $platform->getBlobTypeDeclarationSQL($table->getColumn('col_mediumblob')->toArray()),
            $platform->getBlobTypeDeclarationSQL($onlineColumns['col_mediumblob']->toArray()),
        );
        self::assertSame(
            $platform->getBlobTypeDeclarationSQL($table->getColumn('col_longblob')->toArray()),
            $platform->getBlobTypeDeclarationSQL($onlineColumns['col_longblob']->toArray()),
        );
    }

    public function testDiffListGuidTableColumn(): void
    {
        $offlineTable = new Table('list_guid_table_column');
        $offlineTable->addColumn('col_guid', Types::GUID);

        $this->dropAndCreateTable($offlineTable);

        $onlineTable = $this->schemaManager->introspectTable('list_guid_table_column');

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
        $tableName = 'test_list_decimal_columns';
        $table     = new Table($tableName);

        $table->addColumn('col', Types::DECIMAL, [
            'precision' => 10,
            'scale' => 6,
        ]);

        $table->addColumn('col_unsigned', Types::DECIMAL, [
            'precision' => 10,
            'scale' => 6,
            'unsigned' => true,
        ]);

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertArrayHasKey('col', $columns);
        self::assertArrayHasKey('col_unsigned', $columns);
        self::assertFalse($columns['col']->getUnsigned());
        self::assertTrue($columns['col_unsigned']->getUnsigned());
    }

    public function testListFloatTypeColumns(): void
    {
        $tableName = 'test_list_float_columns';
        $table     = new Table($tableName);

        $table->addColumn('col', Types::FLOAT);
        $table->addColumn('col_unsigned', Types::FLOAT, ['unsigned' => true]);

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertArrayHasKey('col', $columns);
        self::assertArrayHasKey('col_unsigned', $columns);
        self::assertFalse($columns['col']->getUnsigned());
        self::assertTrue($columns['col_unsigned']->getUnsigned());
    }

    public function testJsonColumnType(): void
    {
        $table = new Table('test_mysql_json');
        $table->addColumn('col_json', Types::JSON);
        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('test_mysql_json');

        self::assertInstanceOf(JsonType::class, $columns['col_json']->getType());
    }

    public function testColumnDefaultCurrentTimestamp(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $table = new Table('test_column_defaults_current_timestamp');

        $currentTimeStampSql = $platform->getCurrentTimestampSQL();

        $table->addColumn(
            'col_datetime',
            Types::DATETIME_MUTABLE,
            ['notnull' => true, 'default' => $currentTimeStampSql],
        );
        $table->addColumn('col_datetime_nullable', Types::DATETIME_MUTABLE, ['default' => $currentTimeStampSql]);

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTable('test_column_defaults_current_timestamp');
        self::assertSame($currentTimeStampSql, $onlineTable->getColumn('col_datetime')->getDefault());
        self::assertSame($currentTimeStampSql, $onlineTable->getColumn('col_datetime_nullable')->getDefault());

        self::assertTrue(
            $this->schemaManager
                ->createComparator()
                ->compareTables($table, $onlineTable)
                ->isEmpty(),
        );
    }

    public function testColumnDefaultsAreValid(): void
    {
        $table = new Table('test_column_defaults_are_valid');

        $currentTimeStampSql = $this->connection->getDatabasePlatform()->getCurrentTimestampSQL();
        $table->addColumn('col_datetime', Types::DATETIME_MUTABLE, ['default' => $currentTimeStampSql]);
        $table->addColumn('col_datetime_null', Types::DATETIME_MUTABLE, ['notnull' => false, 'default' => null]);
        $table->addColumn('col_int', Types::INTEGER, ['default' => 1]);
        $table->addColumn('col_neg_int', Types::INTEGER, ['default' => -1]);
        $table->addColumn('col_string', Types::STRING, [
            'length' => 1,
            'default' => 'A',
        ]);
        $table->addColumn('col_decimal', Types::DECIMAL, ['scale' => 3, 'precision' => 6, 'default' => -2.3]);
        $table->addColumn('col_date', Types::DATE_MUTABLE, ['default' => '2012-12-12']);

        $this->dropAndCreateTable($table);

        $this->connection->executeStatement(
            'INSERT INTO test_column_defaults_are_valid () VALUES()',
        );

        $row = $this->connection->fetchAssociative(
            'SELECT *, DATEDIFF(CURRENT_TIMESTAMP(), col_datetime) as diff_seconds FROM test_column_defaults_are_valid',
        );
        self::assertNotFalse($row);

        self::assertInstanceOf(DateTime::class, DateTime::createFromFormat('Y-m-d H:i:s', $row['col_datetime']));
        self::assertNull($row['col_datetime_null']);
        self::assertSame('2012-12-12', $row['col_date']);
        self::assertSame('A', $row['col_string']);
        self::assertEquals(1, $row['col_int']);
        self::assertEquals(-1, $row['col_neg_int']);
        self::assertEquals('-2.300', $row['col_decimal']);
        self::assertLessThan(5, $row['diff_seconds']);
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

        $table = new Table('test_column_defaults_current_time_and_date');

        $currentTimestampSql = $platform->getCurrentTimestampSQL();
        $currentTimeSql      = $platform->getCurrentTimeSQL();
        $currentDateSql      = $platform->getCurrentDateSQL();

        $table->addColumn('col_datetime', Types::DATETIME_MUTABLE, ['default' => $currentTimestampSql]);
        $table->addColumn('col_date', Types::DATE_MUTABLE, ['default' => $currentDateSql]);
        $table->addColumn('col_time', Types::TIME_MUTABLE, ['default' => $currentTimeSql]);

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTable('test_column_defaults_current_time_and_date');

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
        $onlineTable = $this->schemaManager->introspectTable('test_table_metadata');

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
        $onlineTable = $this->schemaManager->introspectTable('test_table_empty_metadata');

        self::assertNotEmpty($onlineTable->getOption('engine'));
        // collation could be set to default or not set, information_schema indicate a possibly null value
        self::assertFalse($onlineTable->hasOption('autoincrement'));
        self::assertEquals('', $onlineTable->getOption('comment'));
        self::assertEquals([], $onlineTable->getOption('create_options'));
    }

    public function testColumnIntrospection(): void
    {
        $table = new Table('test_column_introspection');

        $doctrineTypes = array_keys(Type::getTypesMap());

        foreach ($doctrineTypes as $type) {
            $table->addColumn('col_' . $type, $type, ['length' => 8, 'precision' => 8, 'scale' => 2]);
        }

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTable('test_column_introspection');

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

        $schemaManager->listTableColumns('users');
    }

    public function testSchemaDiffWithCustomColumnTypeWhileDatabaseTypeDiffers(): void
    {
        Type::addType('custom_type', CustomType::class);

        $metadataTable = new Table('table_with_custom_type');
        $metadataTable->addColumn('col1', 'custom_type');

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
            ' The column should be changed from VARCAHR TO INT',
        );
    }
}

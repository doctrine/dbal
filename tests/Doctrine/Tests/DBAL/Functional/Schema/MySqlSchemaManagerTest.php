<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use DateTime;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Tests\Types\MySqlPointType;

class MySqlSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        if (Type::hasType('point')) {
            return;
        }

        $this->resetSharedConn();

        Type::addType('point', MySqlPointType::class);
    }

    public function testSwitchPrimaryKeyColumns() : void
    {
        $tableOld = new Table('switch_primary_key_columns');
        $tableOld->addColumn('foo_id', 'integer');
        $tableOld->addColumn('bar_id', 'integer');

        $this->schemaManager->createTable($tableOld);
        $tableFetched = $this->schemaManager->listTableDetails('switch_primary_key_columns');
        $tableNew     = clone $tableFetched;
        $tableNew->setPrimaryKey(['bar_id', 'foo_id']);

        $comparator = new Comparator();
        $this->schemaManager->alterTable($comparator->diffTable($tableFetched, $tableNew));

        $table      = $this->schemaManager->listTableDetails('switch_primary_key_columns');
        $primaryKey = $table->getPrimaryKeyColumns();

        self::assertCount(2, $primaryKey);
        self::assertContains('bar_id', $primaryKey);
        self::assertContains('foo_id', $primaryKey);
    }

    public function testDiffTableBug() : void
    {
        $schema = new Schema();
        $table  = $schema->createTable('diffbug_routing_translations');
        $table->addColumn('id', 'integer');
        $table->addColumn('route', 'string');
        $table->addColumn('locale', 'string');
        $table->addColumn('attribute', 'string');
        $table->addColumn('localized_value', 'string');
        $table->addColumn('original_value', 'string');
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['route', 'locale', 'attribute']);
        $table->addIndex(['localized_value']); // this is much more selective than the unique index

        $this->schemaManager->createTable($table);
        $tableFetched = $this->schemaManager->listTableDetails('diffbug_routing_translations');

        $comparator = new Comparator();
        $diff       = $comparator->diffTable($tableFetched, $table);

        self::assertFalse($diff, 'no changes expected.');
    }

    public function testFulltextIndex() : void
    {
        $table = new Table('fulltext_index');
        $table->addColumn('text', 'text');
        $table->addIndex(['text'], 'f_index');
        $table->addOption('engine', 'MyISAM');

        $index = $table->getIndex('f_index');
        $index->addFlag('fulltext');

        $this->schemaManager->dropAndCreateTable($table);

        $indexes = $this->schemaManager->listTableIndexes('fulltext_index');
        self::assertArrayHasKey('f_index', $indexes);
        self::assertTrue($indexes['f_index']->hasFlag('fulltext'));
    }

    public function testSpatialIndex() : void
    {
        $table = new Table('spatial_index');
        $table->addColumn('point', 'point');
        $table->addIndex(['point'], 's_index');
        $table->addOption('engine', 'MyISAM');

        $index = $table->getIndex('s_index');
        $index->addFlag('spatial');

        $this->schemaManager->dropAndCreateTable($table);

        $indexes = $this->schemaManager->listTableIndexes('spatial_index');
        self::assertArrayHasKey('s_index', $indexes);
        self::assertTrue($indexes['s_index']->hasFlag('spatial'));
    }

    public function testIndexWithLength() : void
    {
        $table = new Table('index_length');
        $table->addColumn('text', 'string', ['length' => 255]);
        $table->addIndex(['text'], 'text_index', [], ['lengths' => [128]]);

        $this->schemaManager->dropAndCreateTable($table);

        $indexes = $this->schemaManager->listTableIndexes('index_length');
        self::assertArrayHasKey('text_index', $indexes);
        self::assertSame([128], $indexes['text_index']->getOption('lengths'));
    }

    /**
     * @group DBAL-400
     */
    public function testAlterTableAddPrimaryKey() : void
    {
        $table = new Table('alter_table_add_pk');
        $table->addColumn('id', 'integer');
        $table->addColumn('foo', 'integer');
        $table->addIndex(['id'], 'idx_id');

        $this->schemaManager->createTable($table);

        $comparator = new Comparator();
        $diffTable  = clone $table;

        $diffTable->dropIndex('idx_id');
        $diffTable->setPrimaryKey(['id']);

        $this->schemaManager->alterTable($comparator->diffTable($table, $diffTable));

        $table = $this->schemaManager->listTableDetails('alter_table_add_pk');

        self::assertFalse($table->hasIndex('idx_id'));
        self::assertTrue($table->hasPrimaryKey());
    }

    /**
     * @group DBAL-464
     */
    public function testDropPrimaryKeyWithAutoincrementColumn() : void
    {
        $table = new Table('drop_primary_key');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('foo', 'integer');
        $table->setPrimaryKey(['id', 'foo']);

        $this->schemaManager->dropAndCreateTable($table);

        $diffTable = clone $table;

        $diffTable->dropPrimaryKey();

        $comparator = new Comparator();

        $this->schemaManager->alterTable($comparator->diffTable($table, $diffTable));

        $table = $this->schemaManager->listTableDetails('drop_primary_key');

        self::assertFalse($table->hasPrimaryKey());
        self::assertFalse($table->getColumn('id')->getAutoincrement());
    }

    /**
     * @group DBAL-789
     */
    public function testDoesNotPropagateDefaultValuesForUnsupportedColumnTypes() : void
    {
        if ($this->schemaManager->getDatabasePlatform() instanceof MariaDb1027Platform) {
            $this->markTestSkipped(
                'MariaDb102Platform supports default values for BLOB and TEXT columns and will propagate values'
            );
        }

        $table = new Table('text_blob_default_value');
        $table->addColumn('def_text', 'text', ['default' => 'def']);
        $table->addColumn('def_text_null', 'text', ['notnull' => false, 'default' => 'def']);
        $table->addColumn('def_blob', 'blob', ['default' => 'def']);
        $table->addColumn('def_blob_null', 'blob', ['notnull' => false, 'default' => 'def']);

        $this->schemaManager->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->listTableDetails('text_blob_default_value');

        self::assertNull($onlineTable->getColumn('def_text')->getDefault());
        self::assertNull($onlineTable->getColumn('def_text_null')->getDefault());
        self::assertFalse($onlineTable->getColumn('def_text_null')->getNotnull());
        self::assertNull($onlineTable->getColumn('def_blob')->getDefault());
        self::assertNull($onlineTable->getColumn('def_blob_null')->getDefault());
        self::assertFalse($onlineTable->getColumn('def_blob_null')->getNotnull());

        $comparator = new Comparator();

        $this->schemaManager->alterTable($comparator->diffTable($table, $onlineTable));

        $onlineTable = $this->schemaManager->listTableDetails('text_blob_default_value');

        self::assertNull($onlineTable->getColumn('def_text')->getDefault());
        self::assertNull($onlineTable->getColumn('def_text_null')->getDefault());
        self::assertFalse($onlineTable->getColumn('def_text_null')->getNotnull());
        self::assertNull($onlineTable->getColumn('def_blob')->getDefault());
        self::assertNull($onlineTable->getColumn('def_blob_null')->getDefault());
        self::assertFalse($onlineTable->getColumn('def_blob_null')->getNotnull());
    }

    public function testColumnCharset() : void
    {
        $table = new Table('test_column_charset');
        $table->addColumn('id', 'integer');
        $table->addColumn('no_charset', 'text');
        $table->addColumn('foo', 'text')->setPlatformOption('charset', 'ascii');
        $table->addColumn('bar', 'text')->setPlatformOption('charset', 'latin1');
        $this->schemaManager->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('test_column_charset');

        self::assertFalse($columns['id']->hasPlatformOption('charset'));
        self::assertEquals('utf8', $columns['no_charset']->getPlatformOption('charset'));
        self::assertEquals('ascii', $columns['foo']->getPlatformOption('charset'));
        self::assertEquals('latin1', $columns['bar']->getPlatformOption('charset'));
    }

    public function testAlterColumnCharset() : void
    {
        $tableName = 'test_alter_column_charset';

        $table = new Table($tableName);
        $table->addColumn('col_text', 'text')->setPlatformOption('charset', 'utf8');

        $this->schemaManager->dropAndCreateTable($table);

        $diffTable = clone $table;
        $diffTable->getColumn('col_text')->setPlatformOption('charset', 'ascii');

        $comparator = new Comparator();

        $this->schemaManager->alterTable($comparator->diffTable($table, $diffTable));

        $table = $this->schemaManager->listTableDetails($tableName);

        self::assertEquals('ascii', $table->getColumn('col_text')->getPlatformOption('charset'));
    }

    public function testColumnCharsetChange() : void
    {
        $table = new Table('test_column_charset_change');
        $table->addColumn('col_string', 'string')->setLength(100)->setNotnull(true)->setPlatformOption('charset', 'utf8');

        $diffTable = clone $table;
        $diffTable->getColumn('col_string')->setPlatformOption('charset', 'ascii');

        $fromSchema = new Schema([$table]);
        $toSchema   = new Schema([$diffTable]);

        $diff = $fromSchema->getMigrateToSql($toSchema, $this->connection->getDatabasePlatform());
        self::assertContains('ALTER TABLE test_column_charset_change CHANGE col_string col_string VARCHAR(100) CHARACTER SET ascii NOT NULL', $diff);
    }

    public function testColumnCollation() : void
    {
        $table                                  = new Table('test_collation');
        $table->addOption('collate', $collation = 'latin1_swedish_ci');
        $table->addOption('charset', 'latin1');
        $table->addColumn('id', 'integer');
        $table->addColumn('text', 'text');
        $table->addColumn('foo', 'text')->setPlatformOption('collation', 'latin1_swedish_ci');
        $table->addColumn('bar', 'text')->setPlatformOption('collation', 'utf8_general_ci');
        $table->addColumn('baz', 'text')->setPlatformOption('collation', 'binary');
        $this->schemaManager->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('test_collation');

        self::assertArrayNotHasKey('collation', $columns['id']->getPlatformOptions());
        self::assertEquals('latin1_swedish_ci', $columns['text']->getPlatformOption('collation'));
        self::assertEquals('latin1_swedish_ci', $columns['foo']->getPlatformOption('collation'));
        self::assertEquals('utf8_general_ci', $columns['bar']->getPlatformOption('collation'));
        self::assertInstanceOf(BlobType::class, $columns['baz']->getType());
    }

    /**
     * @group DBAL-843
     */
    public function testListLobTypeColumns() : void
    {
        $tableName = 'lob_type_columns';
        $table     = new Table($tableName);

        $table->addColumn('col_tinytext', 'text', ['length' => MySqlPlatform::LENGTH_LIMIT_TINYTEXT]);
        $table->addColumn('col_text', 'text', ['length' => MySqlPlatform::LENGTH_LIMIT_TEXT]);
        $table->addColumn('col_mediumtext', 'text', ['length' => MySqlPlatform::LENGTH_LIMIT_MEDIUMTEXT]);
        $table->addColumn('col_longtext', 'text');

        $table->addColumn('col_tinyblob', 'text', ['length' => MySqlPlatform::LENGTH_LIMIT_TINYBLOB]);
        $table->addColumn('col_blob', 'blob', ['length' => MySqlPlatform::LENGTH_LIMIT_BLOB]);
        $table->addColumn('col_mediumblob', 'blob', ['length' => MySqlPlatform::LENGTH_LIMIT_MEDIUMBLOB]);
        $table->addColumn('col_longblob', 'blob');

        $this->schemaManager->dropAndCreateTable($table);

        $platform       = $this->schemaManager->getDatabasePlatform();
        $offlineColumns = $table->getColumns();
        $onlineColumns  = $this->schemaManager->listTableColumns($tableName);

        self::assertSame(
            $platform->getClobTypeDeclarationSQL($offlineColumns['col_tinytext']->toArray()),
            $platform->getClobTypeDeclarationSQL($onlineColumns['col_tinytext']->toArray())
        );
        self::assertSame(
            $platform->getClobTypeDeclarationSQL($offlineColumns['col_text']->toArray()),
            $platform->getClobTypeDeclarationSQL($onlineColumns['col_text']->toArray())
        );
        self::assertSame(
            $platform->getClobTypeDeclarationSQL($offlineColumns['col_mediumtext']->toArray()),
            $platform->getClobTypeDeclarationSQL($onlineColumns['col_mediumtext']->toArray())
        );
        self::assertSame(
            $platform->getClobTypeDeclarationSQL($offlineColumns['col_longtext']->toArray()),
            $platform->getClobTypeDeclarationSQL($onlineColumns['col_longtext']->toArray())
        );

        self::assertSame(
            $platform->getBlobTypeDeclarationSQL($offlineColumns['col_tinyblob']->toArray()),
            $platform->getBlobTypeDeclarationSQL($onlineColumns['col_tinyblob']->toArray())
        );
        self::assertSame(
            $platform->getBlobTypeDeclarationSQL($offlineColumns['col_blob']->toArray()),
            $platform->getBlobTypeDeclarationSQL($onlineColumns['col_blob']->toArray())
        );
        self::assertSame(
            $platform->getBlobTypeDeclarationSQL($offlineColumns['col_mediumblob']->toArray()),
            $platform->getBlobTypeDeclarationSQL($onlineColumns['col_mediumblob']->toArray())
        );
        self::assertSame(
            $platform->getBlobTypeDeclarationSQL($offlineColumns['col_longblob']->toArray()),
            $platform->getBlobTypeDeclarationSQL($onlineColumns['col_longblob']->toArray())
        );
    }

    /**
     * @group DBAL-423
     */
    public function testDiffListGuidTableColumn() : void
    {
        $offlineTable = new Table('list_guid_table_column');
        $offlineTable->addColumn('col_guid', 'guid');

        $this->schemaManager->dropAndCreateTable($offlineTable);

        $onlineTable = $this->schemaManager->listTableDetails('list_guid_table_column');

        $comparator = new Comparator();

        self::assertFalse(
            $comparator->diffTable($offlineTable, $onlineTable),
            'No differences should be detected with the offline vs online schema.'
        );
    }

    /**
     * @group DBAL-1082
     */
    public function testListDecimalTypeColumns() : void
    {
        $tableName = 'test_list_decimal_columns';
        $table     = new Table($tableName);

        $table->addColumn('col', 'decimal');
        $table->addColumn('col_unsigned', 'decimal', ['unsigned' => true]);

        $this->schemaManager->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertArrayHasKey('col', $columns);
        self::assertArrayHasKey('col_unsigned', $columns);
        self::assertFalse($columns['col']->getUnsigned());
        self::assertTrue($columns['col_unsigned']->getUnsigned());
    }

    /**
     * @group DBAL-1082
     */
    public function testListFloatTypeColumns() : void
    {
        $tableName = 'test_list_float_columns';
        $table     = new Table($tableName);

        $table->addColumn('col', 'float');
        $table->addColumn('col_unsigned', 'float', ['unsigned' => true]);

        $this->schemaManager->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertArrayHasKey('col', $columns);
        self::assertArrayHasKey('col_unsigned', $columns);
        self::assertFalse($columns['col']->getUnsigned());
        self::assertTrue($columns['col_unsigned']->getUnsigned());
    }

    public function testJsonColumnType() : void
    {
        $table = new Table('test_mysql_json');
        $table->addColumn('col_json', 'json');
        $this->schemaManager->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('test_mysql_json');

        self::assertSame(Types::JSON, $columns['col_json']->getType()->getName());
    }

    public function testColumnDefaultCurrentTimestamp() : void
    {
        $platform = $this->schemaManager->getDatabasePlatform();

        $table = new Table('test_column_defaults_current_timestamp');

        $currentTimeStampSql = $platform->getCurrentTimestampSQL();

        $table->addColumn('col_datetime', 'datetime', ['notnull' => true, 'default' => $currentTimeStampSql]);
        $table->addColumn('col_datetime_nullable', 'datetime', ['default' => $currentTimeStampSql]);

        $this->schemaManager->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->listTableDetails('test_column_defaults_current_timestamp');
        self::assertSame($currentTimeStampSql, $onlineTable->getColumn('col_datetime')->getDefault());
        self::assertSame($currentTimeStampSql, $onlineTable->getColumn('col_datetime_nullable')->getDefault());

        $comparator = new Comparator();

        $diff = $comparator->diffTable($table, $onlineTable);
        self::assertFalse($diff, 'Tables should be identical with column defaults.');
    }

    public function testColumnDefaultsAreValid() : void
    {
        $table = new Table('test_column_defaults_are_valid');

        $currentTimeStampSql = $this->schemaManager->getDatabasePlatform()->getCurrentTimestampSQL();
        $table->addColumn('col_datetime', 'datetime', ['default' => $currentTimeStampSql]);
        $table->addColumn('col_datetime_null', 'datetime', ['notnull' => false, 'default' => null]);
        $table->addColumn('col_int', 'integer', ['default' => 1]);
        $table->addColumn('col_neg_int', 'integer', ['default' => -1]);
        $table->addColumn('col_string', 'string', ['default' => 'A']);
        $table->addColumn('col_decimal', 'decimal', ['scale' => 3, 'precision' => 6, 'default' => -2.3]);
        $table->addColumn('col_date', 'date', ['default' => '2012-12-12']);

        $this->schemaManager->dropAndCreateTable($table);

        $this->connection->executeUpdate(
            'INSERT INTO test_column_defaults_are_valid () VALUES()'
        );

        $row = $this->connection->fetchAssoc(
            'SELECT *, DATEDIFF(CURRENT_TIMESTAMP(), col_datetime) as diff_seconds FROM test_column_defaults_are_valid'
        );

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
    public function testColumnDefaultValuesCurrentTimeAndDate() : void
    {
        if (! $this->schemaManager->getDatabasePlatform() instanceof MariaDb1027Platform) {
            $this->markTestSkipped('Only relevant for MariaDb102Platform.');
        }

        $platform = $this->schemaManager->getDatabasePlatform();

        $table = new Table('test_column_defaults_current_time_and_date');

        $currentTimestampSql = $platform->getCurrentTimestampSQL();
        $currentTimeSql      = $platform->getCurrentTimeSQL();
        $currentDateSql      = $platform->getCurrentDateSQL();

        $table->addColumn('col_datetime', 'datetime', ['default' => $currentTimestampSql]);
        $table->addColumn('col_date', 'date', ['default' => $currentDateSql]);
        $table->addColumn('col_time', 'time', ['default' => $currentTimeSql]);

        $this->schemaManager->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->listTableDetails('test_column_defaults_current_time_and_date');

        self::assertSame($currentTimestampSql, $onlineTable->getColumn('col_datetime')->getDefault());
        self::assertSame($currentDateSql, $onlineTable->getColumn('col_date')->getDefault());
        self::assertSame($currentTimeSql, $onlineTable->getColumn('col_time')->getDefault());

        $comparator = new Comparator();

        $diff = $comparator->diffTable($table, $onlineTable);
        self::assertFalse($diff, 'Tables should be identical with column defauts time and date.');
    }

    public function testEnsureTableOptionsAreReflectedInMetadata() : void
    {
        $this->connection->query('DROP TABLE IF EXISTS test_table_metadata');

        $sql = <<<'SQL'
CREATE TABLE test_table_metadata(
  col1 INT NOT NULL AUTO_INCREMENT PRIMARY KEY
)
COLLATE utf8_general_ci
ENGINE InnoDB
ROW_FORMAT COMPRESSED
COMMENT 'This is a test'
AUTO_INCREMENT=42
PARTITION BY HASH (col1)
SQL;

        $this->connection->query($sql);
        $onlineTable = $this->schemaManager->listTableDetails('test_table_metadata');

        self::assertEquals('InnoDB', $onlineTable->getOption('engine'));
        self::assertEquals('utf8_general_ci', $onlineTable->getOption('collation'));
        self::assertEquals(42, $onlineTable->getOption('autoincrement'));
        self::assertEquals('This is a test', $onlineTable->getOption('comment'));
        self::assertEquals([
            'row_format' => 'COMPRESSED',
            'partitioned' => true,
        ], $onlineTable->getOption('create_options'));
    }

    public function testEnsureTableWithoutOptionsAreReflectedInMetadata() : void
    {
        $this->connection->query('DROP TABLE IF EXISTS test_table_empty_metadata');

        $this->connection->query('CREATE TABLE test_table_empty_metadata(col1 INT NOT NULL)');
        $onlineTable = $this->schemaManager->listTableDetails('test_table_empty_metadata');

        self::assertNotEmpty($onlineTable->getOption('engine'));
        // collation could be set to default or not set, information_schema indicate a possibly null value
        self::assertFalse($onlineTable->hasOption('autoincrement'));
        self::assertEquals('', $onlineTable->getOption('comment'));
        self::assertEquals([], $onlineTable->getOption('create_options'));
    }

    public function testParseNullCreateOptions() : void
    {
        $table = $this->schemaManager->listTableDetails('sys.processlist');

        self::assertEquals([], $table->getOption('create_options'));
    }
}

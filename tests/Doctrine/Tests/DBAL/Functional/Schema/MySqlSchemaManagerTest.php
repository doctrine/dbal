<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\Types\MySqlPointType;
use function implode;

class MySqlSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ( ! Type::hasType('point')) {
            Type::addType('point', MySqlPointType::class);
        }
    }

    public function testSwitchPrimaryKeyColumns()
    {
        $tableOld = new Table("switch_primary_key_columns");
        $tableOld->addColumn('foo_id', 'integer');
        $tableOld->addColumn('bar_id', 'integer');

        $this->_sm->createTable($tableOld);
        $tableFetched = $this->_sm->listTableDetails("switch_primary_key_columns");
        $tableNew = clone $tableFetched;
        $tableNew->setPrimaryKey(array('bar_id', 'foo_id'));

        $comparator = new Comparator;
        $this->_sm->alterTable($comparator->diffTable($tableFetched, $tableNew));

        $table      = $this->_sm->listTableDetails('switch_primary_key_columns');
        $primaryKey = $table->getPrimaryKeyColumns();

        self::assertCount(2, $primaryKey);
        self::assertContains('bar_id', $primaryKey);
        self::assertContains('foo_id', $primaryKey);
    }

    public function testDiffTableBug()
    {
        $schema = new Schema();
        $table = $schema->createTable('diffbug_routing_translations');
        $table->addColumn('id', 'integer');
        $table->addColumn('route', 'string');
        $table->addColumn('locale', 'string');
        $table->addColumn('attribute', 'string');
        $table->addColumn('localized_value', 'string');
        $table->addColumn('original_value', 'string');
        $table->setPrimaryKey(array('id'));
        $table->addUniqueIndex(array('route', 'locale', 'attribute'));
        $table->addIndex(array('localized_value')); // this is much more selective than the unique index

        $this->_sm->createTable($table);
        $tableFetched = $this->_sm->listTableDetails("diffbug_routing_translations");

        $comparator = new Comparator;
        $diff = $comparator->diffTable($tableFetched, $table);

        self::assertFalse($diff, "no changes expected.");
    }

    public function testFulltextIndex()
    {
        $table = new Table('fulltext_index');
        $table->addColumn('text', 'text');
        $table->addIndex(array('text'), 'f_index');
        $table->addOption('engine', 'MyISAM');

        $index = $table->getIndex('f_index');
        $index->addFlag('fulltext');

        $this->_sm->dropAndCreateTable($table);

        $indexes = $this->_sm->listTableIndexes('fulltext_index');
        self::assertArrayHasKey('f_index', $indexes);
        self::assertTrue($indexes['f_index']->hasFlag('fulltext'));
    }

    public function testSpatialIndex()
    {
        $table = new Table('spatial_index');
        $table->addColumn('point', 'point');
        $table->addIndex(array('point'), 's_index');
        $table->addOption('engine', 'MyISAM');

        $index = $table->getIndex('s_index');
        $index->addFlag('spatial');

        $this->_sm->dropAndCreateTable($table);

        $indexes = $this->_sm->listTableIndexes('spatial_index');
        self::assertArrayHasKey('s_index', $indexes);
        self::assertTrue($indexes['s_index']->hasFlag('spatial'));
    }

    /**
     * @group DBAL-400
     */
    public function testAlterTableAddPrimaryKey()
    {
        $table = new Table('alter_table_add_pk');
        $table->addColumn('id', 'integer');
        $table->addColumn('foo', 'integer');
        $table->addIndex(array('id'), 'idx_id');

        $this->_sm->createTable($table);

        $comparator = new Comparator();
        $diffTable  = clone $table;

        $diffTable->dropIndex('idx_id');
        $diffTable->setPrimaryKey(array('id'));

        $this->_sm->alterTable($comparator->diffTable($table, $diffTable));

        $table = $this->_sm->listTableDetails("alter_table_add_pk");

        self::assertFalse($table->hasIndex('idx_id'));
        self::assertTrue($table->hasPrimaryKey());
    }

    /**
     * @group DBAL-464
     */
    public function testDropPrimaryKeyWithAutoincrementColumn()
    {
        $table = new Table("drop_primary_key");
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('foo', 'integer');
        $table->setPrimaryKey(array('id', 'foo'));

        $this->_sm->dropAndCreateTable($table);

        $diffTable = clone $table;

        $diffTable->dropPrimaryKey();

        $comparator = new Comparator();

        $this->_sm->alterTable($comparator->diffTable($table, $diffTable));

        $table = $this->_sm->listTableDetails("drop_primary_key");

        self::assertFalse($table->hasPrimaryKey());
        self::assertFalse($table->getColumn('id')->getAutoincrement());
    }

    /**
     * @group DBAL-789
     */
    public function testDoesNotPropagateDefaultValuesForUnsupportedColumnTypes()
    {
        if ($this->_sm->getDatabasePlatform() instanceof MariaDb1027Platform) {
            $this->markTestSkipped(
                'MariaDb102Platform supports default values for BLOB and TEXT columns and will propagate values'
            );
        }

        $table = new Table("text_blob_default_value");
        $table->addColumn('def_text', 'text', ['default' => 'def']);
        $table->addColumn('def_text_null', 'text', ['notnull' => false, 'default' => 'def']);
        $table->addColumn('def_blob', 'blob', ['default' => 'def']);
        $table->addColumn('def_blob_null', 'blob', ['notnull' => false, 'default' => 'def']);

        $this->_sm->dropAndCreateTable($table);

        $onlineTable = $this->_sm->listTableDetails("text_blob_default_value");

        self::assertNull($onlineTable->getColumn('def_text')->getDefault());
        self::assertNull($onlineTable->getColumn('def_text_null')->getDefault());
        self::assertFalse($onlineTable->getColumn('def_text_null')->getNotnull());
        self::assertNull($onlineTable->getColumn('def_blob')->getDefault());
        self::assertNull($onlineTable->getColumn('def_blob_null')->getDefault());
        self::assertFalse($onlineTable->getColumn('def_blob_null')->getNotnull());

        $comparator = new Comparator();

        $this->_sm->alterTable($comparator->diffTable($table, $onlineTable));

        $onlineTable = $this->_sm->listTableDetails("text_blob_default_value");

        self::assertNull($onlineTable->getColumn('def_text')->getDefault());
        self::assertNull($onlineTable->getColumn('def_text_null')->getDefault());
        self::assertFalse($onlineTable->getColumn('def_text_null')->getNotnull());
        self::assertNull($onlineTable->getColumn('def_blob')->getDefault());
        self::assertNull($onlineTable->getColumn('def_blob_null')->getDefault());
        self::assertFalse($onlineTable->getColumn('def_blob_null')->getNotnull());
    }

    public function testColumnCollation()
    {
        $table = new Table('test_collation');
        $table->addOption('collate', $collation = 'latin1_swedish_ci');
        $table->addOption('charset', 'latin1');
        $table->addColumn('id', 'integer');
        $table->addColumn('text', 'text');
        $table->addColumn('foo', 'text')->setPlatformOption('collation', 'latin1_swedish_ci');
        $table->addColumn('bar', 'text')->setPlatformOption('collation', 'utf8_general_ci');
        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns('test_collation');

        self::assertArrayNotHasKey('collation', $columns['id']->getPlatformOptions());
        self::assertEquals('latin1_swedish_ci', $columns['text']->getPlatformOption('collation'));
        self::assertEquals('latin1_swedish_ci', $columns['foo']->getPlatformOption('collation'));
        self::assertEquals('utf8_general_ci', $columns['bar']->getPlatformOption('collation'));
    }

    /**
     * @group DBAL-843
     */
    public function testListLobTypeColumns()
    {
        $tableName = 'lob_type_columns';
        $table = new Table($tableName);

        $table->addColumn('col_tinytext', 'text', array('length' => MySqlPlatform::LENGTH_LIMIT_TINYTEXT));
        $table->addColumn('col_text', 'text', array('length' => MySqlPlatform::LENGTH_LIMIT_TEXT));
        $table->addColumn('col_mediumtext', 'text', array('length' => MySqlPlatform::LENGTH_LIMIT_MEDIUMTEXT));
        $table->addColumn('col_longtext', 'text');

        $table->addColumn('col_tinyblob', 'text', array('length' => MySqlPlatform::LENGTH_LIMIT_TINYBLOB));
        $table->addColumn('col_blob', 'blob', array('length' => MySqlPlatform::LENGTH_LIMIT_BLOB));
        $table->addColumn('col_mediumblob', 'blob', array('length' => MySqlPlatform::LENGTH_LIMIT_MEDIUMBLOB));
        $table->addColumn('col_longblob', 'blob');

        $this->_sm->dropAndCreateTable($table);

        $platform = $this->_sm->getDatabasePlatform();
        $offlineColumns = $table->getColumns();
        $onlineColumns = $this->_sm->listTableColumns($tableName);

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
    public function testDiffListGuidTableColumn()
    {
        $offlineTable = new Table('list_guid_table_column');
        $offlineTable->addColumn('col_guid', 'guid');

        $this->_sm->dropAndCreateTable($offlineTable);

        $onlineTable = $this->_sm->listTableDetails('list_guid_table_column');

        $comparator = new Comparator();

        self::assertFalse(
            $comparator->diffTable($offlineTable, $onlineTable),
            "No differences should be detected with the offline vs online schema."
        );
    }

    /**
     * @group DBAL-1082
     */
    public function testListDecimalTypeColumns()
    {
        $tableName = 'test_list_decimal_columns';
        $table = new Table($tableName);

        $table->addColumn('col', 'decimal');
        $table->addColumn('col_unsigned', 'decimal', array('unsigned' => true));

        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns($tableName);

        self::assertArrayHasKey('col', $columns);
        self::assertArrayHasKey('col_unsigned', $columns);
        self::assertFalse($columns['col']->getUnsigned());
        self::assertTrue($columns['col_unsigned']->getUnsigned());
    }

    /**
     * @group DBAL-1082
     */
    public function testListFloatTypeColumns()
    {
        $tableName = 'test_list_float_columns';
        $table = new Table($tableName);

        $table->addColumn('col', 'float');
        $table->addColumn('col_unsigned', 'float', array('unsigned' => true));

        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns($tableName);

        self::assertArrayHasKey('col', $columns);
        self::assertArrayHasKey('col_unsigned', $columns);
        self::assertFalse($columns['col']->getUnsigned());
        self::assertTrue($columns['col_unsigned']->getUnsigned());
    }

    public function testJsonColumnType() : void
    {
        $table = new Table('test_mysql_json');
        $table->addColumn('col_json', 'json');
        $this->_sm->dropAndCreateTable($table);

        $columns = $this->_sm->listTableColumns('test_mysql_json');

        self::assertSame(TYPE::JSON, $columns['col_json']->getType()->getName());
    }

    public function testColumnDefaultCurrentTimestamp() : void
    {
        $platform = $this->_sm->getDatabasePlatform();

        $table = new Table("test_column_defaults_current_timestamp");

        $currentTimeStampSql = $platform->getCurrentTimestampSQL();

        $table->addColumn('col_datetime', 'datetime', ['notnull' => true, 'default' => $currentTimeStampSql]);
        $table->addColumn('col_datetime_nullable', 'datetime', ['default' => $currentTimeStampSql]);

        $this->_sm->dropAndCreateTable($table);

        $onlineTable = $this->_sm->listTableDetails("test_column_defaults_current_timestamp");
        self::assertSame($currentTimeStampSql, $onlineTable->getColumn('col_datetime')->getDefault());
        self::assertSame($currentTimeStampSql, $onlineTable->getColumn('col_datetime_nullable')->getDefault());

        $comparator = new Comparator();

        $diff = $comparator->diffTable($table, $onlineTable);
        self::assertFalse($diff, "Tables should be identical with column defaults.");
    }

    public function testColumnDefaultsAreValid()
    {
        $table = new Table("test_column_defaults_are_valid");

        $currentTimeStampSql = $this->_sm->getDatabasePlatform()->getCurrentTimestampSQL();
        $table->addColumn('col_datetime', 'datetime', ['default' => $currentTimeStampSql]);
        $table->addColumn('col_datetime_null', 'datetime', ['notnull' => false, 'default' => null]);
        $table->addColumn('col_int', 'integer', ['default' => 1]);
        $table->addColumn('col_neg_int', 'integer', ['default' => -1]);
        $table->addColumn('col_string', 'string', ['default' => 'A']);
        $table->addColumn('col_decimal', 'decimal', ['scale' => 3, 'precision' => 6, 'default' => -2.3]);
        $table->addColumn('col_date', 'date', ['default' => '2012-12-12']);

        $this->_sm->dropAndCreateTable($table);

        $this->_conn->executeUpdate(
            "INSERT INTO test_column_defaults_are_valid () VALUES()"
        );

        $row = $this->_conn->fetchAssoc(
            'SELECT *, DATEDIFF(CURRENT_TIMESTAMP(), col_datetime) as diff_seconds FROM test_column_defaults_are_valid'
        );

        self::assertInstanceOf(\DateTime::class, \DateTime::createFromFormat('Y-m-d H:i:s', $row['col_datetime']));
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
        if ( ! $this->_sm->getDatabasePlatform() instanceof MariaDb1027Platform) {
            $this->markTestSkipped('Only relevant for MariaDb102Platform.');
        }

        $platform = $this->_sm->getDatabasePlatform();

        $table = new Table("test_column_defaults_current_time_and_date");

        $currentTimestampSql = $platform->getCurrentTimestampSQL();
        $currentTimeSql      = $platform->getCurrentTimeSQL();
        $currentDateSql      = $platform->getCurrentDateSQL();

        $table->addColumn('col_datetime', 'datetime', ['default' => $currentTimestampSql]);
        $table->addColumn('col_date', 'date', ['default' => $currentDateSql]);
        $table->addColumn('col_time', 'time', ['default' => $currentTimeSql]);

        $this->_sm->dropAndCreateTable($table);

        $onlineTable = $this->_sm->listTableDetails("test_column_defaults_current_time_and_date");

        self::assertSame($currentTimestampSql, $onlineTable->getColumn('col_datetime')->getDefault());
        self::assertSame($currentDateSql, $onlineTable->getColumn('col_date')->getDefault());
        self::assertSame($currentTimeSql, $onlineTable->getColumn('col_time')->getDefault());

        $comparator = new Comparator();

        $diff = $comparator->diffTable($table, $onlineTable);
        self::assertFalse($diff, "Tables should be identical with column defauts time and date.");
    }

    /**
     * Ensure default values (un-)escaping is properly done by mysql platforms.
     * The test is voluntarily relying on schema introspection due to current
     * doctrine limitations. Once #2850 is landed, this test can be removed.
     * @see https://dev.mysql.com/doc/refman/5.7/en/string-literals.html
     */
    public function testEnsureDefaultsAreUnescapedFromSchemaIntrospection() : void
    {
        $platform = $this->_sm->getDatabasePlatform();
        $this->_conn->query('DROP TABLE IF EXISTS test_column_defaults_with_create');

        $escapeSequences = [
            "\\0",          // An ASCII NUL (X'00') character
            "\\'", "''",    // Single quote
            '\\"', '""',    // Double quote
            '\\b',          // A backspace character
            '\\n',          // A new-line character
            '\\r',          // A carriage return character
            '\\t',          // A tab character
            '\\Z',          // ASCII 26 (Control+Z)
            '\\\\',         // A backslash (\) character
            '\\%',          // A percent (%) character
            '\\_',          // An underscore (_) character
        ];

        $default = implode('+', $escapeSequences);

        $sql = "CREATE TABLE test_column_defaults_with_create(
                    col1 VARCHAR(255) NULL DEFAULT {$platform->quoteStringLiteral($default)} 
                )";
        $this->_conn->query($sql);
        $onlineTable = $this->_sm->listTableDetails("test_column_defaults_with_create");
        self::assertSame($default, $onlineTable->getColumn('col1')->getDefault());
    }
}

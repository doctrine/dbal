<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

class MySqlSchemaManagerTest extends SchemaManagerFunctionalTestCase
{

    protected function setUp()
    {
        parent::setUp();

        if (!Type::hasType('point')) {
            Type::addType('point', 'Doctrine\Tests\Types\MySqlPointType');
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
        $table->addColumn('id', 'integer', array('primary' => true, 'autoincrement' => true));
        $table->addColumn('foo', 'integer', array('primary' => true));
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
            $this->markTestSkipped('MariaDb1027Platform supports default values for BLOB and TEXT columns and will propagate values');
        }

        $table = new Table("text_blob_default_value");
        $table->addColumn('def_text', 'text', array('default' => 'def'));
        $table->addColumn('def_text_null', 'text', array('notnull' => false, 'default' => 'def'));
        $table->addColumn('def_blob', 'blob', array('default' => 'def'));
        $table->addColumn('def_blob_null', 'blob', array('notnull' => false, 'default' => 'def'));

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

    /**
     * Since MariaDB 10.2.1, Blob and text columns can have a default value
     *
     * @link https://mariadb.com/kb/en/library/blob-and-text-data-types
     */
    public function testDefaultValueSupportForBlobAndText()
    {
        if (!$this->_sm->getDatabasePlatform() instanceof MariaDb1027Platform) {
            $this->markTestSkipped('Only MariaDb1027Platform supports default values for BLOB and TEXT columns');
        }

        $table = new Table("text_blob_default_value");
        $table->addColumn('def_text', 'text', array('default' => 'def'));
        $table->addColumn('def_text_null', 'text', array('notnull' => false, 'default' => 'def'));
        $table->addColumn('def_blob', 'blob', array('default' => 'def'));
        $table->addColumn('def_blob_null', 'blob', array('notnull' => false, 'default' => 'def'));

        $this->_sm->dropAndCreateTable($table);

        $onlineTable = $this->_sm->listTableDetails("text_blob_default_value");

        self::assertSame('def', $onlineTable->getColumn('def_text')->getDefault());
        self::assertSame('def', $onlineTable->getColumn('def_text_null')->getDefault());
        self::assertSame('def', $onlineTable->getColumn('def_blob')->getDefault());
        self::assertSame('def', $onlineTable->getColumn('def_blob_null')->getDefault());

        $comparator = new Comparator();

        self::assertFalse($comparator->diffTable($table, $onlineTable));
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


    /**
     * As of MariaDB 10.2.7, nullable default values literals are always single quoted in
     * information_schema. Non-nullable defaults behaviour is not affected.
     * This test ensure accidental removal of double single encoded defaults for MariaDB >= 10.2.7.
     *
     * @link https://mariadb.com/kb/en/library/information-schema-columns-table/
     * @link https://dev.mysql.com/doc/refman/5.5/en/string-literals.html
     */
    public function testColumnDefaultValuesDoubleQuoted(): void
    {

        $table = new Table("test_column_default_values_double_quoted");
        $table->addColumn('string_nullable_quoted', 'string', ['notnull' => false, 'default' => 'NULL']);
        $table->addColumn('string_nullable_double_quoted', 'string', ['notnull' => false, 'default' => "'NULL'"]);

        $table->addColumn('string_notnull_quoted', 'string', ['notnull' => true, 'default' => 'NULL']);
        //$table->addColumn('string_notnull_double_quoted', 'string', ['notnull' => true, 'default' => "\\'NULL\\'"]);

        $this->_sm->dropAndCreateTable($table);

        $onlineTable = $this->_sm->listTableDetails("test_column_default_values_double_quoted");
        self::assertSame('NULL', $onlineTable->getColumn('string_nullable_quoted')->getDefault());

        self::assertSame("'NULL'", $onlineTable->getColumn('string_nullable_double_quoted')->getDefault());
        self::assertSame("NULL", $onlineTable->getColumn('string_notnull_quoted')->getDefault());
        //self::assertSame("'NULL'", $onlineTable->getColumn('string_notnull_double_quoted')->getDefault());

        $comparator = new Comparator();

        $diff = $comparator->diffTable($table, $onlineTable);
        $this->assertFalse($diff, "Tables should be identical with double quoted literals.");

    }


    public function testColumnDefaultCurrentTimestamp(): void
    {
        $platform = $this->_sm->getDatabasePlatform();

        $table = new Table("test_column_defaults_current_timestamp");

        $currentTimeStampSql = $platform->getCurrentTimestampSQL();

        $table->addColumn('col_datetime', 'datetime', ['notnull' => true, 'default' => $currentTimeStampSql]);
        $table->addColumn('col_datetime_nullable', 'datetime', ['notnull' => false, 'default' => $currentTimeStampSql]);

        $this->_sm->dropAndCreateTable($table);

        $onlineTable = $this->_sm->listTableDetails("test_column_defaults_current_timestamp");
        self::assertSame($currentTimeStampSql, $onlineTable->getColumn('col_datetime')->getDefault());
        self::assertSame($currentTimeStampSql, $onlineTable->getColumn('col_datetime_nullable')->getDefault());

        $comparator = new Comparator();

        $diff = $comparator->diffTable($table, $onlineTable);
        $this->assertFalse($diff, "Tables should be identical with column defaults.");
    }

    /**
     * Test default CURRENT_TIME and CURRENT_DATE as default values
     *
     * Note: MySQL (as of 5.7.19) does not support default value
     * for DATE and TIME fields while MariaDB 10.2+ does
     */
    public function testColumnDefaultCurrentTimeAndDate()
    {
        $platform = $this->_sm->getDatabasePlatform();

        if (!$platform instanceof MariaDb1027Platform) {
            $this->markTestSkipped('Currently only MariaDb1027Platform supports setting CURRENT_TIME and CURRENT_DATE default values.');
        }

        $table = new Table("test_column_defaults_current_time_and_date");

        $currentTimeSql = $platform->getCurrentTimeSQL();
        $currentDateSql = $platform->getCurrentDateSQL();

        $table->addColumn('col_date', 'date', ['notnull' => true, 'default' => $currentDateSql]);
        $table->addColumn('col_time', 'time', ['notnull' => true, 'default' => $currentTimeSql]);

        $this->_sm->dropAndCreateTable($table);

        $onlineTable = $this->_sm->listTableDetails("test_column_defaults_current_time_and_date");

        self::assertSame($currentDateSql, $onlineTable->getColumn('col_date')->getDefault());
        self::assertSame($currentTimeSql, $onlineTable->getColumn('col_time')->getDefault());

        $comparator = new Comparator();

        $diff = $comparator->diffTable($table, $onlineTable);
        $this->assertFalse($diff, "Tables should be identical with column defaults.");
    }


    /**
     *
     * @link https://mariadb.com/kb/en/library/string-literals
     */
    public function testColumnDefaultValuesEscaping(): void
    {
        $table = new Table("test_column_default_values_escaping");
        $table->addColumn('no_quotes', 'string', ['notnull' => false, 'default' => 'az']);

        $table->addColumn('backslash', 'string', ['notnull' => false, 'default' => 'a\\\z']);
        $table->addColumn('repeated_single_quotes', 'string', ['notnull' => false, 'default' => "a''z"]);

        $this->_sm->dropAndCreateTable($table);

        $onlineTable = $this->_sm->listTableDetails("test_column_default_values_escaping");
        self::assertSame("az", $onlineTable->getColumn('no_quotes')->getDefault());
        self::assertSame('a\\\z', $onlineTable->getColumn('backslash')->getDefault());
        self::assertSame("a''z", $onlineTable->getColumn('repeated_single_quotes')->getDefault());

        $comparator = new Comparator();

        $diff = $comparator->diffTable($table, $onlineTable);
        $this->assertFalse($diff, "Tables should be identical with values escape sequences.");
    }

    public function testColumnDefaultsUsingDoctrineTable(): void
    {

        $table = new Table("test_column_defaults_with_table");
        $table->addColumn('col0', 'integer', ['notnull' => false]);
        $table->addColumn('col1', 'integer', ['notnull' => false, 'default' => null]);
        $table->addColumn('col2', 'string', ['notnull' => false, 'default' => null]);
        $table->addColumn('col3', 'string', ['notnull' => false, 'default' => 'NULL']);
        $table->addColumn('col4', 'string', ['notnull' => false, 'default' => 'Hello world']);
        $table->addColumn('col5', 'datetime', ['notnull' => false, 'default' => null]);
        $table->addColumn('col6', 'decimal', ['scale' => 3, 'precision' => 6, 'notnull' => false, 'default' => -2.3]);
        $table->addColumn('col7', 'date', ['notnull' => false, 'default' => '2012-12-12']);
        $table->addColumn('col8', 'string', ['notnull' => true, 'default' => '']);
        $table->addColumn('col9', 'integer', ['notnull' => false, 'default' => 0]);
        $table->addColumn('col10', 'string', ['notnull' => false, 'default' => 'He"ll"o world']);
        $table->addColumn('col11', 'string', ['notnull' => false, 'default' => '2012-12-12 23:59:59']);
        //$table->addColumn('col12', 'string', ['notnull' => false, 'default' => 'He\\\'llo \\\world']);

        $table->addColumn('col20', 'string', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP()']);
        $table->addColumn('col21', 'string', ['notnull' => false, 'default' => 'CURRENT_TIMESTAMP']);

        $this->_sm->dropAndCreateTable($table);

        $onlineTable = $this->_sm->listTableDetails("test_column_defaults_with_table");
        self::assertNull($onlineTable->getColumn('col0')->getDefault());
        self::assertNull($onlineTable->getColumn('col1')->getDefault());
        self::assertNull($onlineTable->getColumn('col2')->getDefault());
        self::assertEquals('NULL', $onlineTable->getColumn('col3')->getDefault());
        self::assertEquals('Hello world', $onlineTable->getColumn('col4')->getDefault());
        self::assertNull($onlineTable->getColumn('col5')->getDefault());
        self::assertEquals(-2.3, $onlineTable->getColumn('col6')->getDefault());
        self::assertEquals('2012-12-12', $onlineTable->getColumn('col7')->getDefault());
        self::assertTrue($onlineTable->getColumn('col8')->getNotnull());
        self::assertEquals('', $onlineTable->getColumn('col8')->getDefault());
        self::assertSame('0', $onlineTable->getColumn('col9')->getDefault());
        self::assertEquals('He"ll"o world', $onlineTable->getColumn('col10')->getDefault());
        self::assertEquals('2012-12-12 23:59:59', $onlineTable->getColumn('col11')->getDefault());
        //self::assertEquals('He\\\'llo \\world', $onlineTable->getColumn('col12')->getDefault());

        // MariaDB 10.2 and MySQL 5.7 differences while storing default now() in information schema.
        // MariaDB will always store "current_timestamp()", mysql "CURRENT_TIMESTAMP"
        self::assertStringStartsWith('current_timestamp', strtolower($onlineTable->getColumn('col20')->getDefault()));
        self::assertStringStartsWith('current_timestamp', strtolower($onlineTable->getColumn('col21')->getDefault()));

        $comparator = new Comparator();

        $diff = $comparator->diffTable($table, $onlineTable);
        self::assertFalse($diff, "Tables should be identical with column defaults.");
    }

    /**
     * Ensure that an existing table with quoted (literals) default values
     * does not trigger a table change.
     */
    public function testColumnDefaultsDoesNotTriggerADiff(): void
    {
        $this->_conn->query('DROP TABLE IF EXISTS test_column_defaults_no_diff');
        $sql = "
            CREATE TABLE test_column_defaults_with_create (
                col1 VARCHAR(255) NULL DEFAULT 'O''Connor\'\"',
                col2 VARCHAR(255) NULL DEFAULT '''A'''
                );
        ";

        $this->_conn->query($sql);
        $onlineTable = $this->_sm->listTableDetails("test_column_defaults_with_create");
        self::assertSame("O'Connor'\"", $onlineTable->getColumn('col1')->getDefault());
        self::assertSame("'A'", $onlineTable->getColumn('col2')->getDefault());

        $table = new Table("test_column_defaults_no_diff");
        $table->addColumn('col1', 'string', ['notnull' => false, 'default' => "O'Connor'\""]);
        $table->addColumn('col2', 'string', ['notnull' => false, 'default' => "'A'"]);

        $comparator = new Comparator();
        $diff = $comparator->diffTable($table, $onlineTable);
        self::assertFalse($diff);
    }

    /**
     * MariaDB supports expressions as default values
     *
     * @link https://mariadb.com/kb/en/library/information-schema-columns-table/
     */
    public function testColumnDefaultExpressions(): void
    {
        $this->markTestSkipped('Setting an expression as a default value is not yet supported (WIP)');

        if ($this->_sm->getDatabasePlatform() instanceof MariaDb1027Platform) {

            $table = new Table("test_column_default_expressions");

            $table->addColumn('expression', 'string', ['notnull' => false, 'default' => "concat('A','B')"]);

            $this->_sm->dropAndCreateTable($table);

            $onlineTable = $this->_sm->listTableDetails("test_column_default_expressions");
            self::assertSame("concat('A','B')", $onlineTable->getColumn('expression')->getDefault());

            $comparator = new Comparator();

            $diff = $comparator->diffTable($table, $onlineTable);
            $this->assertFalse($diff, "Tables should be identical with expression column defaults.");
        }
    }

}

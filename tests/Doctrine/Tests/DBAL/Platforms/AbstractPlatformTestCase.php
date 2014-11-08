<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;

abstract class AbstractPlatformTestCase extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    protected $_platform;

    abstract public function createPlatform();

    public function setUp()
    {
        $this->_platform = $this->createPlatform();
    }

    /**
     * @group DDC-1360
     */
    public function testQuoteIdentifier()
    {
        if ($this->_platform->getName() == "mssql") {
            $this->markTestSkipped('Not working this way on mssql.');
        }

        $c = $this->_platform->getIdentifierQuoteCharacter();
        $this->assertEquals($c."test".$c, $this->_platform->quoteIdentifier("test"));
        $this->assertEquals($c."test".$c.".".$c."test".$c, $this->_platform->quoteIdentifier("test.test"));
        $this->assertEquals(str_repeat($c, 4), $this->_platform->quoteIdentifier($c));
    }

    /**
     * @group DDC-1360
     */
    public function testQuoteSingleIdentifier()
    {
        if ($this->_platform->getName() == "mssql") {
            $this->markTestSkipped('Not working this way on mssql.');
        }

        $c = $this->_platform->getIdentifierQuoteCharacter();
        $this->assertEquals($c."test".$c, $this->_platform->quoteSingleIdentifier("test"));
        $this->assertEquals($c."test.test".$c, $this->_platform->quoteSingleIdentifier("test.test"));
        $this->assertEquals(str_repeat($c, 4), $this->_platform->quoteSingleIdentifier($c));
    }

    /**
     * @group DBAL-1029
     *
     * @dataProvider getReturnsForeignKeyReferentialActionSQL
     */
    public function testReturnsForeignKeyReferentialActionSQL($action, $expectedSQL)
    {
        $this->assertSame($expectedSQL, $this->_platform->getForeignKeyReferentialActionSQL($action));
    }

    /**
     * @return array
     */
    public function getReturnsForeignKeyReferentialActionSQL()
    {
        return array(
            array('CASCADE', 'CASCADE'),
            array('SET NULL', 'SET NULL'),
            array('NO ACTION', 'NO ACTION'),
            array('RESTRICT', 'RESTRICT'),
            array('SET DEFAULT', 'SET DEFAULT'),
            array('CaScAdE', 'CASCADE'),
        );
    }

    public function testGetInvalidtForeignKeyReferentialActionSQL()
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->_platform->getForeignKeyReferentialActionSQL('unknown');
    }

    public function testGetUnknownDoctrineMappingType()
    {
        $this->setExpectedException('Doctrine\DBAL\DBALException');
        $this->_platform->getDoctrineTypeMapping('foobar');
    }

    public function testRegisterDoctrineMappingType()
    {
        $this->_platform->registerDoctrineTypeMapping('foo', 'integer');
        $this->assertEquals('integer', $this->_platform->getDoctrineTypeMapping('foo'));
    }

    public function testRegisterUnknownDoctrineMappingType()
    {
        $this->setExpectedException('Doctrine\DBAL\DBALException');
        $this->_platform->registerDoctrineTypeMapping('foo', 'bar');
    }

    public function testCreateWithNoColumns()
    {
        $table = new Table('test');

        $this->setExpectedException('Doctrine\DBAL\DBALException');
        $sql = $this->_platform->getCreateTableSQL($table);
    }

    public function testGeneratesTableCreationSql()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer', array('notnull' => true, 'autoincrement' => true));
        $table->addColumn('test', 'string', array('notnull' => false, 'length' => 255));
        $table->setPrimaryKey(array('id'));

        $sql = $this->_platform->getCreateTableSQL($table);
        $this->assertEquals($this->getGenerateTableSql(), $sql[0]);
    }

    abstract public function getGenerateTableSql();

    public function testGenerateTableWithMultiColumnUniqueIndex()
    {
        $table = new Table('test');
        $table->addColumn('foo', 'string', array('notnull' => false, 'length' => 255));
        $table->addColumn('bar', 'string', array('notnull' => false, 'length' => 255));
        $table->addUniqueIndex(array("foo", "bar"));

        $sql = $this->_platform->getCreateTableSQL($table);
        $this->assertEquals($this->getGenerateTableWithMultiColumnUniqueIndexSql(), $sql);
    }

    abstract public function getGenerateTableWithMultiColumnUniqueIndexSql();

    public function testGeneratesIndexCreationSql()
    {
        $indexDef = new \Doctrine\DBAL\Schema\Index('my_idx', array('user_name', 'last_login'));

        $this->assertEquals(
            $this->getGenerateIndexSql(),
            $this->_platform->getCreateIndexSQL($indexDef, 'mytable')
        );
    }

    abstract public function getGenerateIndexSql();

    public function testGeneratesUniqueIndexCreationSql()
    {
        $indexDef = new \Doctrine\DBAL\Schema\Index('index_name', array('test', 'test2'), true);

        $sql = $this->_platform->getCreateIndexSQL($indexDef, 'test');
        $this->assertEquals($this->getGenerateUniqueIndexSql(), $sql);
    }

    abstract public function getGenerateUniqueIndexSql();

    public function testGeneratesPartialIndexesSqlOnlyWhenSupportingPartialIndexes()
    {
        $where = 'test IS NULL AND test2 IS NOT NULL';
        $indexDef = new \Doctrine\DBAL\Schema\Index('name', array('test', 'test2'), false, false, array(), array('where' => $where));
        $uniqueIndex = new \Doctrine\DBAL\Schema\Index('name', array('test', 'test2'), true, false, array(), array('where' => $where));

        $expected = ' WHERE ' . $where;

        $actuals = array();

        if ($this->supportsInlineIndexDeclaration()) {
            $actuals []= $this->_platform->getIndexDeclarationSQL('name', $indexDef);
        }

        $actuals []= $this->_platform->getUniqueConstraintDeclarationSQL('name', $uniqueIndex);
        $actuals []= $this->_platform->getCreateIndexSQL($indexDef, 'table');

        foreach ($actuals as $actual) {
            if ($this->_platform->supportsPartialIndexes()) {
                $this->assertStringEndsWith($expected, $actual, 'WHERE clause should be present');
            } else {
                $this->assertStringEndsNotWith($expected, $actual, 'WHERE clause should NOT be present');
            }
        }
    }

    public function testGeneratesForeignKeyCreationSql()
    {
        $fk = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(array('fk_name_id'), 'other_table', array('id'), '');

        $sql = $this->_platform->getCreateForeignKeySQL($fk, 'test');
        $this->assertEquals($sql, $this->getGenerateForeignKeySql());
    }

    abstract public function getGenerateForeignKeySql();

    public function testGeneratesConstraintCreationSql()
    {
        $idx = new \Doctrine\DBAL\Schema\Index('constraint_name', array('test'), true, false);
        $sql = $this->_platform->getCreateConstraintSQL($idx, 'test');
        $this->assertEquals($this->getGenerateConstraintUniqueIndexSql(), $sql);

        $pk = new \Doctrine\DBAL\Schema\Index('constraint_name', array('test'), true, true);
        $sql = $this->_platform->getCreateConstraintSQL($pk, 'test');
        $this->assertEquals($this->getGenerateConstraintPrimaryIndexSql(), $sql);

        $fk = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(array('fk_name'), 'foreign', array('id'), 'constraint_fk');
        $sql = $this->_platform->getCreateConstraintSQL($fk, 'test');
        $this->assertEquals($this->getGenerateConstraintForeignKeySql($fk), $sql);
    }

    public function testGeneratesForeignKeySqlOnlyWhenSupportingForeignKeys()
    {
        $fk = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(array('fk_name'), 'foreign', array('id'), 'constraint_fk');

        if ($this->_platform->supportsForeignKeyConstraints()) {
            $this->assertInternalType(
                'string',
                $this->_platform->getCreateForeignKeySQL($fk, 'test')
            );
        } else {
            $this->setExpectedException('Doctrine\DBAL\DBALException');
            $this->_platform->getCreateForeignKeySQL($fk, 'test');
        }
    }

    protected function getBitAndComparisonExpressionSql($value1, $value2)
    {
        return '(' . $value1 . ' & ' . $value2 . ')';
    }

    /**
     * @group DDC-1213
     */
    public function testGeneratesBitAndComparisonExpressionSql()
    {
        $sql = $this->_platform->getBitAndComparisonExpression(2, 4);
        $this->assertEquals($this->getBitAndComparisonExpressionSql(2, 4), $sql);
    }

    protected  function getBitOrComparisonExpressionSql($value1, $value2)
    {
        return '(' . $value1 . ' | ' . $value2 . ')';
    }

    /**
     * @group DDC-1213
     */
    public function testGeneratesBitOrComparisonExpressionSql()
    {
        $sql = $this->_platform->getBitOrComparisonExpression(2, 4);
        $this->assertEquals($this->getBitOrComparisonExpressionSql(2, 4), $sql);
    }

    public function getGenerateConstraintUniqueIndexSql()
    {
        return 'ALTER TABLE test ADD CONSTRAINT constraint_name UNIQUE (test)';
    }

    public function getGenerateConstraintPrimaryIndexSql()
    {
        return 'ALTER TABLE test ADD CONSTRAINT constraint_name PRIMARY KEY (test)';
    }

    public function getGenerateConstraintForeignKeySql(ForeignKeyConstraint $fk)
    {
        $quotedForeignTable = $fk->getQuotedForeignTableName($this->_platform);

        return "ALTER TABLE test ADD CONSTRAINT constraint_fk FOREIGN KEY (fk_name) REFERENCES $quotedForeignTable (id)";
    }

    abstract public function getGenerateAlterTableSql();

    public function testGeneratesTableAlterationSql()
    {
        $expectedSql = $this->getGenerateAlterTableSql();

        $table = new Table('mytable');
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'string');
        $table->addColumn('bloo', 'boolean');
        $table->setPrimaryKey(array('id'));

        $tableDiff = new TableDiff('mytable');
        $tableDiff->fromTable = $table;
        $tableDiff->newName = 'userlist';
        $tableDiff->addedColumns['quota'] = new \Doctrine\DBAL\Schema\Column('quota', \Doctrine\DBAL\Types\Type::getType('integer'), array('notnull' => false));
        $tableDiff->removedColumns['foo'] = new \Doctrine\DBAL\Schema\Column('foo', \Doctrine\DBAL\Types\Type::getType('integer'));
        $tableDiff->changedColumns['bar'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'bar', new \Doctrine\DBAL\Schema\Column(
                'baz', \Doctrine\DBAL\Types\Type::getType('string'), array('default' => 'def')
            ),
            array('type', 'notnull', 'default')
        );
        $tableDiff->changedColumns['bloo'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'bloo', new \Doctrine\DBAL\Schema\Column(
                'bloo', \Doctrine\DBAL\Types\Type::getType('boolean'), array('default' => false)
            ),
            array('type', 'notnull', 'default')
        );

        $sql = $this->_platform->getAlterTableSQL($tableDiff);

        $this->assertEquals($expectedSql, $sql);
    }

    public function testGetCustomColumnDeclarationSql()
    {
        $field = array('columnDefinition' => 'MEDIUMINT(6) UNSIGNED');
        $this->assertEquals('foo MEDIUMINT(6) UNSIGNED', $this->_platform->getColumnDeclarationSQL('foo', $field));
    }

    public function testGetCreateTableSqlDispatchEvent()
    {
        $listenerMock = $this->getMock('GetCreateTableSqlDispatchEvenListener', array('onSchemaCreateTable', 'onSchemaCreateTableColumn'));
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaCreateTable');
        $listenerMock
            ->expects($this->exactly(2))
            ->method('onSchemaCreateTableColumn');

        $eventManager = new EventManager();
        $eventManager->addEventListener(array(Events::onSchemaCreateTable, Events::onSchemaCreateTableColumn), $listenerMock);

        $this->_platform->setEventManager($eventManager);

        $table = new Table('test');
        $table->addColumn('foo', 'string', array('notnull' => false, 'length' => 255));
        $table->addColumn('bar', 'string', array('notnull' => false, 'length' => 255));

        $this->_platform->getCreateTableSQL($table);
    }

    public function testGetDropTableSqlDispatchEvent()
    {
        $listenerMock = $this->getMock('GetDropTableSqlDispatchEventListener', array('onSchemaDropTable'));
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaDropTable');

        $eventManager = new EventManager();
        $eventManager->addEventListener(array(Events::onSchemaDropTable), $listenerMock);

        $this->_platform->setEventManager($eventManager);

        $this->_platform->getDropTableSQL('TABLE');
    }

    public function testGetAlterTableSqlDispatchEvent()
    {
        $events = array(
            'onSchemaAlterTable',
            'onSchemaAlterTableAddColumn',
            'onSchemaAlterTableRemoveColumn',
            'onSchemaAlterTableChangeColumn',
            'onSchemaAlterTableRenameColumn'
        );

        $listenerMock = $this->getMock('GetAlterTableSqlDispatchEvenListener', $events);
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaAlterTable');
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaAlterTableAddColumn');
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaAlterTableRemoveColumn');
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaAlterTableChangeColumn');
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaAlterTableRenameColumn');

        $eventManager = new EventManager();
        $events = array(
            Events::onSchemaAlterTable,
            Events::onSchemaAlterTableAddColumn,
            Events::onSchemaAlterTableRemoveColumn,
            Events::onSchemaAlterTableChangeColumn,
            Events::onSchemaAlterTableRenameColumn
        );
        $eventManager->addEventListener($events, $listenerMock);

        $this->_platform->setEventManager($eventManager);

        $table = new Table('mytable');
        $table->addColumn('removed', 'integer');
        $table->addColumn('changed', 'integer');
        $table->addColumn('renamed', 'integer');

        $tableDiff = new TableDiff('mytable');
        $tableDiff->fromTable = $table;
        $tableDiff->addedColumns['added'] = new \Doctrine\DBAL\Schema\Column('added', \Doctrine\DBAL\Types\Type::getType('integer'), array());
        $tableDiff->removedColumns['removed'] = new \Doctrine\DBAL\Schema\Column('removed', \Doctrine\DBAL\Types\Type::getType('integer'), array());
        $tableDiff->changedColumns['changed'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'changed', new \Doctrine\DBAL\Schema\Column(
                'changed2', \Doctrine\DBAL\Types\Type::getType('string'), array()
            ),
            array()
        );
        $tableDiff->renamedColumns['renamed'] = new \Doctrine\DBAL\Schema\Column('renamed2', \Doctrine\DBAL\Types\Type::getType('integer'), array());

        $this->_platform->getAlterTableSQL($tableDiff);
    }

    /**
     * @group DBAL-42
     */
    public function testCreateTableColumnComments()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer', array('comment' => 'This is a comment'));
        $table->setPrimaryKey(array('id'));

        $this->assertEquals($this->getCreateTableColumnCommentsSQL(), $this->_platform->getCreateTableSQL($table));
    }

    /**
     * @group DBAL-42
     */
    public function testAlterTableColumnComments()
    {
        $tableDiff = new TableDiff('mytable');
        $tableDiff->addedColumns['quota'] = new \Doctrine\DBAL\Schema\Column('quota', \Doctrine\DBAL\Types\Type::getType('integer'), array('comment' => 'A comment'));
        $tableDiff->changedColumns['foo'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'foo', new \Doctrine\DBAL\Schema\Column(
                'foo', \Doctrine\DBAL\Types\Type::getType('string')
            ),
            array('comment')
        );
        $tableDiff->changedColumns['bar'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'bar', new \Doctrine\DBAL\Schema\Column(
                'baz', \Doctrine\DBAL\Types\Type::getType('string'), array('comment' => 'B comment')
            ),
            array('comment')
        );

        $this->assertEquals($this->getAlterTableColumnCommentsSQL(), $this->_platform->getAlterTableSQL($tableDiff));
    }

    public function testCreateTableColumnTypeComments()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('data', 'array');
        $table->setPrimaryKey(array('id'));

        $this->assertEquals($this->getCreateTableColumnTypeCommentsSQL(), $this->_platform->getCreateTableSQL($table));
    }

    public function getCreateTableColumnCommentsSQL()
    {
        $this->markTestSkipped('Platform does not support Column comments.');
    }

    public function getAlterTableColumnCommentsSQL()
    {
        $this->markTestSkipped('Platform does not support Column comments.');
    }

    public function getCreateTableColumnTypeCommentsSQL()
    {
        $this->markTestSkipped('Platform does not support Column comments.');
    }

    public function testGetDefaultValueDeclarationSQL()
    {
        // non-timestamp value will get single quotes
        $field = array(
            'type' => 'string',
            'default' => 'non_timestamp'
        );

        $this->assertEquals(" DEFAULT 'non_timestamp'", $this->_platform->getDefaultValueDeclarationSQL($field));
    }

    public function testGetDefaultValueDeclarationSQLDateTime()
    {
        // timestamps on datetime types should not be quoted
        foreach (array('datetime', 'datetimetz') as $type) {

            $field = array(
                'type' => Type::getType($type),
                'default' => $this->_platform->getCurrentTimestampSQL()
            );

            $this->assertEquals(' DEFAULT ' . $this->_platform->getCurrentTimestampSQL(), $this->_platform->getDefaultValueDeclarationSQL($field));

        }
    }

    public function testGetDefaultValueDeclarationSQLForIntegerTypes()
    {
        foreach(array('bigint', 'integer', 'smallint') as $type) {
            $field = array(
                'type'    => Type::getType($type),
                'default' => 1
            );

            $this->assertEquals(
                ' DEFAULT 1',
                $this->_platform->getDefaultValueDeclarationSQL($field)
            );
        }
    }

    /**
     * @group DBAL-45
     */
    public function testKeywordList()
    {
        $keywordList = $this->_platform->getReservedKeywordsList();
        $this->assertInstanceOf('Doctrine\DBAL\Platforms\Keywords\KeywordList', $keywordList);

        $this->assertTrue($keywordList->isKeyword('table'));
    }

    /**
     * @group DBAL-374
     */
    public function testQuotedColumnInPrimaryKeyPropagation()
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', 'string');
        $table->setPrimaryKey(array('create'));

        $sql = $this->_platform->getCreateTableSQL($table);
        $this->assertEquals($this->getQuotedColumnInPrimaryKeySQL(), $sql);
    }

    abstract protected function getQuotedColumnInPrimaryKeySQL();
    abstract protected function getQuotedColumnInIndexSQL();
    abstract protected function getQuotedNameInIndexSQL();
    abstract protected function getQuotedColumnInForeignKeySQL();

    /**
     * @group DBAL-374
     */
    public function testQuotedColumnInIndexPropagation()
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', 'string');
        $table->addIndex(array('create'));

        $sql = $this->_platform->getCreateTableSQL($table);
        $this->assertEquals($this->getQuotedColumnInIndexSQL(), $sql);
    }

    public function testQuotedNameInIndexSQL()
    {
        $table = new Table('test');
        $table->addColumn('column1', 'string');
        $table->addIndex(array('column1'), '`key`');

        $sql = $this->_platform->getCreateTableSQL($table);
        $this->assertEquals($this->getQuotedNameInIndexSQL(), $sql);
    }

    /**
     * @group DBAL-374
     */
    public function testQuotedColumnInForeignKeyPropagation()
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', 'string');
        $table->addColumn('foo', 'string');
        $table->addColumn('`bar`', 'string');

        // Foreign table with reserved keyword as name (needs quotation).
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('create', 'string');    // Foreign column with reserved keyword as name (needs quotation).
        $foreignTable->addColumn('bar', 'string');       // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('`foo-bar`', 'string'); // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).

        $table->addForeignKeyConstraint($foreignTable, array('create', 'foo', '`bar`'), array('create', 'bar', '`foo-bar`'), array(), 'FK_WITH_RESERVED_KEYWORD');

        // Foreign table with non-reserved keyword as name (does not need quotation).
        $foreignTable = new Table('foo');
        $foreignTable->addColumn('create', 'string');    // Foreign column with reserved keyword as name (needs quotation).
        $foreignTable->addColumn('bar', 'string');       // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('`foo-bar`', 'string'); // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).

        $table->addForeignKeyConstraint($foreignTable, array('create', 'foo', '`bar`'), array('create', 'bar', '`foo-bar`'), array(), 'FK_WITH_NON_RESERVED_KEYWORD');

        // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $foreignTable = new Table('`foo-bar`');
        $foreignTable->addColumn('create', 'string');    // Foreign column with reserved keyword as name (needs quotation).
        $foreignTable->addColumn('bar', 'string');       // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('`foo-bar`', 'string'); // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).

        $table->addForeignKeyConstraint($foreignTable, array('create', 'foo', '`bar`'), array('create', 'bar', '`foo-bar`'), array(), 'FK_WITH_INTENDED_QUOTATION');

        $sql = $this->_platform->getCreateTableSQL($table, AbstractPlatform::CREATE_FOREIGNKEYS);
        $this->assertEquals($this->getQuotedColumnInForeignKeySQL(), $sql);
    }

    /**
     * @group DBAL-1051
     */
    public function testQuotesReservedKeywordInUniqueConstraintDeclarationSQL()
    {
        $index = new Index('select', array('foo'), true);

        $this->assertSame(
            $this->getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(),
            $this->_platform->getUniqueConstraintDeclarationSQL('select', $index)
        );
    }

    /**
     * @return string
     */
    abstract protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL();

    /**
     * @group DBAL-1051
     */
    public function testQuotesReservedKeywordInIndexDeclarationSQL()
    {
        $index = new Index('select', array('foo'));

        if (! $this->supportsInlineIndexDeclaration()) {
            $this->setExpectedException('Doctrine\DBAL\DBALException');
        }

        $this->assertSame(
            $this->getQuotesReservedKeywordInIndexDeclarationSQL(),
            $this->_platform->getIndexDeclarationSQL('select', $index)
        );
    }

    /**
     * @return string
     */
    abstract protected function getQuotesReservedKeywordInIndexDeclarationSQL();

    /**
     * @return boolean
     */
    protected function supportsInlineIndexDeclaration()
    {
        return true;
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testGetCreateSchemaSQL()
    {
        $this->_platform->getCreateSchemaSQL('schema');
    }

    /**
     * @group DBAL-585
     */
    public function testAlterTableChangeQuotedColumn()
    {
        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff('mytable');
        $tableDiff->fromTable = new \Doctrine\DBAL\Schema\Table('mytable');
        $tableDiff->changedColumns['foo'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'select', new \Doctrine\DBAL\Schema\Column(
                'select', \Doctrine\DBAL\Types\Type::getType('string')
            ),
            array('type')
        );

        $this->assertContains(
            $this->_platform->quoteIdentifier('select'),
            implode(';', $this->_platform->getAlterTableSQL($tableDiff))
        );
    }

    /**
     * @group DBAL-563
     */
    public function testUsesSequenceEmulatedIdentityColumns()
    {
        $this->assertFalse($this->_platform->usesSequenceEmulatedIdentityColumns());
    }

    /**
     * @group DBAL-563
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testReturnsIdentitySequenceName()
    {
        $this->_platform->getIdentitySequenceName('mytable', 'mycolumn');
    }

    public function testReturnsBinaryDefaultLength()
    {
        $this->assertSame($this->getBinaryDefaultLength(), $this->_platform->getBinaryDefaultLength());
    }

    protected function getBinaryDefaultLength()
    {
        return 255;
    }

    public function testReturnsBinaryMaxLength()
    {
        $this->assertSame($this->getBinaryMaxLength(), $this->_platform->getBinaryMaxLength());
    }

    protected function getBinaryMaxLength()
    {
        return 4000;
    }

    /**
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testReturnsBinaryTypeDeclarationSQL()
    {
        $this->_platform->getBinaryTypeDeclarationSQL(array());
    }

    /**
     * @group DBAL-553
     */
    public function hasNativeJsonType()
    {
        $this->assertFalse($this->_platform->hasNativeJsonType());
    }

    /**
     * @group DBAL-553
     */
    public function testReturnsJsonTypeDeclarationSQL()
    {
        $column = array(
            'length'  => 666,
            'notnull' => true,
            'type'    => Type::getType('json_array'),
        );

        $this->assertSame(
            $this->_platform->getClobTypeDeclarationSQL($column),
            $this->_platform->getJsonTypeDeclarationSQL($column)
        );
    }

    /**
     * @group DBAL-234
     */
    public function testAlterTableRenameIndex()
    {
        $tableDiff = new TableDiff('mytable');
        $tableDiff->fromTable = new Table('mytable');
        $tableDiff->fromTable->addColumn('id', 'integer');
        $tableDiff->fromTable->setPrimaryKey(array('id'));
        $tableDiff->renamedIndexes = array(
            'idx_foo' => new Index('idx_bar', array('id'))
        );

        $this->assertSame(
            $this->getAlterTableRenameIndexSQL(),
            $this->_platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL()
    {
        return array(
            'DROP INDEX idx_foo',
            'CREATE INDEX idx_bar ON mytable (id)',
        );
    }

    /**
     * @group DBAL-234
     */
    public function testQuotesAlterTableRenameIndex()
    {
        $tableDiff = new TableDiff('table');
        $tableDiff->fromTable = new Table('table');
        $tableDiff->fromTable->addColumn('id', 'integer');
        $tableDiff->fromTable->setPrimaryKey(array('id'));
        $tableDiff->renamedIndexes = array(
            'create' => new Index('select', array('id')),
            '`foo`'  => new Index('`bar`', array('id')),
        );

        $this->assertSame(
            $this->getQuotedAlterTableRenameIndexSQL(),
            $this->_platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL()
    {
        return array(
            'DROP INDEX "create"',
            'CREATE INDEX "select" ON "table" (id)',
            'DROP INDEX "foo"',
            'CREATE INDEX "bar" ON "table" (id)',
        );
    }

    /**
     * @group DBAL-835
     */
    public function testQuotesAlterTableRenameColumn()
    {
        $fromTable = new Table('mytable');

        $fromTable->addColumn('unquoted1', 'integer', array('comment' => 'Unquoted 1'));
        $fromTable->addColumn('unquoted2', 'integer', array('comment' => 'Unquoted 2'));
        $fromTable->addColumn('unquoted3', 'integer', array('comment' => 'Unquoted 3'));

        $fromTable->addColumn('create', 'integer', array('comment' => 'Reserved keyword 1'));
        $fromTable->addColumn('table', 'integer', array('comment' => 'Reserved keyword 2'));
        $fromTable->addColumn('select', 'integer', array('comment' => 'Reserved keyword 3'));

        $fromTable->addColumn('`quoted1`', 'integer', array('comment' => 'Quoted 1'));
        $fromTable->addColumn('`quoted2`', 'integer', array('comment' => 'Quoted 2'));
        $fromTable->addColumn('`quoted3`', 'integer', array('comment' => 'Quoted 3'));

        $toTable = new Table('mytable');

        $toTable->addColumn('unquoted', 'integer', array('comment' => 'Unquoted 1')); // unquoted -> unquoted
        $toTable->addColumn('where', 'integer', array('comment' => 'Unquoted 2')); // unquoted -> reserved keyword
        $toTable->addColumn('`foo`', 'integer', array('comment' => 'Unquoted 3')); // unquoted -> quoted

        $toTable->addColumn('reserved_keyword', 'integer', array('comment' => 'Reserved keyword 1')); // reserved keyword -> unquoted
        $toTable->addColumn('from', 'integer', array('comment' => 'Reserved keyword 2')); // reserved keyword -> reserved keyword
        $toTable->addColumn('`bar`', 'integer', array('comment' => 'Reserved keyword 3')); // reserved keyword -> quoted

        $toTable->addColumn('quoted', 'integer', array('comment' => 'Quoted 1')); // quoted -> unquoted
        $toTable->addColumn('and', 'integer', array('comment' => 'Quoted 2')); // quoted -> reserved keyword
        $toTable->addColumn('`baz`', 'integer', array('comment' => 'Quoted 3')); // quoted -> quoted

        $comparator = new Comparator();

        $this->assertEquals(
            $this->getQuotedAlterTableRenameColumnSQL(),
            $this->_platform->getAlterTableSQL($comparator->diffTable($fromTable, $toTable))
        );
    }

    /**
     * Returns SQL statements for {@link testQuotesAlterTableRenameColumn}.
     *
     * @return array
     *
     * @group DBAL-835
     */
    abstract protected function getQuotedAlterTableRenameColumnSQL();

    /**
     * @group DBAL-835
     */
    public function testQuotesAlterTableChangeColumnLength()
    {
        $fromTable = new Table('mytable');

        $fromTable->addColumn('unquoted1', 'string', array('comment' => 'Unquoted 1', 'length' => 10));
        $fromTable->addColumn('unquoted2', 'string', array('comment' => 'Unquoted 2', 'length' => 10));
        $fromTable->addColumn('unquoted3', 'string', array('comment' => 'Unquoted 3', 'length' => 10));

        $fromTable->addColumn('create', 'string', array('comment' => 'Reserved keyword 1', 'length' => 10));
        $fromTable->addColumn('table', 'string', array('comment' => 'Reserved keyword 2', 'length' => 10));
        $fromTable->addColumn('select', 'string', array('comment' => 'Reserved keyword 3', 'length' => 10));

        $toTable = new Table('mytable');

        $toTable->addColumn('unquoted1', 'string', array('comment' => 'Unquoted 1', 'length' => 255));
        $toTable->addColumn('unquoted2', 'string', array('comment' => 'Unquoted 2', 'length' => 255));
        $toTable->addColumn('unquoted3', 'string', array('comment' => 'Unquoted 3', 'length' => 255));

        $toTable->addColumn('create', 'string', array('comment' => 'Reserved keyword 1', 'length' => 255));
        $toTable->addColumn('table', 'string', array('comment' => 'Reserved keyword 2', 'length' => 255));
        $toTable->addColumn('select', 'string', array('comment' => 'Reserved keyword 3', 'length' => 255));

        $comparator = new Comparator();

        $this->assertEquals(
            $this->getQuotedAlterTableChangeColumnLengthSQL(),
            $this->_platform->getAlterTableSQL($comparator->diffTable($fromTable, $toTable))
        );
    }

    /**
     * Returns SQL statements for {@link testQuotesAlterTableChangeColumnLength}.
     *
     * @return array
     *
     * @group DBAL-835
     */
    abstract protected function getQuotedAlterTableChangeColumnLengthSQL();

    /**
     * @group DBAL-807
     */
    public function testAlterTableRenameIndexInSchema()
    {
        $tableDiff = new TableDiff('myschema.mytable');
        $tableDiff->fromTable = new Table('myschema.mytable');
        $tableDiff->fromTable->addColumn('id', 'integer');
        $tableDiff->fromTable->setPrimaryKey(array('id'));
        $tableDiff->renamedIndexes = array(
            'idx_foo' => new Index('idx_bar', array('id'))
        );

        $this->assertSame(
            $this->getAlterTableRenameIndexInSchemaSQL(),
            $this->_platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @group DBAL-807
     */
    protected function getAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            'DROP INDEX idx_foo',
            'CREATE INDEX idx_bar ON myschema.mytable (id)',
        );
    }

    /**
     * @group DBAL-807
     */
    public function testQuotesAlterTableRenameIndexInSchema()
    {
        $tableDiff = new TableDiff('`schema`.table');
        $tableDiff->fromTable = new Table('`schema`.table');
        $tableDiff->fromTable->addColumn('id', 'integer');
        $tableDiff->fromTable->setPrimaryKey(array('id'));
        $tableDiff->renamedIndexes = array(
            'create' => new Index('select', array('id')),
            '`foo`'  => new Index('`bar`', array('id')),
        );

        $this->assertSame(
            $this->getQuotedAlterTableRenameIndexInSchemaSQL(),
            $this->_platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            'DROP INDEX "schema"."create"',
            'CREATE INDEX "select" ON "schema"."table" (id)',
            'DROP INDEX "schema"."foo"',
            'CREATE INDEX "bar" ON "schema"."table" (id)',
        );
    }

    protected function getStringLiteralQuoteCharacter()
    {
        return "'";
    }

    public function testGetStringLiteralQuoteCharacter()
    {
        $this->assertSame($this->getStringLiteralQuoteCharacter(), $this->_platform->getStringLiteralQuoteCharacter());
    }

    protected function getQuotedCommentOnColumnSQLWithoutQuoteCharacter()
    {
        return "COMMENT ON COLUMN mytable.id IS 'This is a comment'";
    }

    public function testGetCommentOnColumnSQLWithoutQuoteCharacter()
    {
        $this->assertEquals(
            $this->getQuotedCommentOnColumnSQLWithoutQuoteCharacter(),
            $this->_platform->getCommentOnColumnSQL('mytable', 'id', 'This is a comment')
        );
    }

    protected function getQuotedCommentOnColumnSQLWithQuoteCharacter()
    {
        return "COMMENT ON COLUMN mytable.id IS 'It''s a quote !'";
    }

    public function testGetCommentOnColumnSQLWithQuoteCharacter()
    {
        $c = $this->getStringLiteralQuoteCharacter();

        $this->assertEquals(
            $this->getQuotedCommentOnColumnSQLWithQuoteCharacter(),
            $this->_platform->getCommentOnColumnSQL('mytable', 'id', "It" . $c . "s a quote !")
        );
    }

    /**
     * @return array
     *
     * @see testGetCommentOnColumnSQL
     */
    abstract protected function getCommentOnColumnSQL();

    /**
     * @group DBAL-1004
     */
    public function testGetCommentOnColumnSQL()
    {
        $this->assertSame(
            $this->getCommentOnColumnSQL(),
            array(
                $this->_platform->getCommentOnColumnSQL('foo', 'bar', 'comment'), // regular identifiers
                $this->_platform->getCommentOnColumnSQL('`Foo`', '`BAR`', 'comment'), // explicitly quoted identifiers
                $this->_platform->getCommentOnColumnSQL('select', 'from', 'comment'), // reserved keyword identifiers
            )
        );
    }

    protected function getQuotedStringLiteralWithoutQuoteCharacter()
    {
        return "'No quote'";
    }

    protected function getQuotedStringLiteralWithQuoteCharacter()
    {
        return "'It''s a quote'";
    }

    protected function getQuotedStringLiteralQuoteCharacter()
    {
        return "''''";
    }

    public function testQuoteStringLiteral()
    {
        $c = $this->getStringLiteralQuoteCharacter();

        $this->assertEquals(
            $this->getQuotedStringLiteralWithoutQuoteCharacter(),
            $this->_platform->quoteStringLiteral('No quote')
        );
        $this->assertEquals(
            $this->getQuotedStringLiteralWithQuoteCharacter(),
            $this->_platform->quoteStringLiteral('It' . $c . 's a quote')
        );
        $this->assertEquals(
            $this->getQuotedStringLiteralQuoteCharacter(),
            $this->_platform->quoteStringLiteral($c)
        );
    }

    /**
     * @group DBAL-423
     *
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testReturnsGuidTypeDeclarationSQL()
    {
        $this->_platform->getGuidTypeDeclarationSQL(array());
    }

    /**
     * @group DBAL-1010
     */
    public function testGeneratesAlterTableRenameColumnSQL()
    {
        $table = new Table('foo');
        $table->addColumn(
            'bar',
            'integer',
            array('notnull' => true, 'default' => 666, 'comment' => 'rename test')
        );

        $tableDiff = new TableDiff('foo');
        $tableDiff->fromTable = $table;
        $tableDiff->renamedColumns['bar'] = new Column(
            'baz',
            Type::getType('integer'),
            array('notnull' => true, 'default' => 666, 'comment' => 'rename test')
        );

        $this->assertSame($this->getAlterTableRenameColumnSQL(), $this->_platform->getAlterTableSQL($tableDiff));
    }

    /**
     * @return array
     */
    abstract public function getAlterTableRenameColumnSQL();

    /**
     * @group DBAL-1016
     */
    public function testQuotesTableIdentifiersInAlterTableSQL()
    {
        $table = new Table('"foo"');
        $table->addColumn('id', 'integer');
        $table->addColumn('fk', 'integer');
        $table->addColumn('fk2', 'integer');
        $table->addColumn('fk3', 'integer');
        $table->addColumn('bar', 'integer');
        $table->addColumn('baz', 'integer');
        $table->addForeignKeyConstraint('fk_table', array('fk'), array('id'), array(), 'fk1');
        $table->addForeignKeyConstraint('fk_table', array('fk2'), array('id'), array(), 'fk2');

        $tableDiff = new TableDiff('"foo"');
        $tableDiff->fromTable = $table;
        $tableDiff->newName = 'table';
        $tableDiff->addedColumns['bloo'] = new Column('bloo', Type::getType('integer'));
        $tableDiff->changedColumns['bar'] = new ColumnDiff(
            'bar',
            new Column('bar', Type::getType('integer'), array('notnull' => false)),
            array('notnull'),
            $table->getColumn('bar')
        );
        $tableDiff->renamedColumns['id'] = new Column('war', Type::getType('integer'));
        $tableDiff->removedColumns['baz'] = new Column('baz', Type::getType('integer'));
        $tableDiff->addedForeignKeys[] = new ForeignKeyConstraint(array('fk3'), 'fk_table', array('id'), 'fk_add');
        $tableDiff->changedForeignKeys[] = new ForeignKeyConstraint(array('fk2'), 'fk_table2', array('id'), 'fk2');
        $tableDiff->removedForeignKeys[] = new ForeignKeyConstraint(array('fk'), 'fk_table', array('id'), 'fk1');

        $this->assertSame(
            $this->getQuotesTableIdentifiersInAlterTableSQL(),
            $this->_platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @return array
     */
    abstract protected function getQuotesTableIdentifiersInAlterTableSQL();

    /**
     * @group DBAL-1090
     */
    public function testAlterStringToFixedString()
    {

        $table = new Table('mytable');
        $table->addColumn('name', 'string', array('length' => 2));

        $tableDiff = new TableDiff('mytable');
        $tableDiff->fromTable = $table;

        $tableDiff->changedColumns['name'] = new \Doctrine\DBAL\Schema\ColumnDiff(
            'name', new \Doctrine\DBAL\Schema\Column(
                'name', \Doctrine\DBAL\Types\Type::getType('string'), array('fixed' => true, 'length' => 2)
            ),
            array('fixed')
        );

        $sql = $this->_platform->getAlterTableSQL($tableDiff);

        $expectedSql = $this->getAlterStringToFixedStringSQL();

        $this->assertEquals($expectedSql, $sql);
    }

    /**
     * @return array
     */
    abstract protected function getAlterStringToFixedStringSQL();

    /**
     * @group DBAL-1062
     */
    public function testGeneratesAlterTableRenameIndexUsedByForeignKeySQL()
    {
        $foreignTable = new Table('foreign_table');
        $foreignTable->addColumn('id', 'integer');
        $foreignTable->setPrimaryKey(array('id'));

        $primaryTable = new Table('mytable');
        $primaryTable->addColumn('foo', 'integer');
        $primaryTable->addColumn('bar', 'integer');
        $primaryTable->addColumn('baz', 'integer');
        $primaryTable->addIndex(array('foo'), 'idx_foo');
        $primaryTable->addIndex(array('bar'), 'idx_bar');
        $primaryTable->addForeignKeyConstraint($foreignTable, array('foo'), array('id'), array(), 'fk_foo');
        $primaryTable->addForeignKeyConstraint($foreignTable, array('bar'), array('id'), array(), 'fk_bar');

        $tableDiff = new TableDiff('mytable');
        $tableDiff->fromTable = $primaryTable;
        $tableDiff->renamedIndexes['idx_foo'] = new Index('idx_foo_renamed', array('foo'));

        $this->assertSame(
            $this->getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(),
            $this->_platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @return array
     */
    abstract protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL();
}

<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

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
        $table->addIndex(array('column1'), 'create');

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
}

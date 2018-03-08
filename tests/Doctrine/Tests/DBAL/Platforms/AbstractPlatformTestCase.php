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
use Doctrine\Tests\Types\CommentedType;

abstract class AbstractPlatformTestCase extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    protected $_platform;

    abstract public function createPlatform();

    protected function setUp()
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
        self::assertEquals($c."test".$c, $this->_platform->quoteIdentifier("test"));
        self::assertEquals($c."test".$c.".".$c."test".$c, $this->_platform->quoteIdentifier("test.test"));
        self::assertEquals(str_repeat($c, 4), $this->_platform->quoteIdentifier($c));
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
        self::assertEquals($c."test".$c, $this->_platform->quoteSingleIdentifier("test"));
        self::assertEquals($c."test.test".$c, $this->_platform->quoteSingleIdentifier("test.test"));
        self::assertEquals(str_repeat($c, 4), $this->_platform->quoteSingleIdentifier($c));
    }

    /**
     * @group DBAL-1029
     *
     * @dataProvider getReturnsForeignKeyReferentialActionSQL
     */
    public function testReturnsForeignKeyReferentialActionSQL($action, $expectedSQL)
    {
        self::assertSame($expectedSQL, $this->_platform->getForeignKeyReferentialActionSQL($action));
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

    public function testGetInvalidForeignKeyReferentialActionSQL()
    {
        $this->expectException('InvalidArgumentException');
        $this->_platform->getForeignKeyReferentialActionSQL('unknown');
    }

    public function testGetUnknownDoctrineMappingType()
    {
        $this->expectException('Doctrine\DBAL\DBALException');
        $this->_platform->getDoctrineTypeMapping('foobar');
    }

    public function testRegisterDoctrineMappingType()
    {
        $this->_platform->registerDoctrineTypeMapping('foo', 'integer');
        self::assertEquals('integer', $this->_platform->getDoctrineTypeMapping('foo'));
    }

    public function testRegisterUnknownDoctrineMappingType()
    {
        $this->expectException('Doctrine\DBAL\DBALException');
        $this->_platform->registerDoctrineTypeMapping('foo', 'bar');
    }

    /**
     * @group DBAL-2594
     */
    public function testRegistersCommentedDoctrineMappingTypeImplicitly()
    {
        if (!Type::hasType('my_commented')) {
            Type::addType('my_commented', CommentedType::class);
        }

        $type = Type::getType('my_commented');
        $this->_platform->registerDoctrineTypeMapping('foo', 'my_commented');

        self::assertTrue($this->_platform->isCommentedDoctrineType($type));
    }

    /**
     * @group DBAL-939
     *
     * @dataProvider getIsCommentedDoctrineType
     */
    public function testIsCommentedDoctrineType(Type $type, $commented)
    {
        self::assertSame($commented, $this->_platform->isCommentedDoctrineType($type));
    }

    public function getIsCommentedDoctrineType()
    {
        $this->setUp();

        $data = array();

        foreach (Type::getTypesMap() as $typeName => $className) {
            $type = Type::getType($typeName);

            $data[$typeName] = array(
                $type,
                $type->requiresSQLCommentHint($this->_platform),
            );
        }

        return $data;
    }

    public function testCreateWithNoColumns()
    {
        $table = new Table('test');

        $this->expectException('Doctrine\DBAL\DBALException');
        $sql = $this->_platform->getCreateTableSQL($table);
    }

    public function testGeneratesTableCreationSql()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer', array('notnull' => true, 'autoincrement' => true));
        $table->addColumn('test', 'string', array('notnull' => false, 'length' => 255));
        $table->setPrimaryKey(array('id'));

        $sql = $this->_platform->getCreateTableSQL($table);
        self::assertEquals($this->getGenerateTableSql(), $sql[0]);
    }

    abstract public function getGenerateTableSql();

    public function testGenerateTableWithMultiColumnUniqueIndex()
    {
        $table = new Table('test');
        $table->addColumn('foo', 'string', array('notnull' => false, 'length' => 255));
        $table->addColumn('bar', 'string', array('notnull' => false, 'length' => 255));
        $table->addUniqueIndex(array("foo", "bar"));

        $sql = $this->_platform->getCreateTableSQL($table);
        self::assertEquals($this->getGenerateTableWithMultiColumnUniqueIndexSql(), $sql);
    }

    abstract public function getGenerateTableWithMultiColumnUniqueIndexSql();

    public function testGeneratesIndexCreationSql()
    {
        $indexDef = new \Doctrine\DBAL\Schema\Index('my_idx', array('user_name', 'last_login'));

        self::assertEquals(
            $this->getGenerateIndexSql(),
            $this->_platform->getCreateIndexSQL($indexDef, 'mytable')
        );
    }

    abstract public function getGenerateIndexSql();

    public function testGeneratesUniqueIndexCreationSql()
    {
        $indexDef = new \Doctrine\DBAL\Schema\Index('index_name', array('test', 'test2'), true);

        $sql = $this->_platform->getCreateIndexSQL($indexDef, 'test');
        self::assertEquals($this->getGenerateUniqueIndexSql(), $sql);
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
                self::assertStringEndsWith($expected, $actual, 'WHERE clause should be present');
            } else {
                self::assertStringEndsNotWith($expected, $actual, 'WHERE clause should NOT be present');
            }
        }
    }

    public function testGeneratesForeignKeyCreationSql()
    {
        $fk = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(array('fk_name_id'), 'other_table', array('id'), '');

        $sql = $this->_platform->getCreateForeignKeySQL($fk, 'test');
        self::assertEquals($sql, $this->getGenerateForeignKeySql());
    }

    abstract public function getGenerateForeignKeySql();

    public function testGeneratesConstraintCreationSql()
    {
        $idx = new \Doctrine\DBAL\Schema\Index('constraint_name', array('test'), true, false);
        $sql = $this->_platform->getCreateConstraintSQL($idx, 'test');
        self::assertEquals($this->getGenerateConstraintUniqueIndexSql(), $sql);

        $pk = new \Doctrine\DBAL\Schema\Index('constraint_name', array('test'), true, true);
        $sql = $this->_platform->getCreateConstraintSQL($pk, 'test');
        self::assertEquals($this->getGenerateConstraintPrimaryIndexSql(), $sql);

        $fk = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(array('fk_name'), 'foreign', array('id'), 'constraint_fk');
        $sql = $this->_platform->getCreateConstraintSQL($fk, 'test');
        self::assertEquals($this->getGenerateConstraintForeignKeySql($fk), $sql);
    }

    public function testGeneratesForeignKeySqlOnlyWhenSupportingForeignKeys()
    {
        $fk = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(array('fk_name'), 'foreign', array('id'), 'constraint_fk');

        if ($this->_platform->supportsForeignKeyConstraints()) {
            self::assertInternalType(
                'string',
                $this->_platform->getCreateForeignKeySQL($fk, 'test')
            );
        } else {
            $this->expectException('Doctrine\DBAL\DBALException');
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
        self::assertEquals($this->getBitAndComparisonExpressionSql(2, 4), $sql);
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
        self::assertEquals($this->getBitOrComparisonExpressionSql(2, 4), $sql);
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

        self::assertEquals($expectedSql, $sql);
    }

    public function testGetCustomColumnDeclarationSql()
    {
        $field = array('columnDefinition' => 'MEDIUMINT(6) UNSIGNED');
        self::assertEquals('foo MEDIUMINT(6) UNSIGNED', $this->_platform->getColumnDeclarationSQL('foo', $field));
    }

    public function testGetCreateTableSqlDispatchEvent()
    {
        $listenerMock = $this->getMockBuilder('GetCreateTableSqlDispatchEvenListener')
            ->setMethods(array('onSchemaCreateTable', 'onSchemaCreateTableColumn'))
            ->getMock();
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
        $listenerMock = $this->getMockBuilder('GetDropTableSqlDispatchEventListener')
            ->setMethods(array('onSchemaDropTable'))
            ->getMock();
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

        $listenerMock = $this->getMockBuilder('GetAlterTableSqlDispatchEvenListener')
            ->setMethods($events)
            ->getMock();
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

        self::assertEquals($this->getCreateTableColumnCommentsSQL(), $this->_platform->getCreateTableSQL($table));
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

        self::assertEquals($this->getAlterTableColumnCommentsSQL(), $this->_platform->getAlterTableSQL($tableDiff));
    }

    public function testCreateTableColumnTypeComments()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('data', 'array');
        $table->setPrimaryKey(array('id'));

        self::assertEquals($this->getCreateTableColumnTypeCommentsSQL(), $this->_platform->getCreateTableSQL($table));
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
            'type' => Type::getType('string'),
            'default' => 'non_timestamp'
        );

        self::assertEquals(" DEFAULT 'non_timestamp'", $this->_platform->getDefaultValueDeclarationSQL($field));
    }

    /**
     * @group 2859
     */
    public function testGetDefaultValueDeclarationSQLDateTime() : void
    {
        // timestamps on datetime types should not be quoted
        foreach (['datetime', 'datetimetz', 'datetime_immutable', 'datetimetz_immutable'] as $type) {
            $field = [
                'type'    => Type::getType($type),
                'default' => $this->_platform->getCurrentTimestampSQL(),
            ];

            self::assertSame(
                ' DEFAULT ' . $this->_platform->getCurrentTimestampSQL(),
                $this->_platform->getDefaultValueDeclarationSQL($field)
            );
        }
    }

    public function testGetDefaultValueDeclarationSQLForIntegerTypes()
    {
        foreach(array('bigint', 'integer', 'smallint') as $type) {
            $field = array(
                'type'    => Type::getType($type),
                'default' => 1
            );

            self::assertEquals(
                ' DEFAULT 1',
                $this->_platform->getDefaultValueDeclarationSQL($field)
            );
        }
    }

    /**
     * @group 2859
     */
    public function testGetDefaultValueDeclarationSQLForDateType() : void
    {
        $currentDateSql = $this->_platform->getCurrentDateSQL();
        foreach (['date', 'date_immutable'] as $type) {
            $field = [
                'type'    => Type::getType($type),
                'default' => $currentDateSql,
            ];

            self::assertSame(
                ' DEFAULT ' . $currentDateSql,
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
        self::assertInstanceOf('Doctrine\DBAL\Platforms\Keywords\KeywordList', $keywordList);

        self::assertTrue($keywordList->isKeyword('table'));
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
        self::assertEquals($this->getQuotedColumnInPrimaryKeySQL(), $sql);
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
        self::assertEquals($this->getQuotedColumnInIndexSQL(), $sql);
    }

    public function testQuotedNameInIndexSQL()
    {
        $table = new Table('test');
        $table->addColumn('column1', 'string');
        $table->addIndex(array('column1'), '`key`');

        $sql = $this->_platform->getCreateTableSQL($table);
        self::assertEquals($this->getQuotedNameInIndexSQL(), $sql);
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
        self::assertEquals($this->getQuotedColumnInForeignKeySQL(), $sql);
    }

    /**
     * @group DBAL-1051
     */
    public function testQuotesReservedKeywordInUniqueConstraintDeclarationSQL()
    {
        $index = new Index('select', array('foo'), true);

        self::assertSame(
            $this->getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(),
            $this->_platform->getUniqueConstraintDeclarationSQL('select', $index)
        );
    }

    /**
     * @return string
     */
    abstract protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL();

    /**
     * @group DBAL-2270
     */
    public function testQuotesReservedKeywordInTruncateTableSQL()
    {
        self::assertSame(
            $this->getQuotesReservedKeywordInTruncateTableSQL(),
            $this->_platform->getTruncateTableSQL('select')
        );
    }

    /**
     * @return string
     */
    abstract protected function getQuotesReservedKeywordInTruncateTableSQL();

    /**
     * @group DBAL-1051
     */
    public function testQuotesReservedKeywordInIndexDeclarationSQL()
    {
        $index = new Index('select', array('foo'));

        if (! $this->supportsInlineIndexDeclaration()) {
            $this->expectException('Doctrine\DBAL\DBALException');
        }

        self::assertSame(
            $this->getQuotesReservedKeywordInIndexDeclarationSQL(),
            $this->_platform->getIndexDeclarationSQL('select', $index)
        );
    }

    /**
     * @return string
     */
    abstract protected function getQuotesReservedKeywordInIndexDeclarationSQL();

    /**
     * @return bool
     */
    protected function supportsInlineIndexDeclaration()
    {
        return true;
    }

    public function testSupportsCommentOnStatement()
    {
        self::assertSame($this->supportsCommentOnStatement(), $this->_platform->supportsCommentOnStatement());
    }

    /**
     * @return bool
     */
    protected function supportsCommentOnStatement()
    {
        return false;
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

        self::assertContains(
            $this->_platform->quoteIdentifier('select'),
            implode(';', $this->_platform->getAlterTableSQL($tableDiff))
        );
    }

    /**
     * @group DBAL-563
     */
    public function testUsesSequenceEmulatedIdentityColumns()
    {
        self::assertFalse($this->_platform->usesSequenceEmulatedIdentityColumns());
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
        self::assertSame($this->getBinaryDefaultLength(), $this->_platform->getBinaryDefaultLength());
    }

    protected function getBinaryDefaultLength()
    {
        return 255;
    }

    public function testReturnsBinaryMaxLength()
    {
        self::assertSame($this->getBinaryMaxLength(), $this->_platform->getBinaryMaxLength());
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
        self::assertFalse($this->_platform->hasNativeJsonType());
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

        self::assertSame(
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

        self::assertSame(
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

        self::assertSame(
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

        self::assertEquals(
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

        self::assertEquals(
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

        self::assertSame(
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

        self::assertSame(
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

    /**
     * @group DBAL-1237
     */
    public function testQuotesDropForeignKeySQL()
    {
        if (! $this->_platform->supportsForeignKeyConstraints()) {
            $this->markTestSkipped(
                sprintf('%s does not support foreign key constraints.', get_class($this->_platform))
            );
        }

        $tableName = 'table';
        $table = new Table($tableName);
        $foreignKeyName = 'select';
        $foreignKey = new ForeignKeyConstraint(array(), 'foo', array(), 'select');
        $expectedSql = $this->getQuotesDropForeignKeySQL();

        self::assertSame($expectedSql, $this->_platform->getDropForeignKeySQL($foreignKeyName, $tableName));
        self::assertSame($expectedSql, $this->_platform->getDropForeignKeySQL($foreignKey, $table));
    }

    protected function getQuotesDropForeignKeySQL()
    {
        return 'ALTER TABLE "table" DROP FOREIGN KEY "select"';
    }

    /**
     * @group DBAL-1237
     */
    public function testQuotesDropConstraintSQL()
    {
        $tableName = 'table';
        $table = new Table($tableName);
        $constraintName = 'select';
        $constraint = new ForeignKeyConstraint(array(), 'foo', array(), 'select');
        $expectedSql = $this->getQuotesDropConstraintSQL();

        self::assertSame($expectedSql, $this->_platform->getDropConstraintSQL($constraintName, $tableName));
        self::assertSame($expectedSql, $this->_platform->getDropConstraintSQL($constraint, $table));
    }

    protected function getQuotesDropConstraintSQL()
    {
        return 'ALTER TABLE "table" DROP CONSTRAINT "select"';
    }

    protected function getStringLiteralQuoteCharacter()
    {
        return "'";
    }

    public function testGetStringLiteralQuoteCharacter()
    {
        self::assertSame($this->getStringLiteralQuoteCharacter(), $this->_platform->getStringLiteralQuoteCharacter());
    }

    protected function getQuotedCommentOnColumnSQLWithoutQuoteCharacter()
    {
        return "COMMENT ON COLUMN mytable.id IS 'This is a comment'";
    }

    public function testGetCommentOnColumnSQLWithoutQuoteCharacter()
    {
        self::assertEquals(
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

        self::assertEquals(
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
        self::assertSame(
            $this->getCommentOnColumnSQL(),
            array(
                $this->_platform->getCommentOnColumnSQL('foo', 'bar', 'comment'), // regular identifiers
                $this->_platform->getCommentOnColumnSQL('`Foo`', '`BAR`', 'comment'), // explicitly quoted identifiers
                $this->_platform->getCommentOnColumnSQL('select', 'from', 'comment'), // reserved keyword identifiers
            )
        );
    }

    /**
     * @group DBAL-1176
     *
     * @dataProvider getGeneratesInlineColumnCommentSQL
     */
    public function testGeneratesInlineColumnCommentSQL($comment, $expectedSql)
    {
        if (! $this->_platform->supportsInlineColumnComments()) {
            $this->markTestSkipped(sprintf('%s does not support inline column comments.', get_class($this->_platform)));
        }

        self::assertSame($expectedSql, $this->_platform->getInlineColumnCommentSQL($comment));
    }

    public function getGeneratesInlineColumnCommentSQL()
    {
        return array(
            'regular comment' => array('Regular comment', $this->getInlineColumnRegularCommentSQL()),
            'comment requiring escaping' => array(
                sprintf(
                    'Using inline comment delimiter %s works',
                    $this->getInlineColumnCommentDelimiter()
                ),
                $this->getInlineColumnCommentRequiringEscapingSQL()
            ),
            'empty comment' => array('', $this->getInlineColumnEmptyCommentSQL()),
        );
    }

    protected function getInlineColumnCommentDelimiter()
    {
        return "'";
    }

    protected function getInlineColumnRegularCommentSQL()
    {
        return "COMMENT 'Regular comment'";
    }

    protected function getInlineColumnCommentRequiringEscapingSQL()
    {
        return "COMMENT 'Using inline comment delimiter '' works'";
    }

    protected function getInlineColumnEmptyCommentSQL()
    {
        return "COMMENT ''";
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

    /**
     * @group DBAL-1176
     */
    public function testThrowsExceptionOnGeneratingInlineColumnCommentSQLIfUnsupported()
    {
        if ($this->_platform->supportsInlineColumnComments()) {
            $this->markTestSkipped(sprintf('%s supports inline column comments.', get_class($this->_platform)));
        }

        $this->expectException(
            'Doctrine\DBAL\DBALException',
            "Operation 'Doctrine\\DBAL\\Platforms\\AbstractPlatform::getInlineColumnCommentSQL' is not supported by platform.",
            0
        );

        $this->_platform->getInlineColumnCommentSQL('unsupported');
    }

    public function testQuoteStringLiteral()
    {
        $c = $this->getStringLiteralQuoteCharacter();

        self::assertEquals(
            $this->getQuotedStringLiteralWithoutQuoteCharacter(),
            $this->_platform->quoteStringLiteral('No quote')
        );
        self::assertEquals(
            $this->getQuotedStringLiteralWithQuoteCharacter(),
            $this->_platform->quoteStringLiteral('It' . $c . 's a quote')
        );
        self::assertEquals(
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

        self::assertSame($this->getAlterTableRenameColumnSQL(), $this->_platform->getAlterTableSQL($tableDiff));
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

        self::assertSame(
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

        self::assertEquals($expectedSql, $sql);
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

        self::assertSame(
            $this->getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(),
            $this->_platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @return array
     */
    abstract protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL();

    /**
     * @group DBAL-1082
     *
     * @dataProvider getGeneratesDecimalTypeDeclarationSQL
     */
    public function testGeneratesDecimalTypeDeclarationSQL(array $column, $expectedSql)
    {
        self::assertSame($expectedSql, $this->_platform->getDecimalTypeDeclarationSQL($column));
    }

    /**
     * @return array
     */
    public function getGeneratesDecimalTypeDeclarationSQL()
    {
        return array(
            array(array(), 'NUMERIC(10, 0)'),
            array(array('unsigned' => true), 'NUMERIC(10, 0)'),
            array(array('unsigned' => false), 'NUMERIC(10, 0)'),
            array(array('precision' => 5), 'NUMERIC(5, 0)'),
            array(array('scale' => 5), 'NUMERIC(10, 5)'),
            array(array('precision' => 8, 'scale' => 2), 'NUMERIC(8, 2)'),
        );
    }

    /**
     * @group DBAL-1082
     *
     * @dataProvider getGeneratesFloatDeclarationSQL
     */
    public function testGeneratesFloatDeclarationSQL(array $column, $expectedSql)
    {
        self::assertSame($expectedSql, $this->_platform->getFloatDeclarationSQL($column));
    }

    /**
     * @return array
     */
    public function getGeneratesFloatDeclarationSQL()
    {
        return array(
            array(array(), 'DOUBLE PRECISION'),
            array(array('unsigned' => true), 'DOUBLE PRECISION'),
            array(array('unsigned' => false), 'DOUBLE PRECISION'),
            array(array('precision' => 5), 'DOUBLE PRECISION'),
            array(array('scale' => 5), 'DOUBLE PRECISION'),
            array(array('precision' => 8, 'scale' => 2), 'DOUBLE PRECISION'),
        );
    }

    public function testItEscapesStringsForLike() : void
    {
        self::assertSame(
            '\_25\% off\_ your next purchase \\\\o/',
            $this->_platform->escapeStringForLike('_25% off_ your next purchase \o/', '\\')
        );
    }
}

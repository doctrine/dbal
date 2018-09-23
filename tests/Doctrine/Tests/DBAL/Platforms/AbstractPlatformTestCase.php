<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalTestCase;
use Doctrine\Tests\Types\CommentedType;
use function get_class;
use function implode;
use function sprintf;
use function str_repeat;

abstract class AbstractPlatformTestCase extends DbalTestCase
{
    /** @var AbstractPlatform */
    protected $platform;

    abstract public function createPlatform();

    protected function setUp()
    {
        $this->platform = $this->createPlatform();
    }

    /**
     * @group DDC-1360
     */
    public function testQuoteIdentifier()
    {
        if ($this->platform->getName() === 'mssql') {
            $this->markTestSkipped('Not working this way on mssql.');
        }

        $c = $this->platform->getIdentifierQuoteCharacter();
        self::assertEquals($c . 'test' . $c, $this->platform->quoteIdentifier('test'));
        self::assertEquals($c . 'test' . $c . '.' . $c . 'test' . $c, $this->platform->quoteIdentifier('test.test'));
        self::assertEquals(str_repeat($c, 4), $this->platform->quoteIdentifier($c));
    }

    /**
     * @group DDC-1360
     */
    public function testQuoteSingleIdentifier()
    {
        if ($this->platform->getName() === 'mssql') {
            $this->markTestSkipped('Not working this way on mssql.');
        }

        $c = $this->platform->getIdentifierQuoteCharacter();
        self::assertEquals($c . 'test' . $c, $this->platform->quoteSingleIdentifier('test'));
        self::assertEquals($c . 'test.test' . $c, $this->platform->quoteSingleIdentifier('test.test'));
        self::assertEquals(str_repeat($c, 4), $this->platform->quoteSingleIdentifier($c));
    }

    /**
     * @group DBAL-1029
     * @dataProvider getReturnsForeignKeyReferentialActionSQL
     */
    public function testReturnsForeignKeyReferentialActionSQL($action, $expectedSQL)
    {
        self::assertSame($expectedSQL, $this->platform->getForeignKeyReferentialActionSQL($action));
    }

    /**
     * @return string[][]
     */
    public function getReturnsForeignKeyReferentialActionSQL()
    {
        return [
            ['CASCADE', 'CASCADE'],
            ['SET NULL', 'SET NULL'],
            ['NO ACTION', 'NO ACTION'],
            ['RESTRICT', 'RESTRICT'],
            ['SET DEFAULT', 'SET DEFAULT'],
            ['CaScAdE', 'CASCADE'],
        ];
    }

    public function testGetInvalidForeignKeyReferentialActionSQL()
    {
        $this->expectException('InvalidArgumentException');
        $this->platform->getForeignKeyReferentialActionSQL('unknown');
    }

    public function testGetUnknownDoctrineMappingType()
    {
        $this->expectException(DBALException::class);
        $this->platform->getDoctrineTypeMapping('foobar');
    }

    public function testRegisterDoctrineMappingType()
    {
        $this->platform->registerDoctrineTypeMapping('foo', 'integer');
        self::assertEquals('integer', $this->platform->getDoctrineTypeMapping('foo'));
    }

    public function testRegisterUnknownDoctrineMappingType()
    {
        $this->expectException(DBALException::class);
        $this->platform->registerDoctrineTypeMapping('foo', 'bar');
    }

    /**
     * @group DBAL-2594
     */
    public function testRegistersCommentedDoctrineMappingTypeImplicitly()
    {
        if (! Type::hasType('my_commented')) {
            Type::addType('my_commented', CommentedType::class);
        }

        $type = Type::getType('my_commented');
        $this->platform->registerDoctrineTypeMapping('foo', 'my_commented');

        self::assertTrue($this->platform->isCommentedDoctrineType($type));
    }

    /**
     * @group DBAL-939
     * @dataProvider getIsCommentedDoctrineType
     */
    public function testIsCommentedDoctrineType(Type $type, $commented)
    {
        self::assertSame($commented, $this->platform->isCommentedDoctrineType($type));
    }

    public function getIsCommentedDoctrineType()
    {
        $this->setUp();

        $data = [];

        foreach (Type::getTypesMap() as $typeName => $className) {
            $type = Type::getType($typeName);

            $data[$typeName] = [
                $type,
                $type->requiresSQLCommentHint($this->platform),
            ];
        }

        return $data;
    }

    public function testCreateWithNoColumns()
    {
        $table = new Table('test');

        $this->expectException(DBALException::class);
        $sql = $this->platform->getCreateTableSQL($table);
    }

    public function testGeneratesTableCreationSql()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('test', 'string', ['notnull' => false, 'length' => 255]);
        $table->setPrimaryKey(['id']);

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getGenerateTableSql(), $sql[0]);
    }

    abstract public function getGenerateTableSql();

    public function testGenerateTableWithMultiColumnUniqueIndex()
    {
        $table = new Table('test');
        $table->addColumn('foo', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('bar', 'string', ['notnull' => false, 'length' => 255]);
        $table->addUniqueIndex(['foo', 'bar']);

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getGenerateTableWithMultiColumnUniqueIndexSql(), $sql);
    }

    abstract public function getGenerateTableWithMultiColumnUniqueIndexSql();

    public function testGeneratesIndexCreationSql()
    {
        $indexDef = new Index('my_idx', ['user_name', 'last_login']);

        self::assertEquals(
            $this->getGenerateIndexSql(),
            $this->platform->getCreateIndexSQL($indexDef, 'mytable')
        );
    }

    abstract public function getGenerateIndexSql();

    public function testGeneratesUniqueIndexCreationSql()
    {
        $indexDef = new Index('index_name', ['test', 'test2'], true);

        $sql = $this->platform->getCreateIndexSQL($indexDef, 'test');
        self::assertEquals($this->getGenerateUniqueIndexSql(), $sql);
    }

    abstract public function getGenerateUniqueIndexSql();

    public function testGeneratesPartialIndexesSqlOnlyWhenSupportingPartialIndexes()
    {
        $where       = 'test IS NULL AND test2 IS NOT NULL';
        $indexDef    = new Index('name', ['test', 'test2'], false, false, [], ['where' => $where]);
        $uniqueIndex = new Index('name', ['test', 'test2'], true, false, [], ['where' => $where]);

        $expected = ' WHERE ' . $where;

        $actuals = [];

        if ($this->supportsInlineIndexDeclaration()) {
            $actuals[] = $this->platform->getIndexDeclarationSQL('name', $indexDef);
        }

        $actuals[] = $this->platform->getUniqueConstraintDeclarationSQL('name', $uniqueIndex);
        $actuals[] = $this->platform->getCreateIndexSQL($indexDef, 'table');

        foreach ($actuals as $actual) {
            if ($this->platform->supportsPartialIndexes()) {
                self::assertStringEndsWith($expected, $actual, 'WHERE clause should be present');
            } else {
                self::assertStringEndsNotWith($expected, $actual, 'WHERE clause should NOT be present');
            }
        }
    }

    public function testGeneratesForeignKeyCreationSql()
    {
        $fk = new ForeignKeyConstraint(['fk_name_id'], 'other_table', ['id'], '');

        $sql = $this->platform->getCreateForeignKeySQL($fk, 'test');
        self::assertEquals($sql, $this->getGenerateForeignKeySql());
    }

    abstract public function getGenerateForeignKeySql();

    public function testGeneratesConstraintCreationSql()
    {
        $idx = new Index('constraint_name', ['test'], true, false);
        $sql = $this->platform->getCreateConstraintSQL($idx, 'test');
        self::assertEquals($this->getGenerateConstraintUniqueIndexSql(), $sql);

        $pk  = new Index('constraint_name', ['test'], true, true);
        $sql = $this->platform->getCreateConstraintSQL($pk, 'test');
        self::assertEquals($this->getGenerateConstraintPrimaryIndexSql(), $sql);

        $fk  = new ForeignKeyConstraint(['fk_name'], 'foreign', ['id'], 'constraint_fk');
        $sql = $this->platform->getCreateConstraintSQL($fk, 'test');
        self::assertEquals($this->getGenerateConstraintForeignKeySql($fk), $sql);
    }

    public function testGeneratesForeignKeySqlOnlyWhenSupportingForeignKeys()
    {
        $fk = new ForeignKeyConstraint(['fk_name'], 'foreign', ['id'], 'constraint_fk');

        if ($this->platform->supportsForeignKeyConstraints()) {
            self::assertInternalType(
                'string',
                $this->platform->getCreateForeignKeySQL($fk, 'test')
            );
        } else {
            $this->expectException(DBALException::class);
            $this->platform->getCreateForeignKeySQL($fk, 'test');
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
        $sql = $this->platform->getBitAndComparisonExpression(2, 4);
        self::assertEquals($this->getBitAndComparisonExpressionSql(2, 4), $sql);
    }

    protected function getBitOrComparisonExpressionSql($value1, $value2)
    {
        return '(' . $value1 . ' | ' . $value2 . ')';
    }

    /**
     * @group DDC-1213
     */
    public function testGeneratesBitOrComparisonExpressionSql()
    {
        $sql = $this->platform->getBitOrComparisonExpression(2, 4);
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
        $quotedForeignTable = $fk->getQuotedForeignTableName($this->platform);

        return sprintf(
            'ALTER TABLE test ADD CONSTRAINT constraint_fk FOREIGN KEY (fk_name) REFERENCES %s (id)',
            $quotedForeignTable
        );
    }

    abstract public function getGenerateAlterTableSql();

    public function testGeneratesTableAlterationSql()
    {
        $expectedSql = $this->getGenerateAlterTableSql();

        $table = new Table('mytable');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'string');
        $table->addColumn('bloo', 'boolean');
        $table->setPrimaryKey(['id']);

        $tableDiff                         = new TableDiff('mytable');
        $tableDiff->fromTable              = $table;
        $tableDiff->newName                = 'userlist';
        $tableDiff->addedColumns['quota']  = new Column('quota', Type::getType('integer'), ['notnull' => false]);
        $tableDiff->removedColumns['foo']  = new Column('foo', Type::getType('integer'));
        $tableDiff->changedColumns['bar']  = new ColumnDiff(
            'bar',
            new Column(
                'baz',
                Type::getType('string'),
                ['default' => 'def']
            ),
            ['type', 'notnull', 'default']
        );
        $tableDiff->changedColumns['bloo'] = new ColumnDiff(
            'bloo',
            new Column(
                'bloo',
                Type::getType('boolean'),
                ['default' => false]
            ),
            ['type', 'notnull', 'default']
        );

        $sql = $this->platform->getAlterTableSQL($tableDiff);

        self::assertEquals($expectedSql, $sql);
    }

    public function testGetCustomColumnDeclarationSql()
    {
        $field = ['columnDefinition' => 'MEDIUMINT(6) UNSIGNED'];
        self::assertEquals('foo MEDIUMINT(6) UNSIGNED', $this->platform->getColumnDeclarationSQL('foo', $field));
    }

    public function testGetCreateTableSqlDispatchEvent()
    {
        $listenerMock = $this->getMockBuilder('GetCreateTableSqlDispatchEvenListener')
            ->setMethods(['onSchemaCreateTable', 'onSchemaCreateTableColumn'])
            ->getMock();
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaCreateTable');
        $listenerMock
            ->expects($this->exactly(2))
            ->method('onSchemaCreateTableColumn');

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::onSchemaCreateTable, Events::onSchemaCreateTableColumn], $listenerMock);

        $this->platform->setEventManager($eventManager);

        $table = new Table('test');
        $table->addColumn('foo', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('bar', 'string', ['notnull' => false, 'length' => 255]);

        $this->platform->getCreateTableSQL($table);
    }

    public function testGetDropTableSqlDispatchEvent()
    {
        $listenerMock = $this->getMockBuilder('GetDropTableSqlDispatchEventListener')
            ->setMethods(['onSchemaDropTable'])
            ->getMock();
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaDropTable');

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::onSchemaDropTable], $listenerMock);

        $this->platform->setEventManager($eventManager);

        $this->platform->getDropTableSQL('TABLE');
    }

    public function testGetAlterTableSqlDispatchEvent()
    {
        $events = [
            'onSchemaAlterTable',
            'onSchemaAlterTableAddColumn',
            'onSchemaAlterTableRemoveColumn',
            'onSchemaAlterTableChangeColumn',
            'onSchemaAlterTableRenameColumn',
        ];

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
        $events       = [
            Events::onSchemaAlterTable,
            Events::onSchemaAlterTableAddColumn,
            Events::onSchemaAlterTableRemoveColumn,
            Events::onSchemaAlterTableChangeColumn,
            Events::onSchemaAlterTableRenameColumn,
        ];
        $eventManager->addEventListener($events, $listenerMock);

        $this->platform->setEventManager($eventManager);

        $table = new Table('mytable');
        $table->addColumn('removed', 'integer');
        $table->addColumn('changed', 'integer');
        $table->addColumn('renamed', 'integer');

        $tableDiff                            = new TableDiff('mytable');
        $tableDiff->fromTable                 = $table;
        $tableDiff->addedColumns['added']     = new Column('added', Type::getType('integer'), []);
        $tableDiff->removedColumns['removed'] = new Column('removed', Type::getType('integer'), []);
        $tableDiff->changedColumns['changed'] = new ColumnDiff(
            'changed',
            new Column(
                'changed2',
                Type::getType('string'),
                []
            ),
            []
        );
        $tableDiff->renamedColumns['renamed'] = new Column('renamed2', Type::getType('integer'), []);

        $this->platform->getAlterTableSQL($tableDiff);
    }

    /**
     * @group DBAL-42
     */
    public function testCreateTableColumnComments()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer', ['comment' => 'This is a comment']);
        $table->setPrimaryKey(['id']);

        self::assertEquals($this->getCreateTableColumnCommentsSQL(), $this->platform->getCreateTableSQL($table));
    }

    /**
     * @group DBAL-42
     */
    public function testAlterTableColumnComments()
    {
        $tableDiff                        = new TableDiff('mytable');
        $tableDiff->addedColumns['quota'] = new Column('quota', Type::getType('integer'), ['comment' => 'A comment']);
        $tableDiff->changedColumns['foo'] = new ColumnDiff(
            'foo',
            new Column(
                'foo',
                Type::getType('string')
            ),
            ['comment']
        );
        $tableDiff->changedColumns['bar'] = new ColumnDiff(
            'bar',
            new Column(
                'baz',
                Type::getType('string'),
                ['comment' => 'B comment']
            ),
            ['comment']
        );

        self::assertEquals($this->getAlterTableColumnCommentsSQL(), $this->platform->getAlterTableSQL($tableDiff));
    }

    public function testCreateTableColumnTypeComments()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('data', 'array');
        $table->setPrimaryKey(['id']);

        self::assertEquals($this->getCreateTableColumnTypeCommentsSQL(), $this->platform->getCreateTableSQL($table));
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
        $field = [
            'type' => Type::getType('string'),
            'default' => 'non_timestamp',
        ];

        self::assertEquals(" DEFAULT 'non_timestamp'", $this->platform->getDefaultValueDeclarationSQL($field));
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
                'default' => $this->platform->getCurrentTimestampSQL(),
            ];

            self::assertSame(
                ' DEFAULT ' . $this->platform->getCurrentTimestampSQL(),
                $this->platform->getDefaultValueDeclarationSQL($field)
            );
        }
    }

    public function testGetDefaultValueDeclarationSQLForIntegerTypes()
    {
        foreach (['bigint', 'integer', 'smallint'] as $type) {
            $field = [
                'type'    => Type::getType($type),
                'default' => 1,
            ];

            self::assertEquals(
                ' DEFAULT 1',
                $this->platform->getDefaultValueDeclarationSQL($field)
            );
        }
    }

    /**
     * @group 2859
     */
    public function testGetDefaultValueDeclarationSQLForDateType() : void
    {
        $currentDateSql = $this->platform->getCurrentDateSQL();
        foreach (['date', 'date_immutable'] as $type) {
            $field = [
                'type'    => Type::getType($type),
                'default' => $currentDateSql,
            ];

            self::assertSame(
                ' DEFAULT ' . $currentDateSql,
                $this->platform->getDefaultValueDeclarationSQL($field)
            );
        }
    }

    /**
     * @group DBAL-45
     */
    public function testKeywordList()
    {
        $keywordList = $this->platform->getReservedKeywordsList();
        self::assertInstanceOf(KeywordList::class, $keywordList);

        self::assertTrue($keywordList->isKeyword('table'));
    }

    /**
     * @group DBAL-374
     */
    public function testQuotedColumnInPrimaryKeyPropagation()
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', 'string');
        $table->setPrimaryKey(['create']);

        $sql = $this->platform->getCreateTableSQL($table);
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
        $table->addIndex(['create']);

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getQuotedColumnInIndexSQL(), $sql);
    }

    public function testQuotedNameInIndexSQL()
    {
        $table = new Table('test');
        $table->addColumn('column1', 'string');
        $table->addIndex(['column1'], '`key`');

        $sql = $this->platform->getCreateTableSQL($table);
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

        $table->addForeignKeyConstraint($foreignTable, ['create', 'foo', '`bar`'], ['create', 'bar', '`foo-bar`'], [], 'FK_WITH_RESERVED_KEYWORD');

        // Foreign table with non-reserved keyword as name (does not need quotation).
        $foreignTable = new Table('foo');
        $foreignTable->addColumn('create', 'string');    // Foreign column with reserved keyword as name (needs quotation).
        $foreignTable->addColumn('bar', 'string');       // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('`foo-bar`', 'string'); // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).

        $table->addForeignKeyConstraint($foreignTable, ['create', 'foo', '`bar`'], ['create', 'bar', '`foo-bar`'], [], 'FK_WITH_NON_RESERVED_KEYWORD');

        // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $foreignTable = new Table('`foo-bar`');
        $foreignTable->addColumn('create', 'string');    // Foreign column with reserved keyword as name (needs quotation).
        $foreignTable->addColumn('bar', 'string');       // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('`foo-bar`', 'string'); // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).

        $table->addForeignKeyConstraint($foreignTable, ['create', 'foo', '`bar`'], ['create', 'bar', '`foo-bar`'], [], 'FK_WITH_INTENDED_QUOTATION');

        $sql = $this->platform->getCreateTableSQL($table, AbstractPlatform::CREATE_FOREIGNKEYS);
        self::assertEquals($this->getQuotedColumnInForeignKeySQL(), $sql);
    }

    /**
     * @group DBAL-1051
     */
    public function testQuotesReservedKeywordInUniqueConstraintDeclarationSQL()
    {
        $index = new Index('select', ['foo'], true);

        self::assertSame(
            $this->getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(),
            $this->platform->getUniqueConstraintDeclarationSQL('select', $index)
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
            $this->platform->getTruncateTableSQL('select')
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
        $index = new Index('select', ['foo']);

        if (! $this->supportsInlineIndexDeclaration()) {
            $this->expectException(DBALException::class);
        }

        self::assertSame(
            $this->getQuotesReservedKeywordInIndexDeclarationSQL(),
            $this->platform->getIndexDeclarationSQL('select', $index)
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
        self::assertSame($this->supportsCommentOnStatement(), $this->platform->supportsCommentOnStatement());
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
        $this->platform->getCreateSchemaSQL('schema');
    }

    /**
     * @group DBAL-585
     */
    public function testAlterTableChangeQuotedColumn()
    {
        $tableDiff                        = new TableDiff('mytable');
        $tableDiff->fromTable             = new Table('mytable');
        $tableDiff->changedColumns['foo'] = new ColumnDiff(
            'select',
            new Column(
                'select',
                Type::getType('string')
            ),
            ['type']
        );

        self::assertContains(
            $this->platform->quoteIdentifier('select'),
            implode(';', $this->platform->getAlterTableSQL($tableDiff))
        );
    }

    /**
     * @group DBAL-563
     */
    public function testUsesSequenceEmulatedIdentityColumns()
    {
        self::assertFalse($this->platform->usesSequenceEmulatedIdentityColumns());
    }

    /**
     * @group DBAL-563
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testReturnsIdentitySequenceName()
    {
        $this->platform->getIdentitySequenceName('mytable', 'mycolumn');
    }

    public function testReturnsBinaryDefaultLength()
    {
        self::assertSame($this->getBinaryDefaultLength(), $this->platform->getBinaryDefaultLength());
    }

    protected function getBinaryDefaultLength()
    {
        return 255;
    }

    public function testReturnsBinaryMaxLength()
    {
        self::assertSame($this->getBinaryMaxLength(), $this->platform->getBinaryMaxLength());
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
        $this->platform->getBinaryTypeDeclarationSQL([]);
    }

    public function testReturnsBinaryTypeLongerThanMaxDeclarationSQL()
    {
        $this->markTestSkipped('Not applicable to the platform');
    }

    /**
     * @group DBAL-553
     */
    public function hasNativeJsonType()
    {
        self::assertFalse($this->platform->hasNativeJsonType());
    }

    /**
     * @group DBAL-553
     */
    public function testReturnsJsonTypeDeclarationSQL()
    {
        $column = [
            'length'  => 666,
            'notnull' => true,
            'type'    => Type::getType('json_array'),
        ];

        self::assertSame(
            $this->platform->getClobTypeDeclarationSQL($column),
            $this->platform->getJsonTypeDeclarationSQL($column)
        );
    }

    /**
     * @group DBAL-234
     */
    public function testAlterTableRenameIndex()
    {
        $tableDiff            = new TableDiff('mytable');
        $tableDiff->fromTable = new Table('mytable');
        $tableDiff->fromTable->addColumn('id', 'integer');
        $tableDiff->fromTable->setPrimaryKey(['id']);
        $tableDiff->renamedIndexes = [
            'idx_foo' => new Index('idx_bar', ['id']),
        ];

        self::assertSame(
            $this->getAlterTableRenameIndexSQL(),
            $this->platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL()
    {
        return [
            'DROP INDEX idx_foo',
            'CREATE INDEX idx_bar ON mytable (id)',
        ];
    }

    /**
     * @group DBAL-234
     */
    public function testQuotesAlterTableRenameIndex()
    {
        $tableDiff            = new TableDiff('table');
        $tableDiff->fromTable = new Table('table');
        $tableDiff->fromTable->addColumn('id', 'integer');
        $tableDiff->fromTable->setPrimaryKey(['id']);
        $tableDiff->renamedIndexes = [
            'create' => new Index('select', ['id']),
            '`foo`'  => new Index('`bar`', ['id']),
        ];

        self::assertSame(
            $this->getQuotedAlterTableRenameIndexSQL(),
            $this->platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL()
    {
        return [
            'DROP INDEX "create"',
            'CREATE INDEX "select" ON "table" (id)',
            'DROP INDEX "foo"',
            'CREATE INDEX "bar" ON "table" (id)',
        ];
    }

    /**
     * @group DBAL-835
     */
    public function testQuotesAlterTableRenameColumn()
    {
        $fromTable = new Table('mytable');

        $fromTable->addColumn('unquoted1', 'integer', ['comment' => 'Unquoted 1']);
        $fromTable->addColumn('unquoted2', 'integer', ['comment' => 'Unquoted 2']);
        $fromTable->addColumn('unquoted3', 'integer', ['comment' => 'Unquoted 3']);

        $fromTable->addColumn('create', 'integer', ['comment' => 'Reserved keyword 1']);
        $fromTable->addColumn('table', 'integer', ['comment' => 'Reserved keyword 2']);
        $fromTable->addColumn('select', 'integer', ['comment' => 'Reserved keyword 3']);

        $fromTable->addColumn('`quoted1`', 'integer', ['comment' => 'Quoted 1']);
        $fromTable->addColumn('`quoted2`', 'integer', ['comment' => 'Quoted 2']);
        $fromTable->addColumn('`quoted3`', 'integer', ['comment' => 'Quoted 3']);

        $toTable = new Table('mytable');

        $toTable->addColumn('unquoted', 'integer', ['comment' => 'Unquoted 1']); // unquoted -> unquoted
        $toTable->addColumn('where', 'integer', ['comment' => 'Unquoted 2']); // unquoted -> reserved keyword
        $toTable->addColumn('`foo`', 'integer', ['comment' => 'Unquoted 3']); // unquoted -> quoted

        $toTable->addColumn('reserved_keyword', 'integer', ['comment' => 'Reserved keyword 1']); // reserved keyword -> unquoted
        $toTable->addColumn('from', 'integer', ['comment' => 'Reserved keyword 2']); // reserved keyword -> reserved keyword
        $toTable->addColumn('`bar`', 'integer', ['comment' => 'Reserved keyword 3']); // reserved keyword -> quoted

        $toTable->addColumn('quoted', 'integer', ['comment' => 'Quoted 1']); // quoted -> unquoted
        $toTable->addColumn('and', 'integer', ['comment' => 'Quoted 2']); // quoted -> reserved keyword
        $toTable->addColumn('`baz`', 'integer', ['comment' => 'Quoted 3']); // quoted -> quoted

        $comparator = new Comparator();

        self::assertEquals(
            $this->getQuotedAlterTableRenameColumnSQL(),
            $this->platform->getAlterTableSQL($comparator->diffTable($fromTable, $toTable))
        );
    }

    /**
     * Returns SQL statements for {@link testQuotesAlterTableRenameColumn}.
     *
     * @return string[]
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

        $fromTable->addColumn('unquoted1', 'string', ['comment' => 'Unquoted 1', 'length' => 10]);
        $fromTable->addColumn('unquoted2', 'string', ['comment' => 'Unquoted 2', 'length' => 10]);
        $fromTable->addColumn('unquoted3', 'string', ['comment' => 'Unquoted 3', 'length' => 10]);

        $fromTable->addColumn('create', 'string', ['comment' => 'Reserved keyword 1', 'length' => 10]);
        $fromTable->addColumn('table', 'string', ['comment' => 'Reserved keyword 2', 'length' => 10]);
        $fromTable->addColumn('select', 'string', ['comment' => 'Reserved keyword 3', 'length' => 10]);

        $toTable = new Table('mytable');

        $toTable->addColumn('unquoted1', 'string', ['comment' => 'Unquoted 1', 'length' => 255]);
        $toTable->addColumn('unquoted2', 'string', ['comment' => 'Unquoted 2', 'length' => 255]);
        $toTable->addColumn('unquoted3', 'string', ['comment' => 'Unquoted 3', 'length' => 255]);

        $toTable->addColumn('create', 'string', ['comment' => 'Reserved keyword 1', 'length' => 255]);
        $toTable->addColumn('table', 'string', ['comment' => 'Reserved keyword 2', 'length' => 255]);
        $toTable->addColumn('select', 'string', ['comment' => 'Reserved keyword 3', 'length' => 255]);

        $comparator = new Comparator();

        self::assertEquals(
            $this->getQuotedAlterTableChangeColumnLengthSQL(),
            $this->platform->getAlterTableSQL($comparator->diffTable($fromTable, $toTable))
        );
    }

    /**
     * Returns SQL statements for {@link testQuotesAlterTableChangeColumnLength}.
     *
     * @return string[]
     *
     * @group DBAL-835
     */
    abstract protected function getQuotedAlterTableChangeColumnLengthSQL();

    /**
     * @group DBAL-807
     */
    public function testAlterTableRenameIndexInSchema()
    {
        $tableDiff            = new TableDiff('myschema.mytable');
        $tableDiff->fromTable = new Table('myschema.mytable');
        $tableDiff->fromTable->addColumn('id', 'integer');
        $tableDiff->fromTable->setPrimaryKey(['id']);
        $tableDiff->renamedIndexes = [
            'idx_foo' => new Index('idx_bar', ['id']),
        ];

        self::assertSame(
            $this->getAlterTableRenameIndexInSchemaSQL(),
            $this->platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @group DBAL-807
     */
    protected function getAlterTableRenameIndexInSchemaSQL()
    {
        return [
            'DROP INDEX idx_foo',
            'CREATE INDEX idx_bar ON myschema.mytable (id)',
        ];
    }

    /**
     * @group DBAL-807
     */
    public function testQuotesAlterTableRenameIndexInSchema()
    {
        $tableDiff            = new TableDiff('`schema`.table');
        $tableDiff->fromTable = new Table('`schema`.table');
        $tableDiff->fromTable->addColumn('id', 'integer');
        $tableDiff->fromTable->setPrimaryKey(['id']);
        $tableDiff->renamedIndexes = [
            'create' => new Index('select', ['id']),
            '`foo`'  => new Index('`bar`', ['id']),
        ];

        self::assertSame(
            $this->getQuotedAlterTableRenameIndexInSchemaSQL(),
            $this->platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL()
    {
        return [
            'DROP INDEX "schema"."create"',
            'CREATE INDEX "select" ON "schema"."table" (id)',
            'DROP INDEX "schema"."foo"',
            'CREATE INDEX "bar" ON "schema"."table" (id)',
        ];
    }

    /**
     * @group DBAL-1237
     */
    public function testQuotesDropForeignKeySQL()
    {
        if (! $this->platform->supportsForeignKeyConstraints()) {
            $this->markTestSkipped(
                sprintf('%s does not support foreign key constraints.', get_class($this->platform))
            );
        }

        $tableName      = 'table';
        $table          = new Table($tableName);
        $foreignKeyName = 'select';
        $foreignKey     = new ForeignKeyConstraint([], 'foo', [], 'select');
        $expectedSql    = $this->getQuotesDropForeignKeySQL();

        self::assertSame($expectedSql, $this->platform->getDropForeignKeySQL($foreignKeyName, $tableName));
        self::assertSame($expectedSql, $this->platform->getDropForeignKeySQL($foreignKey, $table));
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
        $tableName      = 'table';
        $table          = new Table($tableName);
        $constraintName = 'select';
        $constraint     = new ForeignKeyConstraint([], 'foo', [], 'select');
        $expectedSql    = $this->getQuotesDropConstraintSQL();

        self::assertSame($expectedSql, $this->platform->getDropConstraintSQL($constraintName, $tableName));
        self::assertSame($expectedSql, $this->platform->getDropConstraintSQL($constraint, $table));
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
        self::assertSame($this->getStringLiteralQuoteCharacter(), $this->platform->getStringLiteralQuoteCharacter());
    }

    protected function getQuotedCommentOnColumnSQLWithoutQuoteCharacter()
    {
        return "COMMENT ON COLUMN mytable.id IS 'This is a comment'";
    }

    public function testGetCommentOnColumnSQLWithoutQuoteCharacter()
    {
        self::assertEquals(
            $this->getQuotedCommentOnColumnSQLWithoutQuoteCharacter(),
            $this->platform->getCommentOnColumnSQL('mytable', 'id', 'This is a comment')
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
            $this->platform->getCommentOnColumnSQL('mytable', 'id', 'It' . $c . 's a quote !')
        );
    }

    /**
     * @see testGetCommentOnColumnSQL
     *
     * @return string[]
     */
    abstract protected function getCommentOnColumnSQL();

    /**
     * @group DBAL-1004
     */
    public function testGetCommentOnColumnSQL()
    {
        self::assertSame(
            $this->getCommentOnColumnSQL(),
            [
                $this->platform->getCommentOnColumnSQL('foo', 'bar', 'comment'), // regular identifiers
                $this->platform->getCommentOnColumnSQL('`Foo`', '`BAR`', 'comment'), // explicitly quoted identifiers
                $this->platform->getCommentOnColumnSQL('select', 'from', 'comment'), // reserved keyword identifiers
            ]
        );
    }

    /**
     * @group DBAL-1176
     * @dataProvider getGeneratesInlineColumnCommentSQL
     */
    public function testGeneratesInlineColumnCommentSQL($comment, $expectedSql)
    {
        if (! $this->platform->supportsInlineColumnComments()) {
            $this->markTestSkipped(sprintf('%s does not support inline column comments.', get_class($this->platform)));
        }

        self::assertSame($expectedSql, $this->platform->getInlineColumnCommentSQL($comment));
    }

    public function getGeneratesInlineColumnCommentSQL()
    {
        return [
            'regular comment' => ['Regular comment', $this->getInlineColumnRegularCommentSQL()],
            'comment requiring escaping' => [
                sprintf(
                    'Using inline comment delimiter %s works',
                    $this->getInlineColumnCommentDelimiter()
                ),
                $this->getInlineColumnCommentRequiringEscapingSQL(),
            ],
            'empty comment' => ['', $this->getInlineColumnEmptyCommentSQL()],
        ];
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
        if ($this->platform->supportsInlineColumnComments()) {
            $this->markTestSkipped(sprintf('%s supports inline column comments.', get_class($this->platform)));
        }

        $this->expectException(DBALException::class);
        $this->expectExceptionMessage("Operation 'Doctrine\\DBAL\\Platforms\\AbstractPlatform::getInlineColumnCommentSQL' is not supported by platform.");
        $this->expectExceptionCode(0);

        $this->platform->getInlineColumnCommentSQL('unsupported');
    }

    public function testQuoteStringLiteral()
    {
        $c = $this->getStringLiteralQuoteCharacter();

        self::assertEquals(
            $this->getQuotedStringLiteralWithoutQuoteCharacter(),
            $this->platform->quoteStringLiteral('No quote')
        );
        self::assertEquals(
            $this->getQuotedStringLiteralWithQuoteCharacter(),
            $this->platform->quoteStringLiteral('It' . $c . 's a quote')
        );
        self::assertEquals(
            $this->getQuotedStringLiteralQuoteCharacter(),
            $this->platform->quoteStringLiteral($c)
        );
    }

    /**
     * @group DBAL-423
     * @expectedException \Doctrine\DBAL\DBALException
     */
    public function testReturnsGuidTypeDeclarationSQL()
    {
        $this->platform->getGuidTypeDeclarationSQL([]);
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
            ['notnull' => true, 'default' => 666, 'comment' => 'rename test']
        );

        $tableDiff                        = new TableDiff('foo');
        $tableDiff->fromTable             = $table;
        $tableDiff->renamedColumns['bar'] = new Column(
            'baz',
            Type::getType('integer'),
            ['notnull' => true, 'default' => 666, 'comment' => 'rename test']
        );

        self::assertSame($this->getAlterTableRenameColumnSQL(), $this->platform->getAlterTableSQL($tableDiff));
    }

    /**
     * @return string[]
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
        $table->addForeignKeyConstraint('fk_table', ['fk'], ['id'], [], 'fk1');
        $table->addForeignKeyConstraint('fk_table', ['fk2'], ['id'], [], 'fk2');

        $tableDiff                        = new TableDiff('"foo"');
        $tableDiff->fromTable             = $table;
        $tableDiff->newName               = 'table';
        $tableDiff->addedColumns['bloo']  = new Column('bloo', Type::getType('integer'));
        $tableDiff->changedColumns['bar'] = new ColumnDiff(
            'bar',
            new Column('bar', Type::getType('integer'), ['notnull' => false]),
            ['notnull'],
            $table->getColumn('bar')
        );
        $tableDiff->renamedColumns['id']  = new Column('war', Type::getType('integer'));
        $tableDiff->removedColumns['baz'] = new Column('baz', Type::getType('integer'));
        $tableDiff->addedForeignKeys[]    = new ForeignKeyConstraint(['fk3'], 'fk_table', ['id'], 'fk_add');
        $tableDiff->changedForeignKeys[]  = new ForeignKeyConstraint(['fk2'], 'fk_table2', ['id'], 'fk2');
        $tableDiff->removedForeignKeys[]  = new ForeignKeyConstraint(['fk'], 'fk_table', ['id'], 'fk1');

        self::assertSame(
            $this->getQuotesTableIdentifiersInAlterTableSQL(),
            $this->platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @return string[]
     */
    abstract protected function getQuotesTableIdentifiersInAlterTableSQL();

    /**
     * @group DBAL-1090
     */
    public function testAlterStringToFixedString()
    {
        $table = new Table('mytable');
        $table->addColumn('name', 'string', ['length' => 2]);

        $tableDiff            = new TableDiff('mytable');
        $tableDiff->fromTable = $table;

        $tableDiff->changedColumns['name'] = new ColumnDiff(
            'name',
            new Column(
                'name',
                Type::getType('string'),
                ['fixed' => true, 'length' => 2]
            ),
            ['fixed']
        );

        $sql = $this->platform->getAlterTableSQL($tableDiff);

        $expectedSql = $this->getAlterStringToFixedStringSQL();

        self::assertEquals($expectedSql, $sql);
    }

    /**
     * @return string[]
     */
    abstract protected function getAlterStringToFixedStringSQL();

    /**
     * @group DBAL-1062
     */
    public function testGeneratesAlterTableRenameIndexUsedByForeignKeySQL()
    {
        $foreignTable = new Table('foreign_table');
        $foreignTable->addColumn('id', 'integer');
        $foreignTable->setPrimaryKey(['id']);

        $primaryTable = new Table('mytable');
        $primaryTable->addColumn('foo', 'integer');
        $primaryTable->addColumn('bar', 'integer');
        $primaryTable->addColumn('baz', 'integer');
        $primaryTable->addIndex(['foo'], 'idx_foo');
        $primaryTable->addIndex(['bar'], 'idx_bar');
        $primaryTable->addForeignKeyConstraint($foreignTable, ['foo'], ['id'], [], 'fk_foo');
        $primaryTable->addForeignKeyConstraint($foreignTable, ['bar'], ['id'], [], 'fk_bar');

        $tableDiff                            = new TableDiff('mytable');
        $tableDiff->fromTable                 = $primaryTable;
        $tableDiff->renamedIndexes['idx_foo'] = new Index('idx_foo_renamed', ['foo']);

        self::assertSame(
            $this->getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(),
            $this->platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @return string[]
     */
    abstract protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL();

    /**
     * @param mixed[] $column
     *
     * @group DBAL-1082
     * @dataProvider getGeneratesDecimalTypeDeclarationSQL
     */
    public function testGeneratesDecimalTypeDeclarationSQL(array $column, $expectedSql)
    {
        self::assertSame($expectedSql, $this->platform->getDecimalTypeDeclarationSQL($column));
    }

    /**
     * @return mixed[]
     */
    public function getGeneratesDecimalTypeDeclarationSQL()
    {
        return [
            [[], 'NUMERIC(10, 0)'],
            [['unsigned' => true], 'NUMERIC(10, 0)'],
            [['unsigned' => false], 'NUMERIC(10, 0)'],
            [['precision' => 5], 'NUMERIC(5, 0)'],
            [['scale' => 5], 'NUMERIC(10, 5)'],
            [['precision' => 8, 'scale' => 2], 'NUMERIC(8, 2)'],
        ];
    }

    /**
     * @param mixed[] $column
     *
     * @group DBAL-1082
     * @dataProvider getGeneratesFloatDeclarationSQL
     */
    public function testGeneratesFloatDeclarationSQL(array $column, $expectedSql)
    {
        self::assertSame($expectedSql, $this->platform->getFloatDeclarationSQL($column));
    }

    /**
     * @return mixed[]
     */
    public function getGeneratesFloatDeclarationSQL()
    {
        return [
            [[], 'DOUBLE PRECISION'],
            [['unsigned' => true], 'DOUBLE PRECISION'],
            [['unsigned' => false], 'DOUBLE PRECISION'],
            [['precision' => 5], 'DOUBLE PRECISION'],
            [['scale' => 5], 'DOUBLE PRECISION'],
            [['precision' => 8, 'scale' => 2], 'DOUBLE PRECISION'],
        ];
    }

    public function testItEscapesStringsForLike() : void
    {
        self::assertSame(
            '\_25\% off\_ your next purchase \\\\o/',
            $this->platform->escapeStringForLike('_25% off_ your next purchase \o/', '\\')
        );
    }

    public function testZeroOffsetWithoutLimitIsIgnored() : void
    {
        $query = 'SELECT * FROM user';

        self::assertSame(
            $query,
            $this->platform->modifyLimitQuery($query, null, 0)
        );
    }
}

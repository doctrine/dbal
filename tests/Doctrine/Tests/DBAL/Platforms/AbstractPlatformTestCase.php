<?php

namespace Doctrine\Tests\DBAL\Platforms;

abstract class AbstractPlatformTestCase extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var Doctrine\DBAL\Platforms\AbstractPlatform
     */
    protected $_platform;

    abstract public function createPlatform();

    public function setUp()
    {
        $this->_platform = $this->createPlatform();
    }

    public function testQuoteIdentifier()
    {
        if ($this->_platform->getName() == "mssql") {
            $this->markTestSkipped('Not working this way on mssql.');
        }

        $c = $this->_platform->getIdentifierQuoteCharacter();
        $this->assertEquals(str_repeat($c, 4), $this->_platform->quoteIdentifier($c));
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
        $table = new \Doctrine\DBAL\Schema\Table('test');

        $this->setExpectedException('Doctrine\DBAL\DBALException');
        $sql = $this->_platform->getCreateTableSQL($table);
    }

    public function testGeneratesTableCreationSql()
    {
        $table = new \Doctrine\DBAL\Schema\Table('test');
        $table->addColumn('id', 'integer', array('notnull' => true, 'autoincrement' => true));
        $table->addColumn('test', 'string', array('notnull' => false, 'length' => 255));
        $table->setPrimaryKey(array('id'));

        $sql = $this->_platform->getCreateTableSQL($table);
        $this->assertEquals($this->getGenerateTableSql(), $sql[0]);
    }

    abstract public function getGenerateTableSql();

    public function testGenerateTableWithMultiColumnUniqueIndex()
    {
        $table = new \Doctrine\DBAL\Schema\Table('test');
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
        $this->assertEquals($this->getGenerateConstraintForeignKeySql(), $sql);
    }

    public function getGenerateConstraintUniqueIndexSql()
    {
        return 'ALTER TABLE test ADD CONSTRAINT constraint_name UNIQUE (test)';
    }

    public function getGenerateConstraintPrimaryIndexSql()
    {
        return 'ALTER TABLE test ADD CONSTRAINT constraint_name PRIMARY KEY (test)';
    }

    public function getGenerateConstraintForeignKeySql()
    {
        return 'ALTER TABLE test ADD CONSTRAINT constraint_fk FOREIGN KEY (fk_name) REFERENCES foreign (id)';
    }

    abstract public function getGenerateAlterTableSql();

    public function testGeneratesTableAlterationSql()
    {
        $expectedSql = $this->getGenerateAlterTableSql();

        $columnDiff = new \Doctrine\DBAL\Schema\ColumnDiff(
            'bar', new \Doctrine\DBAL\Schema\Column(
                'baz', \Doctrine\DBAL\Types\Type::getType('string'), array('default' => 'def')
            ),
            array('type', 'notnull', 'default')
        );

        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff('mytable');
        $tableDiff->newName = 'userlist';
        $tableDiff->addedColumns['quota'] = new \Doctrine\DBAL\Schema\Column('quota', \Doctrine\DBAL\Types\Type::getType('integer'), array('notnull' => false));
        $tableDiff->removedColumns['foo'] = new \Doctrine\DBAL\Schema\Column('foo', \Doctrine\DBAL\Types\Type::getType('integer'));
        $tableDiff->changedColumns['bar'] = $columnDiff;

        $sql = $this->_platform->getAlterTableSQL($tableDiff);

        $this->assertEquals($expectedSql, $sql);
    }

    public function testGetCustomColumnDeclarationSql()
    {
        $field = array('columnDefinition' => 'MEDIUMINT(6) UNSIGNED');
        $this->assertEquals('foo MEDIUMINT(6) UNSIGNED', $this->_platform->getColumnDeclarationSQL('foo', $field));
    }

    /**
     * @group DBAL-42
     */
    public function testCreateTableColumnComments()
    {
        $table = new \Doctrine\DBAL\Schema\Table('test');
        $table->addColumn('id', 'integer', array('comment' => 'This is a comment'));
        $table->setPrimaryKey(array('id'));

        $this->assertEquals($this->getCreateTableColumnCommentsSQL(), $this->_platform->getCreateTableSQL($table));
    }

    /**
     * @group DBAL-42
     */
    public function testAlterTableColumnComments()
    {
        $tableDiff = new \Doctrine\DBAL\Schema\TableDiff('mytable');
        $tableDiff->addedColumns['quota'] = new \Doctrine\DBAL\Schema\Column('quota', \Doctrine\DBAL\Types\Type::getType('integer'), array('comment' => 'A comment'));
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
        $table = new \Doctrine\DBAL\Schema\Table('test');
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
}

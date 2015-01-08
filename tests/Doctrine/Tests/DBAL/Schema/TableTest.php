<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

class TableTest extends \Doctrine\Tests\DbalTestCase
{
    public function testCreateWithInvalidTableName()
    {
        $this->setExpectedException('Doctrine\DBAL\DBALException');
        $table = new \Doctrine\DBAL\Schema\Table('');
    }

    public function testGetName()
    {
        $table =  new Table("foo", array(), array(), array());
        $this->assertEquals("foo", $table->getName());
    }

    public function testColumns()
    {
        $type = Type::getType('integer');
        $columns = array();
        $columns[] = new Column("foo", $type);
        $columns[] = new Column("bar", $type);
        $table = new Table("foo", $columns, array(), array());

        $this->assertTrue($table->hasColumn("foo"));
        $this->assertTrue($table->hasColumn("bar"));
        $this->assertFalse($table->hasColumn("baz"));

        $this->assertInstanceOf('Doctrine\DBAL\Schema\Column', $table->getColumn("foo"));
        $this->assertInstanceOf('Doctrine\DBAL\Schema\Column', $table->getColumn("bar"));

        $this->assertEquals(2, count($table->getColumns()));
    }

    public function testColumnsCaseInsensitive()
    {
        $table = new Table("foo");
        $column = $table->addColumn('Foo', 'integer');

        $this->assertTrue($table->hasColumn('Foo'));
        $this->assertTrue($table->hasColumn('foo'));
        $this->assertTrue($table->hasColumn('FOO'));

        $this->assertSame($column, $table->getColumn('Foo'));
        $this->assertSame($column, $table->getColumn('foo'));
        $this->assertSame($column, $table->getColumn('FOO'));
    }

    public function testCreateColumn()
    {
        $type = Type::getType('integer');

        $table = new Table("foo");

        $this->assertFalse($table->hasColumn("bar"));
        $table->addColumn("bar", 'integer');
        $this->assertTrue($table->hasColumn("bar"));
        $this->assertSame($type, $table->getColumn("bar")->getType());
    }

    public function testDropColumn()
    {
        $type = Type::getType('integer');
        $columns = array();
        $columns[] = new Column("foo", $type);
        $columns[] = new Column("bar", $type);
        $table = new Table("foo", $columns, array(), array());

        $this->assertTrue($table->hasColumn("foo"));
        $this->assertTrue($table->hasColumn("bar"));

        $table->dropColumn("foo")->dropColumn("bar");

        $this->assertFalse($table->hasColumn("foo"));
        $this->assertFalse($table->hasColumn("bar"));
    }

    public function testGetUnknownColumnThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $table = new Table("foo", array(), array(), array());
        $table->getColumn('unknown');
    }

    public function testAddColumnTwiceThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $type = \Doctrine\DBAL\Types\Type::getType('integer');
        $columns = array();
        $columns[] = new Column("foo", $type);
        $columns[] = new Column("foo", $type);
        $table = new Table("foo", $columns, array(), array());
    }

    public function testCreateIndex()
    {
        $type = \Doctrine\DBAL\Types\Type::getType('integer');
        $columns = array(new Column("foo", $type), new Column("bar", $type), new Column("baz", $type));
        $table = new Table("foo", $columns);

        $table->addIndex(array("foo", "bar"), "foo_foo_bar_idx");
        $table->addUniqueIndex(array("bar", "baz"), "foo_bar_baz_uniq");

        $this->assertTrue($table->hasIndex("foo_foo_bar_idx"));
        $this->assertTrue($table->hasIndex("foo_bar_baz_uniq"));
    }

    public function testIndexCaseInsensitive()
    {
        $type = \Doctrine\DBAL\Types\Type::getType('integer');
        $columns = array(
            new Column("foo", $type),
            new Column("bar", $type),
            new Column("baz", $type)
        );
        $table = new Table("foo", $columns);

        $table->addIndex(array("foo", "bar", "baz"), "Foo_Idx");

        $this->assertTrue($table->hasIndex('foo_idx'));
        $this->assertTrue($table->hasIndex('Foo_Idx'));
        $this->assertTrue($table->hasIndex('FOO_IDX'));
    }

    public function testAddIndexes()
    {
        $type = \Doctrine\DBAL\Types\Type::getType('integer');
        $columns = array(
            new Column("foo", $type),
            new Column("bar", $type),
        );
        $indexes = array(
            new Index("the_primary", array("foo"), true, true),
            new Index("bar_idx", array("bar"), false, false),
        );
        $table = new Table("foo", $columns, $indexes, array());

        $this->assertTrue($table->hasIndex("the_primary"));
        $this->assertTrue($table->hasIndex("bar_idx"));
        $this->assertFalse($table->hasIndex("some_idx"));

        $this->assertInstanceOf('Doctrine\DBAL\Schema\Index', $table->getPrimaryKey());
        $this->assertInstanceOf('Doctrine\DBAL\Schema\Index', $table->getIndex('the_primary'));
        $this->assertInstanceOf('Doctrine\DBAL\Schema\Index', $table->getIndex('bar_idx'));
    }

    public function testGetUnknownIndexThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $table = new Table("foo", array(), array(), array());
        $table->getIndex("unknownIndex");
    }

    public function testAddTwoPrimaryThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $type = \Doctrine\DBAL\Types\Type::getType('integer');
        $columns = array(new Column("foo", $type), new Column("bar", $type));
        $indexes = array(
            new Index("the_primary", array("foo"), true, true),
            new Index("other_primary", array("bar"), true, true),
        );
        $table = new Table("foo", $columns, $indexes, array());
    }

    public function testAddTwoIndexesWithSameNameThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $type = \Doctrine\DBAL\Types\Type::getType('integer');
        $columns = array(new Column("foo", $type), new Column("bar", $type));
        $indexes = array(
            new Index("an_idx", array("foo"), false, false),
            new Index("an_idx", array("bar"), false, false),
        );
        $table = new Table("foo", $columns, $indexes, array());
    }

    public function testConstraints()
    {
        $constraint = new ForeignKeyConstraint(array(), "foo", array());

        $tableA = new Table("foo", array(), array(), array($constraint));
        $constraints = $tableA->getForeignKeys();

        $this->assertEquals(1, count($constraints));
        $this->assertSame($constraint, array_shift($constraints));
    }

    public function testOptions()
    {
        $table = new Table("foo", array(), array(), array(), false, array("foo" => "bar"));

        $this->assertTrue($table->hasOption("foo"));
        $this->assertEquals("bar", $table->getOption("foo"));
    }

    public function testBuilderSetPrimaryKey()
    {
        $table = new Table("foo");

        $table->addColumn("bar", 'integer');
        $table->setPrimaryKey(array("bar"));

        $this->assertTrue($table->hasIndex("primary"));
        $this->assertInstanceOf('Doctrine\DBAL\Schema\Index', $table->getPrimaryKey());
        $this->assertTrue($table->getIndex("primary")->isUnique());
        $this->assertTrue($table->getIndex("primary")->isPrimary());
    }

    public function testBuilderAddUniqueIndex()
    {
        $table = new Table("foo");

        $table->addColumn("bar", 'integer');
        $table->addUniqueIndex(array("bar"), "my_idx");

        $this->assertTrue($table->hasIndex("my_idx"));
        $this->assertTrue($table->getIndex("my_idx")->isUnique());
        $this->assertFalse($table->getIndex("my_idx")->isPrimary());
    }

    public function testBuilderAddIndex()
    {
        $table = new Table("foo");

        $table->addColumn("bar", 'integer');
        $table->addIndex(array("bar"), "my_idx");

        $this->assertTrue($table->hasIndex("my_idx"));
        $this->assertFalse($table->getIndex("my_idx")->isUnique());
        $this->assertFalse($table->getIndex("my_idx")->isPrimary());
    }

    public function testBuilderAddIndexWithInvalidNameThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $table = new Table("foo");
        $table->addColumn("bar",'integer');
        $table->addIndex(array("bar"), "invalid name %&/");
    }

    public function testBuilderAddIndexWithUnknownColumnThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $table = new Table("foo");
        $table->addIndex(array("bar"), "invalidName");
    }

    public function testBuilderOptions()
    {
        $table = new Table("foo");
        $table->addOption("foo", "bar");
        $this->assertTrue($table->hasOption("foo"));
        $this->assertEquals("bar", $table->getOption("foo"));
    }

    public function testAddForeignKeyConstraint_UnknownLocalColumn_ThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $table = new Table("foo");
        $table->addColumn("id", 'integer');

        $foreignTable = new Table("bar");
        $foreignTable->addColumn("id", 'integer');

        $table->addForeignKeyConstraint($foreignTable, array("foo"), array("id"));
    }

    public function testAddForeignKeyConstraint_UnknownForeignColumn_ThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $table = new Table("foo");
        $table->addColumn("id", 'integer');

        $foreignTable = new Table("bar");
        $foreignTable->addColumn("id", 'integer');

        $table->addForeignKeyConstraint($foreignTable, array("id"), array("foo"));
    }

    public function testAddForeignKeyConstraint()
    {
        $table = new Table("foo");
        $table->addColumn("id", 'integer');

        $foreignTable = new Table("bar");
        $foreignTable->addColumn("id", 'integer');

        $table->addForeignKeyConstraint($foreignTable, array("id"), array("id"), array("foo" => "bar"));

        $constraints = $table->getForeignKeys();
        $this->assertEquals(1, count($constraints));
        $constraint = current($constraints);

        $this->assertInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint', $constraint);

        $this->assertTrue($constraint->hasOption("foo"));
        $this->assertEquals("bar", $constraint->getOption("foo"));
    }

    public function testAddIndexWithCaseSensitiveColumnProblem()
    {
        $table = new Table("foo");
        $table->addColumn("id", 'integer');

        $table->addIndex(array("ID"), "my_idx");

        $this->assertTrue($table->hasIndex('my_idx'));
        $this->assertEquals(array("ID"), $table->getIndex("my_idx")->getColumns());
        $this->assertTrue($table->getIndex('my_idx')->spansColumns(array('id')));
    }

    public function testAddPrimaryKey_ColumnsAreExplicitlySetToNotNull()
    {
        $table = new Table("foo");
        $column = $table->addColumn("id", 'integer', array('notnull' => false));

        $this->assertFalse($column->getNotnull());

        $table->setPrimaryKey(array('id'));

        $this->assertTrue($column->getNotnull());
    }

    /**
     * @group DDC-133
     */
    public function testAllowImplicitSchemaTableInAutogeneratedIndexNames()
    {
        $table = new Table("foo.bar");
        $table->addColumn('baz', 'integer', array());
        $table->addIndex(array('baz'));

        $this->assertEquals(1, count($table->getIndexes()));
    }

    /**
     * @group DBAL-50
     */
    public function testAddForeignKeyIndexImplicitly()
    {
        $table = new Table("foo");
        $table->addColumn("id", 'integer');

        $foreignTable = new Table("bar");
        $foreignTable->addColumn("id", 'integer');

        $table->addForeignKeyConstraint($foreignTable, array("id"), array("id"), array("foo" => "bar"));

        $indexes = $table->getIndexes();
        $this->assertEquals(1, count($indexes));
        $index = current($indexes);

        $this->assertTrue($table->hasIndex($index->getName()));
        $this->assertEquals(array('id'), $index->getColumns());
    }

    /**
     * @group DBAL-1063
     */
    public function testAddForeignKeyDoesNotCreateDuplicateIndex()
    {
        $table = new Table('foo');
        $table->addColumn('bar', 'integer');
        $table->addIndex(array('bar'), 'bar_idx');

        $foreignTable = new Table('bar');
        $foreignTable->addColumn('foo', 'integer');

        $table->addForeignKeyConstraint($foreignTable, array('bar'), array('foo'));

        $this->assertCount(1, $table->getIndexes());
        $this->assertTrue($table->hasIndex('bar_idx'));
        $this->assertSame(array('bar'), $table->getIndex('bar_idx')->getColumns());
    }

    /**
     * @group DBAL-1063
     */
    public function testAddForeignKeyAddsImplicitIndexIfIndexColumnsDoNotSpan()
    {
        $table = new Table('foo');
        $table->addColumn('bar', 'integer');
        $table->addColumn('baz', 'string');
        $table->addColumn('bloo', 'string');
        $table->addIndex(array('baz', 'bar'), 'composite_idx');
        $table->addIndex(array('bar', 'baz', 'bloo'), 'full_idx');

        $foreignTable = new Table('bar');
        $foreignTable->addColumn('foo', 'integer');
        $foreignTable->addColumn('baz', 'string');

        $table->addForeignKeyConstraint($foreignTable, array('bar', 'baz'), array('foo', 'baz'));

        $this->assertCount(3, $table->getIndexes());
        $this->assertTrue($table->hasIndex('composite_idx'));
        $this->assertTrue($table->hasIndex('full_idx'));
        $this->assertTrue($table->hasIndex('idx_8c73652176ff8caa78240498'));
        $this->assertSame(array('baz', 'bar'), $table->getIndex('composite_idx')->getColumns());
        $this->assertSame(array('bar', 'baz', 'bloo'), $table->getIndex('full_idx')->getColumns());
        $this->assertSame(array('bar', 'baz'), $table->getIndex('idx_8c73652176ff8caa78240498')->getColumns());
    }

    /**
     * @group DBAL-50
     * @group DBAL-1063
     */
    public function testOverrulingIndexDoesNotDropOverruledIndex()
    {
        $table = new Table("bar");
        $table->addColumn('baz', 'integer', array());
        $table->addIndex(array('baz'));

        $indexes = $table->getIndexes();
        $this->assertEquals(1, count($indexes));
        $index = current($indexes);

        $table->addUniqueIndex(array('baz'));
        $this->assertEquals(2, count($table->getIndexes()));
        $this->assertTrue($table->hasIndex($index->getName()));
    }

    /**
     * @group DBAL-1063
     */
    public function testAllowsAddingDuplicateIndexesBasedOnColumns()
    {
        $table = new Table('foo');
        $table->addColumn('bar', 'integer');
        $table->addIndex(array('bar'), 'bar_idx');
        $table->addIndex(array('bar'), 'duplicate_idx');

        $this->assertCount(2, $table->getIndexes());
        $this->assertTrue($table->hasIndex('bar_idx'));
        $this->assertTrue($table->hasIndex('duplicate_idx'));
        $this->assertSame(array('bar'), $table->getIndex('bar_idx')->getColumns());
        $this->assertSame(array('bar'), $table->getIndex('duplicate_idx')->getColumns());
    }

    /**
     * @group DBAL-1063
     */
    public function testAllowsAddingFulfillingIndexesBasedOnColumns()
    {
        $table = new Table('foo');
        $table->addColumn('bar', 'integer');
        $table->addColumn('baz', 'string');
        $table->addIndex(array('bar'), 'bar_idx');
        $table->addIndex(array('bar', 'baz'), 'fulfilling_idx');

        $this->assertCount(2, $table->getIndexes());
        $this->assertTrue($table->hasIndex('bar_idx'));
        $this->assertTrue($table->hasIndex('fulfilling_idx'));
        $this->assertSame(array('bar'), $table->getIndex('bar_idx')->getColumns());
        $this->assertSame(array('bar', 'baz'), $table->getIndex('fulfilling_idx')->getColumns());
    }

    /**
     * @group DBAL-50
     * @group DBAL-1063
     */
    public function testPrimaryKeyOverrulingUniqueIndexDoesNotDropUniqueIndex()
    {
        $table = new Table("bar");
        $table->addColumn('baz', 'integer', array());
        $table->addUniqueIndex(array('baz'), 'idx_unique');

        $table->setPrimaryKey(array('baz'));

        $indexes = $table->getIndexes();
        $this->assertEquals(2, count($indexes), "Table should only contain both the primary key table index and the unique one, even though it was overruled.");

        $this->assertTrue($table->hasPrimaryKey());
        $this->assertTrue($table->hasIndex('idx_unique'));
    }

    public function testAddingFulfillingRegularIndexOverridesImplicitForeignKeyConstraintIndex()
    {
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('id', 'integer');

        $localTable = new Table('local');
        $localTable->addColumn('id', 'integer');
        $localTable->addForeignKeyConstraint($foreignTable, array('id'), array('id'));

        $this->assertCount(1, $localTable->getIndexes());

        $localTable->addIndex(array('id'), 'explicit_idx');

        $this->assertCount(1, $localTable->getIndexes());
        $this->assertTrue($localTable->hasIndex('explicit_idx'));
    }

    public function testAddingFulfillingUniqueIndexOverridesImplicitForeignKeyConstraintIndex()
    {
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('id', 'integer');

        $localTable = new Table('local');
        $localTable->addColumn('id', 'integer');
        $localTable->addForeignKeyConstraint($foreignTable, array('id'), array('id'));

        $this->assertCount(1, $localTable->getIndexes());

        $localTable->addUniqueIndex(array('id'), 'explicit_idx');

        $this->assertCount(1, $localTable->getIndexes());
        $this->assertTrue($localTable->hasIndex('explicit_idx'));
    }

    public function testAddingFulfillingPrimaryKeyOverridesImplicitForeignKeyConstraintIndex()
    {
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('id', 'integer');

        $localTable = new Table('local');
        $localTable->addColumn('id', 'integer');
        $localTable->addForeignKeyConstraint($foreignTable, array('id'), array('id'));

        $this->assertCount(1, $localTable->getIndexes());

        $localTable->setPrimaryKey(array('id'), 'explicit_idx');

        $this->assertCount(1, $localTable->getIndexes());
        $this->assertTrue($localTable->hasIndex('explicit_idx'));
    }

    public function testAddingFulfillingExplicitIndexOverridingImplicitForeignKeyConstraintIndexWithSameNameDoesNotThrowException()
    {
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('id', 'integer');

        $localTable = new Table('local');
        $localTable->addColumn('id', 'integer');
        $localTable->addForeignKeyConstraint($foreignTable, array('id'), array('id'));

        $this->assertCount(1, $localTable->getIndexes());
        $this->assertTrue($localTable->hasIndex('IDX_8BD688E8BF396750'));

        $implicitIndex = $localTable->getIndex('IDX_8BD688E8BF396750');

        $localTable->addIndex(array('id'), 'IDX_8BD688E8BF396750');

        $this->assertCount(1, $localTable->getIndexes());
        $this->assertTrue($localTable->hasIndex('IDX_8BD688E8BF396750'));
        $this->assertNotSame($implicitIndex, $localTable->getIndex('IDX_8BD688E8BF396750'));
    }

    /**
     * @group DBAL-64
     */
    public function testQuotedTableName()
    {
        $table = new Table("`bar`");

        $mysqlPlatform = new \Doctrine\DBAL\Platforms\MySqlPlatform();
        $sqlitePlatform = new \Doctrine\DBAL\Platforms\SqlitePlatform();

        $this->assertEquals('bar', $table->getName());
        $this->assertEquals('`bar`', $table->getQuotedName($mysqlPlatform));
        $this->assertEquals('"bar"', $table->getQuotedName($sqlitePlatform));
    }

    /**
     * @group DBAL-79
     */
    public function testTableHasPrimaryKey()
    {
        $table = new Table("test");

        $this->assertFalse($table->hasPrimaryKey());

        $table->addColumn("foo", "integer");
        $table->setPrimaryKey(array("foo"));

        $this->assertTrue($table->hasPrimaryKey());
    }

    /**
     * @group DBAL-91
     */
    public function testAddIndexWithQuotedColumns()
    {
        $table = new Table("test");
        $table->addColumn('"foo"', 'integer');
        $table->addColumn('bar', 'integer');
        $table->addIndex(array('"foo"', '"bar"'));
    }

    /**
     * @group DBAL-91
     */
    public function testAddForeignKeyWithQuotedColumnsAndTable()
    {
        $table = new Table("test");
        $table->addColumn('"foo"', 'integer');
        $table->addColumn('bar', 'integer');
        $table->addForeignKeyConstraint('"boing"', array('"foo"', '"bar"'), array("id"));
    }

    /**
     * @group DBAL-177
     */
    public function testQuoteSchemaPrefixed()
    {
        $table = new Table("`test`.`test`");
        $this->assertEquals("test.test", $table->getName());
        $this->assertEquals("`test`.`test`", $table->getQuotedName(new \Doctrine\DBAL\Platforms\MySqlPlatform));
    }

    /**
     * @group DBAL-204
     */
    public function testFullQualifiedTableName()
    {
        $table = new Table("`test`.`test`");
        $this->assertEquals('test.test', $table->getFullQualifiedName("test"));
        $this->assertEquals('test.test', $table->getFullQualifiedName("other"));

        $table = new Table("test");
        $this->assertEquals('test.test', $table->getFullQualifiedName("test"));
        $this->assertEquals('other.test', $table->getFullQualifiedName("other"));
    }

    /**
     * @group DBAL-224
     */
    public function testDropIndex()
    {
        $table = new Table("test");
        $table->addColumn('id', 'integer');
        $table->addIndex(array('id'), 'idx');

        $this->assertTrue($table->hasIndex('idx'));

        $table->dropIndex('idx');
        $this->assertFalse($table->hasIndex('idx'));
    }

    /**
     * @group DBAL-224
     */
    public function testDropPrimaryKey()
    {
        $table = new Table("test");
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $this->assertTrue($table->hasPrimaryKey());

        $table->dropPrimaryKey();
        $this->assertFalse($table->hasPrimaryKey());
    }

    /**
     * @group DBAL-234
     */
    public function testRenameIndex()
    {
        $table = new Table("test");
        $table->addColumn('id', 'integer');
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'integer');
        $table->addColumn('baz', 'integer');
        $table->setPrimaryKey(array('id'), 'pk');
        $table->addIndex(array('foo'), 'idx', array('flag'));
        $table->addUniqueIndex(array('bar', 'baz'), 'uniq');

        // Rename to custom name.
        $this->assertSame($table, $table->renameIndex('pk', 'pk_new'));
        $this->assertSame($table, $table->renameIndex('idx', 'idx_new'));
        $this->assertSame($table, $table->renameIndex('uniq', 'uniq_new'));

        $this->assertTrue($table->hasPrimaryKey());
        $this->assertTrue($table->hasIndex('pk_new'));
        $this->assertTrue($table->hasIndex('idx_new'));
        $this->assertTrue($table->hasIndex('uniq_new'));

        $this->assertFalse($table->hasIndex('pk'));
        $this->assertFalse($table->hasIndex('idx'));
        $this->assertFalse($table->hasIndex('uniq'));

        $this->assertEquals(new Index('pk_new', array('id'), true, true), $table->getPrimaryKey());
        $this->assertEquals(new Index('pk_new', array('id'), true, true), $table->getIndex('pk_new'));
        $this->assertEquals(
            new Index('idx_new', array('foo'), false, false, array('flag')),
            $table->getIndex('idx_new')
        );
        $this->assertEquals(new Index('uniq_new', array('bar', 'baz'), true), $table->getIndex('uniq_new'));

        // Rename to auto-generated name.
        $this->assertSame($table, $table->renameIndex('pk_new', null));
        $this->assertSame($table, $table->renameIndex('idx_new', null));
        $this->assertSame($table, $table->renameIndex('uniq_new', null));

        $this->assertTrue($table->hasPrimaryKey());
        $this->assertTrue($table->hasIndex('primary'));
        $this->assertTrue($table->hasIndex('IDX_D87F7E0C8C736521'));
        $this->assertTrue($table->hasIndex('UNIQ_D87F7E0C76FF8CAA78240498'));

        $this->assertFalse($table->hasIndex('pk_new'));
        $this->assertFalse($table->hasIndex('idx_new'));
        $this->assertFalse($table->hasIndex('uniq_new'));

        $this->assertEquals(new Index('primary', array('id'), true, true), $table->getPrimaryKey());
        $this->assertEquals(new Index('primary', array('id'), true, true), $table->getIndex('primary'));
        $this->assertEquals(
            new Index('IDX_D87F7E0C8C736521', array('foo'), false, false, array('flag')),
            $table->getIndex('IDX_D87F7E0C8C736521')
        );
        $this->assertEquals(
            new Index('UNIQ_D87F7E0C76FF8CAA78240498', array('bar', 'baz'), true),
            $table->getIndex('UNIQ_D87F7E0C76FF8CAA78240498')
        );

        // Rename to same name (changed case).
        $this->assertSame($table, $table->renameIndex('primary', 'PRIMARY'));
        $this->assertSame($table, $table->renameIndex('IDX_D87F7E0C8C736521', 'idx_D87F7E0C8C736521'));
        $this->assertSame($table, $table->renameIndex('UNIQ_D87F7E0C76FF8CAA78240498', 'uniq_D87F7E0C76FF8CAA78240498'));

        $this->assertTrue($table->hasPrimaryKey());
        $this->assertTrue($table->hasIndex('primary'));
        $this->assertTrue($table->hasIndex('IDX_D87F7E0C8C736521'));
        $this->assertTrue($table->hasIndex('UNIQ_D87F7E0C76FF8CAA78240498'));
    }

    /**
     * @group DBAL-234
     * @expectedException \Doctrine\DBAL\Schema\SchemaException
     */
    public function testThrowsExceptionOnRenamingNonExistingIndex()
    {
        $table = new Table("test");
        $table->addColumn('id', 'integer');
        $table->addIndex(array('id'), 'idx');

        $table->renameIndex('foo', 'bar');
    }

    /**
     * @group DBAL-234
     * @expectedException \Doctrine\DBAL\Schema\SchemaException
     */
    public function testThrowsExceptionOnRenamingToAlreadyExistingIndex()
    {
        $table = new Table("test");
        $table->addColumn('id', 'integer');
        $table->addColumn('foo', 'integer');
        $table->addIndex(array('id'), 'idx_id');
        $table->addIndex(array('foo'), 'idx_foo');

        $table->renameIndex('idx_id', 'idx_foo');
    }

    /**
     * @dataProvider getNormalizesAssetNames
     * @group DBAL-831
     */
    public function testNormalizesColumnNames($assetName)
    {
        $table = new Table('test');

        $table->addColumn($assetName, 'integer');
        $table->addIndex(array($assetName), $assetName);
        $table->addForeignKeyConstraint('test', array($assetName), array($assetName), array(), $assetName);

        $this->assertTrue($table->hasColumn($assetName));
        $this->assertTrue($table->hasColumn('foo'));
        $this->assertInstanceOf('Doctrine\DBAL\Schema\Column', $table->getColumn($assetName));
        $this->assertInstanceOf('Doctrine\DBAL\Schema\Column', $table->getColumn('foo'));

        $this->assertTrue($table->hasIndex($assetName));
        $this->assertTrue($table->hasIndex('foo'));
        $this->assertInstanceOf('Doctrine\DBAL\Schema\Index', $table->getIndex($assetName));
        $this->assertInstanceOf('Doctrine\DBAL\Schema\Index', $table->getIndex('foo'));

        $this->assertTrue($table->hasForeignKey($assetName));
        $this->assertTrue($table->hasForeignKey('foo'));
        $this->assertInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint', $table->getForeignKey($assetName));
        $this->assertInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint', $table->getForeignKey('foo'));

        $table->renameIndex($assetName, $assetName);
        $this->assertTrue($table->hasIndex($assetName));
        $this->assertTrue($table->hasIndex('foo'));

        $table->renameIndex($assetName, 'foo');
        $this->assertTrue($table->hasIndex($assetName));
        $this->assertTrue($table->hasIndex('foo'));

        $table->renameIndex('foo', $assetName);
        $this->assertTrue($table->hasIndex($assetName));
        $this->assertTrue($table->hasIndex('foo'));

        $table->renameIndex($assetName, 'bar');
        $this->assertFalse($table->hasIndex($assetName));
        $this->assertFalse($table->hasIndex('foo'));
        $this->assertTrue($table->hasIndex('bar'));

        $table->renameIndex('bar', $assetName);

        $table->dropColumn($assetName);
        $table->dropIndex($assetName);
        $table->removeForeignKey($assetName);

        $this->assertFalse($table->hasColumn($assetName));
        $this->assertFalse($table->hasColumn('foo'));
        $this->assertFalse($table->hasIndex($assetName));
        $this->assertFalse($table->hasIndex('foo'));
        $this->assertFalse($table->hasForeignKey($assetName));
        $this->assertFalse($table->hasForeignKey('foo'));
    }

    public function getNormalizesAssetNames()
    {
        return array(
            array('foo'),
            array('FOO'),
            array('`foo`'),
            array('`FOO`'),
            array('"foo"'),
            array('"FOO"'),
            array('"foo"'),
            array('"FOO"'),
        );
    }
}

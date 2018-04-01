<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use function array_shift;
use function current;

class TableTest extends \Doctrine\Tests\DbalTestCase
{
    public function testCreateWithInvalidTableName()
    {
        $this->expectException(DBALException::class);

        new \Doctrine\DBAL\Schema\Table('');
    }

    public function testGetName()
    {
        $table =  new Table("foo", array(), array(), array());
        self::assertEquals("foo", $table->getName());
    }

    public function testColumns()
    {
        $type = Type::getType('integer');
        $columns = array();
        $columns[] = new Column("foo", $type);
        $columns[] = new Column("bar", $type);
        $table = new Table("foo", $columns, array(), array());

        self::assertTrue($table->hasColumn("foo"));
        self::assertTrue($table->hasColumn("bar"));
        self::assertFalse($table->hasColumn("baz"));

        self::assertInstanceOf('Doctrine\DBAL\Schema\Column', $table->getColumn("foo"));
        self::assertInstanceOf('Doctrine\DBAL\Schema\Column', $table->getColumn("bar"));

        self::assertCount(2, $table->getColumns());
    }

    public function testColumnsCaseInsensitive()
    {
        $table = new Table("foo");
        $column = $table->addColumn('Foo', 'integer');

        self::assertTrue($table->hasColumn('Foo'));
        self::assertTrue($table->hasColumn('foo'));
        self::assertTrue($table->hasColumn('FOO'));

        self::assertSame($column, $table->getColumn('Foo'));
        self::assertSame($column, $table->getColumn('foo'));
        self::assertSame($column, $table->getColumn('FOO'));
    }

    public function testCreateColumn()
    {
        $type = Type::getType('integer');

        $table = new Table("foo");

        self::assertFalse($table->hasColumn("bar"));
        $table->addColumn("bar", 'integer');
        self::assertTrue($table->hasColumn("bar"));
        self::assertSame($type, $table->getColumn("bar")->getType());
    }

    public function testDropColumn()
    {
        $type = Type::getType('integer');
        $columns = array();
        $columns[] = new Column("foo", $type);
        $columns[] = new Column("bar", $type);
        $table = new Table("foo", $columns, array(), array());

        self::assertTrue($table->hasColumn("foo"));
        self::assertTrue($table->hasColumn("bar"));

        $table->dropColumn("foo")->dropColumn("bar");

        self::assertFalse($table->hasColumn("foo"));
        self::assertFalse($table->hasColumn("bar"));
    }

    public function testGetUnknownColumnThrowsException()
    {
        $this->expectException("Doctrine\DBAL\Schema\SchemaException");

        $table = new Table("foo", array(), array(), array());
        $table->getColumn('unknown');
    }

    public function testAddColumnTwiceThrowsException()
    {
        $this->expectException("Doctrine\DBAL\Schema\SchemaException");

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

        self::assertTrue($table->hasIndex("foo_foo_bar_idx"));
        self::assertTrue($table->hasIndex("foo_bar_baz_uniq"));
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

        self::assertTrue($table->hasIndex('foo_idx'));
        self::assertTrue($table->hasIndex('Foo_Idx'));
        self::assertTrue($table->hasIndex('FOO_IDX'));
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

        self::assertTrue($table->hasIndex("the_primary"));
        self::assertTrue($table->hasIndex("bar_idx"));
        self::assertFalse($table->hasIndex("some_idx"));

        self::assertInstanceOf('Doctrine\DBAL\Schema\Index', $table->getPrimaryKey());
        self::assertInstanceOf('Doctrine\DBAL\Schema\Index', $table->getIndex('the_primary'));
        self::assertInstanceOf('Doctrine\DBAL\Schema\Index', $table->getIndex('bar_idx'));
    }

    public function testGetUnknownIndexThrowsException()
    {
        $this->expectException("Doctrine\DBAL\Schema\SchemaException");

        $table = new Table("foo", array(), array(), array());
        $table->getIndex("unknownIndex");
    }

    public function testAddTwoPrimaryThrowsException()
    {
        $this->expectException("Doctrine\DBAL\Schema\SchemaException");

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
        $this->expectException("Doctrine\DBAL\Schema\SchemaException");

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

        self::assertCount(1, $constraints);
        self::assertSame($constraint, array_shift($constraints));
    }

    public function testOptions()
    {
        $table = new Table("foo", array(), array(), array(), false, array("foo" => "bar"));

        self::assertTrue($table->hasOption("foo"));
        self::assertEquals("bar", $table->getOption("foo"));
    }

    public function testBuilderSetPrimaryKey()
    {
        $table = new Table("foo");

        $table->addColumn("bar", 'integer');
        $table->setPrimaryKey(array("bar"));

        self::assertTrue($table->hasIndex("primary"));
        self::assertInstanceOf('Doctrine\DBAL\Schema\Index', $table->getPrimaryKey());
        self::assertTrue($table->getIndex("primary")->isUnique());
        self::assertTrue($table->getIndex("primary")->isPrimary());
    }

    public function testBuilderAddUniqueIndex()
    {
        $table = new Table("foo");

        $table->addColumn("bar", 'integer');
        $table->addUniqueIndex(array("bar"), "my_idx");

        self::assertTrue($table->hasIndex("my_idx"));
        self::assertTrue($table->getIndex("my_idx")->isUnique());
        self::assertFalse($table->getIndex("my_idx")->isPrimary());
    }

    public function testBuilderAddIndex()
    {
        $table = new Table("foo");

        $table->addColumn("bar", 'integer');
        $table->addIndex(array("bar"), "my_idx");

        self::assertTrue($table->hasIndex("my_idx"));
        self::assertFalse($table->getIndex("my_idx")->isUnique());
        self::assertFalse($table->getIndex("my_idx")->isPrimary());
    }

    public function testBuilderAddIndexWithInvalidNameThrowsException()
    {
        $this->expectException("Doctrine\DBAL\Schema\SchemaException");

        $table = new Table("foo");
        $table->addColumn("bar",'integer');
        $table->addIndex(array("bar"), "invalid name %&/");
    }

    public function testBuilderAddIndexWithUnknownColumnThrowsException()
    {
        $this->expectException("Doctrine\DBAL\Schema\SchemaException");

        $table = new Table("foo");
        $table->addIndex(array("bar"), "invalidName");
    }

    public function testBuilderOptions()
    {
        $table = new Table("foo");
        $table->addOption("foo", "bar");
        self::assertTrue($table->hasOption("foo"));
        self::assertEquals("bar", $table->getOption("foo"));
    }

    public function testAddForeignKeyConstraint_UnknownLocalColumn_ThrowsException()
    {
        $this->expectException("Doctrine\DBAL\Schema\SchemaException");

        $table = new Table("foo");
        $table->addColumn("id", 'integer');

        $foreignTable = new Table("bar");
        $foreignTable->addColumn("id", 'integer');

        $table->addForeignKeyConstraint($foreignTable, array("foo"), array("id"));
    }

    public function testAddForeignKeyConstraint_UnknownForeignColumn_ThrowsException()
    {
        $this->expectException("Doctrine\DBAL\Schema\SchemaException");

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
        self::assertCount(1, $constraints);
        $constraint = current($constraints);

        self::assertInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint', $constraint);

        self::assertTrue($constraint->hasOption("foo"));
        self::assertEquals("bar", $constraint->getOption("foo"));
    }

    public function testAddIndexWithCaseSensitiveColumnProblem()
    {
        $table = new Table("foo");
        $table->addColumn("id", 'integer');

        $table->addIndex(array("ID"), "my_idx");

        self::assertTrue($table->hasIndex('my_idx'));
        self::assertEquals(array("ID"), $table->getIndex("my_idx")->getColumns());
        self::assertTrue($table->getIndex('my_idx')->spansColumns(array('id')));
    }

    public function testAddPrimaryKey_ColumnsAreExplicitlySetToNotNull()
    {
        $table = new Table("foo");
        $column = $table->addColumn("id", 'integer', array('notnull' => false));

        self::assertFalse($column->getNotnull());

        $table->setPrimaryKey(array('id'));

        self::assertTrue($column->getNotnull());
    }

    /**
     * @group DDC-133
     */
    public function testAllowImplicitSchemaTableInAutogeneratedIndexNames()
    {
        $table = new Table("foo.bar");
        $table->addColumn('baz', 'integer', array());
        $table->addIndex(array('baz'));

        self::assertCount(1, $table->getIndexes());
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
        self::assertCount(1, $indexes);
        $index = current($indexes);

        self::assertTrue($table->hasIndex($index->getName()));
        self::assertEquals(array('id'), $index->getColumns());
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

        self::assertCount(1, $table->getIndexes());
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertSame(array('bar'), $table->getIndex('bar_idx')->getColumns());
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

        self::assertCount(3, $table->getIndexes());
        self::assertTrue($table->hasIndex('composite_idx'));
        self::assertTrue($table->hasIndex('full_idx'));
        self::assertTrue($table->hasIndex('idx_8c73652176ff8caa78240498'));
        self::assertSame(array('baz', 'bar'), $table->getIndex('composite_idx')->getColumns());
        self::assertSame(array('bar', 'baz', 'bloo'), $table->getIndex('full_idx')->getColumns());
        self::assertSame(array('bar', 'baz'), $table->getIndex('idx_8c73652176ff8caa78240498')->getColumns());
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
        self::assertCount(1, $indexes);
        $index = current($indexes);

        $table->addUniqueIndex(array('baz'));
        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex($index->getName()));
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

        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertTrue($table->hasIndex('duplicate_idx'));
        self::assertSame(array('bar'), $table->getIndex('bar_idx')->getColumns());
        self::assertSame(array('bar'), $table->getIndex('duplicate_idx')->getColumns());
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

        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertTrue($table->hasIndex('fulfilling_idx'));
        self::assertSame(array('bar'), $table->getIndex('bar_idx')->getColumns());
        self::assertSame(array('bar', 'baz'), $table->getIndex('fulfilling_idx')->getColumns());
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
        self::assertCount(2, $indexes, "Table should only contain both the primary key table index and the unique one, even though it was overruled.");

        self::assertTrue($table->hasPrimaryKey());
        self::assertTrue($table->hasIndex('idx_unique'));
    }

    public function testAddingFulfillingRegularIndexOverridesImplicitForeignKeyConstraintIndex()
    {
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('id', 'integer');

        $localTable = new Table('local');
        $localTable->addColumn('id', 'integer');
        $localTable->addForeignKeyConstraint($foreignTable, array('id'), array('id'));

        self::assertCount(1, $localTable->getIndexes());

        $localTable->addIndex(array('id'), 'explicit_idx');

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('explicit_idx'));
    }

    public function testAddingFulfillingUniqueIndexOverridesImplicitForeignKeyConstraintIndex()
    {
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('id', 'integer');

        $localTable = new Table('local');
        $localTable->addColumn('id', 'integer');
        $localTable->addForeignKeyConstraint($foreignTable, array('id'), array('id'));

        self::assertCount(1, $localTable->getIndexes());

        $localTable->addUniqueIndex(array('id'), 'explicit_idx');

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('explicit_idx'));
    }

    public function testAddingFulfillingPrimaryKeyOverridesImplicitForeignKeyConstraintIndex()
    {
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('id', 'integer');

        $localTable = new Table('local');
        $localTable->addColumn('id', 'integer');
        $localTable->addForeignKeyConstraint($foreignTable, array('id'), array('id'));

        self::assertCount(1, $localTable->getIndexes());

        $localTable->setPrimaryKey(array('id'), 'explicit_idx');

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('explicit_idx'));
    }

    public function testAddingFulfillingExplicitIndexOverridingImplicitForeignKeyConstraintIndexWithSameNameDoesNotThrowException()
    {
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('id', 'integer');

        $localTable = new Table('local');
        $localTable->addColumn('id', 'integer');
        $localTable->addForeignKeyConstraint($foreignTable, array('id'), array('id'));

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('IDX_8BD688E8BF396750'));

        $implicitIndex = $localTable->getIndex('IDX_8BD688E8BF396750');

        $localTable->addIndex(array('id'), 'IDX_8BD688E8BF396750');

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('IDX_8BD688E8BF396750'));
        self::assertNotSame($implicitIndex, $localTable->getIndex('IDX_8BD688E8BF396750'));
    }

    /**
     * @group DBAL-64
     */
    public function testQuotedTableName()
    {
        $table = new Table("`bar`");

        $mysqlPlatform = new \Doctrine\DBAL\Platforms\MySqlPlatform();
        $sqlitePlatform = new \Doctrine\DBAL\Platforms\SqlitePlatform();

        self::assertEquals('bar', $table->getName());
        self::assertEquals('`bar`', $table->getQuotedName($mysqlPlatform));
        self::assertEquals('"bar"', $table->getQuotedName($sqlitePlatform));
    }

    /**
     * @group DBAL-79
     */
    public function testTableHasPrimaryKey()
    {
        $table = new Table("test");

        self::assertFalse($table->hasPrimaryKey());

        $table->addColumn("foo", "integer");
        $table->setPrimaryKey(array("foo"));

        self::assertTrue($table->hasPrimaryKey());
    }

    /**
     * @group DBAL-91
     */
    public function testAddIndexWithQuotedColumns()
    {
        $table = new Table("test");
        $table->addColumn('"foo"', 'integer');
        $table->addColumn('bar', 'integer');
        $table->addIndex(['"foo"', '"bar"']);

        self::assertTrue($table->columnsAreIndexed(['"foo"', '"bar"']));
    }

    /**
     * @group DBAL-91
     */
    public function testAddForeignKeyWithQuotedColumnsAndTable()
    {
        $table = new Table("test");
        $table->addColumn('"foo"', 'integer');
        $table->addColumn('bar', 'integer');
        $table->addForeignKeyConstraint('"boing"', ['"foo"', '"bar"'], ["id"]);

        self::assertCount(1, $table->getForeignKeys());
    }

    /**
     * @group DBAL-177
     */
    public function testQuoteSchemaPrefixed()
    {
        $table = new Table("`test`.`test`");
        self::assertEquals("test.test", $table->getName());
        self::assertEquals("`test`.`test`", $table->getQuotedName(new \Doctrine\DBAL\Platforms\MySqlPlatform));
    }

    /**
     * @group DBAL-204
     */
    public function testFullQualifiedTableName()
    {
        $table = new Table("`test`.`test`");
        self::assertEquals('test.test', $table->getFullQualifiedName("test"));
        self::assertEquals('test.test', $table->getFullQualifiedName("other"));

        $table = new Table("test");
        self::assertEquals('test.test', $table->getFullQualifiedName("test"));
        self::assertEquals('other.test', $table->getFullQualifiedName("other"));
    }

    /**
     * @group DBAL-224
     */
    public function testDropIndex()
    {
        $table = new Table("test");
        $table->addColumn('id', 'integer');
        $table->addIndex(array('id'), 'idx');

        self::assertTrue($table->hasIndex('idx'));

        $table->dropIndex('idx');
        self::assertFalse($table->hasIndex('idx'));
    }

    /**
     * @group DBAL-224
     */
    public function testDropPrimaryKey()
    {
        $table = new Table("test");
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        self::assertTrue($table->hasPrimaryKey());

        $table->dropPrimaryKey();
        self::assertFalse($table->hasPrimaryKey());
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
        self::assertSame($table, $table->renameIndex('pk', 'pk_new'));
        self::assertSame($table, $table->renameIndex('idx', 'idx_new'));
        self::assertSame($table, $table->renameIndex('uniq', 'uniq_new'));

        self::assertTrue($table->hasPrimaryKey());
        self::assertTrue($table->hasIndex('pk_new'));
        self::assertTrue($table->hasIndex('idx_new'));
        self::assertTrue($table->hasIndex('uniq_new'));

        self::assertFalse($table->hasIndex('pk'));
        self::assertFalse($table->hasIndex('idx'));
        self::assertFalse($table->hasIndex('uniq'));

        self::assertEquals(new Index('pk_new', array('id'), true, true), $table->getPrimaryKey());
        self::assertEquals(new Index('pk_new', array('id'), true, true), $table->getIndex('pk_new'));
        self::assertEquals(
            new Index('idx_new', array('foo'), false, false, array('flag')),
            $table->getIndex('idx_new')
        );
        self::assertEquals(new Index('uniq_new', array('bar', 'baz'), true), $table->getIndex('uniq_new'));

        // Rename to auto-generated name.
        self::assertSame($table, $table->renameIndex('pk_new', null));
        self::assertSame($table, $table->renameIndex('idx_new', null));
        self::assertSame($table, $table->renameIndex('uniq_new', null));

        self::assertTrue($table->hasPrimaryKey());
        self::assertTrue($table->hasIndex('primary'));
        self::assertTrue($table->hasIndex('IDX_D87F7E0C8C736521'));
        self::assertTrue($table->hasIndex('UNIQ_D87F7E0C76FF8CAA78240498'));

        self::assertFalse($table->hasIndex('pk_new'));
        self::assertFalse($table->hasIndex('idx_new'));
        self::assertFalse($table->hasIndex('uniq_new'));

        self::assertEquals(new Index('primary', array('id'), true, true), $table->getPrimaryKey());
        self::assertEquals(new Index('primary', array('id'), true, true), $table->getIndex('primary'));
        self::assertEquals(
            new Index('IDX_D87F7E0C8C736521', array('foo'), false, false, array('flag')),
            $table->getIndex('IDX_D87F7E0C8C736521')
        );
        self::assertEquals(
            new Index('UNIQ_D87F7E0C76FF8CAA78240498', array('bar', 'baz'), true),
            $table->getIndex('UNIQ_D87F7E0C76FF8CAA78240498')
        );

        // Rename to same name (changed case).
        self::assertSame($table, $table->renameIndex('primary', 'PRIMARY'));
        self::assertSame($table, $table->renameIndex('IDX_D87F7E0C8C736521', 'idx_D87F7E0C8C736521'));
        self::assertSame($table, $table->renameIndex('UNIQ_D87F7E0C76FF8CAA78240498', 'uniq_D87F7E0C76FF8CAA78240498'));

        self::assertTrue($table->hasPrimaryKey());
        self::assertTrue($table->hasIndex('primary'));
        self::assertTrue($table->hasIndex('IDX_D87F7E0C8C736521'));
        self::assertTrue($table->hasIndex('UNIQ_D87F7E0C76FF8CAA78240498'));
    }

    /**
     * @group DBAL-2508
     */
    public function testKeepsIndexOptionsOnRenamingRegularIndex()
    {
        $table = new Table('foo');
        $table->addColumn('id', 'integer');
        $table->addIndex(array('id'), 'idx_bar', array(), array('where' => '1 = 1'));

        $table->renameIndex('idx_bar', 'idx_baz');

        self::assertSame(array('where' => '1 = 1'), $table->getIndex('idx_baz')->getOptions());
    }

    /**
     * @group DBAL-2508
     */
    public function testKeepsIndexOptionsOnRenamingUniqueIndex()
    {
        $table = new Table('foo');
        $table->addColumn('id', 'integer');
        $table->addUniqueIndex(array('id'), 'idx_bar', array('where' => '1 = 1'));

        $table->renameIndex('idx_bar', 'idx_baz');

        self::assertSame(array('where' => '1 = 1'), $table->getIndex('idx_baz')->getOptions());
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

        self::assertTrue($table->hasColumn($assetName));
        self::assertTrue($table->hasColumn('foo'));
        self::assertInstanceOf('Doctrine\DBAL\Schema\Column', $table->getColumn($assetName));
        self::assertInstanceOf('Doctrine\DBAL\Schema\Column', $table->getColumn('foo'));

        self::assertTrue($table->hasIndex($assetName));
        self::assertTrue($table->hasIndex('foo'));
        self::assertInstanceOf('Doctrine\DBAL\Schema\Index', $table->getIndex($assetName));
        self::assertInstanceOf('Doctrine\DBAL\Schema\Index', $table->getIndex('foo'));

        self::assertTrue($table->hasForeignKey($assetName));
        self::assertTrue($table->hasForeignKey('foo'));
        self::assertInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint', $table->getForeignKey($assetName));
        self::assertInstanceOf('Doctrine\DBAL\Schema\ForeignKeyConstraint', $table->getForeignKey('foo'));

        $table->renameIndex($assetName, $assetName);
        self::assertTrue($table->hasIndex($assetName));
        self::assertTrue($table->hasIndex('foo'));

        $table->renameIndex($assetName, 'foo');
        self::assertTrue($table->hasIndex($assetName));
        self::assertTrue($table->hasIndex('foo'));

        $table->renameIndex('foo', $assetName);
        self::assertTrue($table->hasIndex($assetName));
        self::assertTrue($table->hasIndex('foo'));

        $table->renameIndex($assetName, 'bar');
        self::assertFalse($table->hasIndex($assetName));
        self::assertFalse($table->hasIndex('foo'));
        self::assertTrue($table->hasIndex('bar'));

        $table->renameIndex('bar', $assetName);

        $table->dropColumn($assetName);
        $table->dropIndex($assetName);
        $table->removeForeignKey($assetName);

        self::assertFalse($table->hasColumn($assetName));
        self::assertFalse($table->hasColumn('foo'));
        self::assertFalse($table->hasIndex($assetName));
        self::assertFalse($table->hasIndex('foo'));
        self::assertFalse($table->hasForeignKey($assetName));
        self::assertFalse($table->hasForeignKey('foo'));
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

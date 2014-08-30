<?php

namespace Doctrine\Tests\DBAL\Schema;

require_once __DIR__ . '/../../TestInit.php';

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;

class SchemaTest extends \PHPUnit_Framework_TestCase
{
    public function testAddTable()
    {
        $tableName = "public.foo";
        $table = new Table($tableName);

        $schema = new Schema(array($table));

        $this->assertTrue($schema->hasTable($tableName));

        $tables = $schema->getTables();
        $this->assertTrue( isset($tables[$tableName]) );
        $this->assertSame($table, $tables[$tableName]);
        $this->assertSame($table, $schema->getTable($tableName));
        $this->assertTrue($schema->hasTable($tableName));
    }

    public function testTableMatchingCaseInsenstive()
    {
        $table = new Table("Foo");

        $schema = new Schema(array($table));
        $this->assertTrue($schema->hasTable("foo"));
        $this->assertTrue($schema->hasTable("FOO"));

        $this->assertSame($table, $schema->getTable('FOO'));
        $this->assertSame($table, $schema->getTable('foo'));
        $this->assertSame($table, $schema->getTable('Foo'));
    }

    public function testGetUnknownTableThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $schema = new Schema();
        $schema->getTable("unknown");
    }

    public function testCreateTableTwiceThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $tableName = "foo";
        $table = new Table($tableName);
        $tables = array($table, $table);

        $schema = new Schema($tables);
    }

    public function testRenameTable()
    {
        $tableName = "foo";
        $table = new Table($tableName);
        $schema = new Schema(array($table));

        $this->assertTrue($schema->hasTable("foo"));
        $schema->renameTable("foo", "bar");
        $this->assertFalse($schema->hasTable("foo"));
        $this->assertTrue($schema->hasTable("bar"));
        $this->assertSame($table, $schema->getTable("bar"));
    }

    public function testDropTable()
    {
        $tableName = "foo";
        $table = new Table($tableName);
        $schema = new Schema(array($table));

        $this->assertTrue($schema->hasTable("foo"));

        $schema->dropTable("foo");

        $this->assertFalse($schema->hasTable("foo"));
    }

    public function testCreateTable()
    {
        $schema = new Schema();

        $this->assertFalse($schema->hasTable("foo"));

        $table = $schema->createTable("foo");

        $this->assertInstanceOf('Doctrine\DBAL\Schema\Table', $table);
        $this->assertEquals("foo", $table->getName());
        $this->assertTrue($schema->hasTable("foo"));
    }

    public function testAddSequences()
    {
        $sequence = new Sequence("a_seq", 1, 1);

        $schema = new Schema(array(), array($sequence));

        $this->assertTrue($schema->hasSequence("a_seq"));
        $this->assertInstanceOf('Doctrine\DBAL\Schema\Sequence', $schema->getSequence("a_seq"));

        $sequences = $schema->getSequences();
        $this->assertArrayHasKey('public.a_seq', $sequences);
    }

    public function testSequenceAccessCaseInsensitive()
    {
        $sequence = new Sequence("a_Seq");

        $schema = new Schema(array(), array($sequence));
        $this->assertTrue($schema->hasSequence('a_seq'));
        $this->assertTrue($schema->hasSequence('a_Seq'));
        $this->assertTrue($schema->hasSequence('A_SEQ'));

        $this->assertEquals($sequence, $schema->getSequence('a_seq'));
        $this->assertEquals($sequence, $schema->getSequence('a_Seq'));
        $this->assertEquals($sequence, $schema->getSequence('A_SEQ'));
    }

    public function testGetUnknownSequenceThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $schema = new Schema();
        $schema->getSequence("unknown");
    }

    public function testCreateSequence()
    {
        $schema = new Schema();
        $sequence = $schema->createSequence('a_seq', 10, 20);

        $this->assertEquals('a_seq', $sequence->getName());
        $this->assertEquals(10, $sequence->getAllocationSize());
        $this->assertEquals(20, $sequence->getInitialValue());

        $this->assertTrue($schema->hasSequence("a_seq"));
        $this->assertInstanceOf('Doctrine\DBAL\Schema\Sequence', $schema->getSequence("a_seq"));

        $sequences = $schema->getSequences();
        $this->assertArrayHasKey('public.a_seq', $sequences);
    }

    public function testDropSequence()
    {
        $sequence = new Sequence("a_seq", 1, 1);

        $schema = new Schema(array(), array($sequence));

        $schema->dropSequence("a_seq");
        $this->assertFalse($schema->hasSequence("a_seq"));
    }

    public function testAddSequenceTwiceThrowsException()
    {
        $this->setExpectedException("Doctrine\DBAL\Schema\SchemaException");

        $sequence = new Sequence("a_seq", 1, 1);

        $schema = new Schema(array(), array($sequence, $sequence));
    }

    public function testConfigMaxIdentifierLength()
    {
        $schemaConfig = new \Doctrine\DBAL\Schema\SchemaConfig();
        $schemaConfig->setMaxIdentifierLength(5);

        $schema = new Schema(array(), array(), $schemaConfig);
        $table = $schema->createTable("smalltable");
        $table->addColumn('long_id', 'integer');
        $table->addIndex(array('long_id'));

        $index = current($table->getIndexes());
        $this->assertEquals(5, strlen($index->getName()));
    }

    public function testDeepClone()
    {
        $schema = new Schema();
        $sequence = $schema->createSequence('baz');

        $tableA = $schema->createTable('foo');
        $tableA->addColumn('id', 'integer');

        $tableB = $schema->createTable('bar');
        $tableB->addColumn('id', 'integer');
        $tableB->addColumn('foo_id', 'integer');
        $tableB->addForeignKeyConstraint($tableA, array('foo_id'), array('id'));

        $schemaNew = clone $schema;

        $this->assertNotSame($sequence, $schemaNew->getSequence('baz'));

        $this->assertNotSame($tableA, $schemaNew->getTable('foo'));
        $this->assertNotSame($tableA->getColumn('id'), $schemaNew->getTable('foo')->getColumn('id'));

        $this->assertNotSame($tableB, $schemaNew->getTable('bar'));
        $this->assertNotSame($tableB->getColumn('id'), $schemaNew->getTable('bar')->getColumn('id'));

        $fk = $schemaNew->getTable('bar')->getForeignKeys();
        $fk = current($fk);
        $this->assertSame($schemaNew->getTable('bar'), $this->readAttribute($fk, '_localTable'));
    }

    /**
     * @group DBAL-219
     */
    public function testHasTableForQuotedAsset()
    {
        $schema = new Schema();

        $tableA = $schema->createTable('foo');
        $tableA->addColumn('id', 'integer');

        $this->assertTrue($schema->hasTable('`foo`'));
    }

    /**
     * @group DBAL-669
     */
    public function testHasNamespace()
    {
        $schema = new Schema();

        $this->assertFalse($schema->hasNamespace('foo'));

        $schema->createTable('foo');

        $this->assertFalse($schema->hasNamespace('foo'));

        $schema->createTable('bar.baz');

        $this->assertFalse($schema->hasNamespace('baz'));
        $this->assertTrue($schema->hasNamespace('bar'));
        $this->assertFalse($schema->hasNamespace('tab'));

        $schema->createTable('tab.taz');

        $this->assertTrue($schema->hasNamespace('tab'));
    }

    /**
     * @group DBAL-669
     */
    public function testCreatesNamespace()
    {
        $schema = new Schema();

        $this->assertFalse($schema->hasNamespace('foo'));

        $schema->createNamespace('foo');

        $this->assertTrue($schema->hasNamespace('foo'));
        $this->assertTrue($schema->hasNamespace('FOO'));
        $this->assertTrue($schema->hasNamespace('`foo`'));
        $this->assertTrue($schema->hasNamespace('`FOO`'));

        $schema->createNamespace('`bar`');

        $this->assertTrue($schema->hasNamespace('bar'));
        $this->assertTrue($schema->hasNamespace('BAR'));
        $this->assertTrue($schema->hasNamespace('`bar`'));
        $this->assertTrue($schema->hasNamespace('`BAR`'));

        $this->assertSame(array('foo' => 'foo', 'bar' => '`bar`'), $schema->getNamespaces());
    }

    /**
     * @group DBAL-669
     *
     * @expectedException \Doctrine\DBAL\Schema\SchemaException
     */
    public function testThrowsExceptionOnCreatingNamespaceTwice()
    {
        $schema = new Schema();

        $schema->createNamespace('foo');
        $schema->createNamespace('foo');
    }

    /**
     * @group DBAL-669
     */
    public function testCreatesNamespaceThroughAddingTableImplicitly()
    {
        $schema = new Schema();

        $this->assertFalse($schema->hasNamespace('foo'));

        $schema->createTable('baz');

        $this->assertFalse($schema->hasNamespace('foo'));
        $this->assertFalse($schema->hasNamespace('baz'));

        $schema->createTable('foo.bar');

        $this->assertTrue($schema->hasNamespace('foo'));
        $this->assertFalse($schema->hasNamespace('bar'));

        $schema->createTable('`baz`.bloo');

        $this->assertTrue($schema->hasNamespace('baz'));
        $this->assertFalse($schema->hasNamespace('bloo'));

        $schema->createTable('`baz`.moo');

        $this->assertTrue($schema->hasNamespace('baz'));
        $this->assertFalse($schema->hasNamespace('moo'));
    }

    /**
     * @group DBAL-669
     */
    public function testCreatesNamespaceThroughAddingSequenceImplicitly()
    {
        $schema = new Schema();

        $this->assertFalse($schema->hasNamespace('foo'));

        $schema->createSequence('baz');

        $this->assertFalse($schema->hasNamespace('foo'));
        $this->assertFalse($schema->hasNamespace('baz'));

        $schema->createSequence('foo.bar');

        $this->assertTrue($schema->hasNamespace('foo'));
        $this->assertFalse($schema->hasNamespace('bar'));

        $schema->createSequence('`baz`.bloo');

        $this->assertTrue($schema->hasNamespace('baz'));
        $this->assertFalse($schema->hasNamespace('bloo'));

        $schema->createSequence('`baz`.moo');

        $this->assertTrue($schema->hasNamespace('baz'));
        $this->assertFalse($schema->hasNamespace('moo'));
    }

    /**
     * @group DBAL-669
     */
    public function testVisitsVisitor()
    {
        $schema = new Schema();
        $visitor = $this->getMock('Doctrine\DBAL\Schema\Visitor\Visitor');

        $schema->createNamespace('foo');
        $schema->createNamespace('bar');

        $schema->createTable('baz');
        $schema->createTable('bla.bloo');

        $schema->createSequence('moo');
        $schema->createSequence('war');

        $visitor->expects($this->once())
            ->method('acceptSchema')
            ->with($schema);

        $visitor->expects($this->never())
            ->method('acceptNamespace');

        $visitor->expects($this->at(1))
            ->method('acceptTable')
            ->with($schema->getTable('baz'));

        $visitor->expects($this->at(2))
            ->method('acceptTable')
            ->with($schema->getTable('bla.bloo'));

        $visitor->expects($this->exactly(2))
            ->method('acceptTable');

        $visitor->expects($this->at(3))
            ->method('acceptSequence')
            ->with($schema->getSequence('moo'));

        $visitor->expects($this->at(4))
            ->method('acceptSequence')
            ->with($schema->getSequence('war'));

        $visitor->expects($this->exactly(2))
            ->method('acceptSequence');

        $this->assertNull($schema->visit($visitor));
    }

    /**
     * @group DBAL-669
     */
    public function testVisitsNamespaceVisitor()
    {
        $schema = new Schema();
        $visitor = $this->getMock('Doctrine\DBAL\Schema\Visitor\AbstractVisitor');

        $schema->createNamespace('foo');
        $schema->createNamespace('bar');

        $schema->createTable('baz');
        $schema->createTable('bla.bloo');

        $schema->createSequence('moo');
        $schema->createSequence('war');

        $visitor->expects($this->once())
            ->method('acceptSchema')
            ->with($schema);

        $visitor->expects($this->at(1))
            ->method('acceptNamespace')
            ->with('foo');

        $visitor->expects($this->at(2))
            ->method('acceptNamespace')
            ->with('bar');

        $visitor->expects($this->at(3))
            ->method('acceptNamespace')
            ->with('bla');

        $visitor->expects($this->exactly(3))
            ->method('acceptNamespace');

        $visitor->expects($this->at(4))
            ->method('acceptTable')
            ->with($schema->getTable('baz'));

        $visitor->expects($this->at(5))
            ->method('acceptTable')
            ->with($schema->getTable('bla.bloo'));

        $visitor->expects($this->exactly(2))
            ->method('acceptTable');

        $visitor->expects($this->at(6))
            ->method('acceptSequence')
            ->with($schema->getSequence('moo'));

        $visitor->expects($this->at(7))
            ->method('acceptSequence')
            ->with($schema->getSequence('war'));

        $visitor->expects($this->exactly(2))
            ->method('acceptSequence');

        $this->assertNull($schema->visit($visitor));
    }
}

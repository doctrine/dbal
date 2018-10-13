<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Visitor\AbstractVisitor;
use Doctrine\DBAL\Schema\Visitor\Visitor;
use PHPUnit\Framework\TestCase;
use function current;
use function strlen;

class SchemaTest extends TestCase
{
    public function testAddTable()
    {
        $tableName = 'public.foo';
        $table     = new Table($tableName);

        $schema = new Schema([$table]);

        self::assertTrue($schema->hasTable($tableName));

        $tables = $schema->getTables();
        self::assertArrayHasKey($tableName, $tables);
        self::assertSame($table, $tables[$tableName]);
        self::assertSame($table, $schema->getTable($tableName));
        self::assertTrue($schema->hasTable($tableName));
    }

    public function testTableMatchingCaseInsensitive()
    {
        $table = new Table('Foo');

        $schema = new Schema([$table]);
        self::assertTrue($schema->hasTable('foo'));
        self::assertTrue($schema->hasTable('FOO'));

        self::assertSame($table, $schema->getTable('FOO'));
        self::assertSame($table, $schema->getTable('foo'));
        self::assertSame($table, $schema->getTable('Foo'));
    }

    public function testGetUnknownTableThrowsException()
    {
        $this->expectException(SchemaException::class);

        $schema = new Schema();
        $schema->getTable('unknown');
    }

    public function testCreateTableTwiceThrowsException()
    {
        $this->expectException(SchemaException::class);

        $tableName = 'foo';
        $table     = new Table($tableName);
        $tables    = [$table, $table];

        $schema = new Schema($tables);
    }

    public function testRenameTable()
    {
        $tableName = 'foo';
        $table     = new Table($tableName);
        $schema    = new Schema([$table]);

        self::assertTrue($schema->hasTable('foo'));
        $schema->renameTable('foo', 'bar');
        self::assertFalse($schema->hasTable('foo'));
        self::assertTrue($schema->hasTable('bar'));
        self::assertSame($table, $schema->getTable('bar'));
    }

    public function testDropTable()
    {
        $tableName = 'foo';
        $table     = new Table($tableName);
        $schema    = new Schema([$table]);

        self::assertTrue($schema->hasTable('foo'));

        $schema->dropTable('foo');

        self::assertFalse($schema->hasTable('foo'));
    }

    public function testCreateTable()
    {
        $schema = new Schema();

        self::assertFalse($schema->hasTable('foo'));

        $table = $schema->createTable('foo');

        self::assertInstanceOf(Table::class, $table);
        self::assertEquals('foo', $table->getName());
        self::assertTrue($schema->hasTable('foo'));
    }

    public function testAddSequences()
    {
        $sequence = new Sequence('a_seq', 1, 1);

        $schema = new Schema([], [$sequence]);

        self::assertTrue($schema->hasSequence('a_seq'));
        self::assertInstanceOf(Sequence::class, $schema->getSequence('a_seq'));

        $sequences = $schema->getSequences();
        self::assertArrayHasKey('public.a_seq', $sequences);
    }

    public function testSequenceAccessCaseInsensitive()
    {
        $sequence = new Sequence('a_Seq');

        $schema = new Schema([], [$sequence]);
        self::assertTrue($schema->hasSequence('a_seq'));
        self::assertTrue($schema->hasSequence('a_Seq'));
        self::assertTrue($schema->hasSequence('A_SEQ'));

        self::assertEquals($sequence, $schema->getSequence('a_seq'));
        self::assertEquals($sequence, $schema->getSequence('a_Seq'));
        self::assertEquals($sequence, $schema->getSequence('A_SEQ'));
    }

    public function testGetUnknownSequenceThrowsException()
    {
        $this->expectException(SchemaException::class);

        $schema = new Schema();
        $schema->getSequence('unknown');
    }

    public function testCreateSequence()
    {
        $schema   = new Schema();
        $sequence = $schema->createSequence('a_seq', 10, 20);

        self::assertEquals('a_seq', $sequence->getName());
        self::assertEquals(10, $sequence->getAllocationSize());
        self::assertEquals(20, $sequence->getInitialValue());

        self::assertTrue($schema->hasSequence('a_seq'));
        self::assertInstanceOf(Sequence::class, $schema->getSequence('a_seq'));

        $sequences = $schema->getSequences();
        self::assertArrayHasKey('public.a_seq', $sequences);
    }

    public function testDropSequence()
    {
        $sequence = new Sequence('a_seq', 1, 1);

        $schema = new Schema([], [$sequence]);

        $schema->dropSequence('a_seq');
        self::assertFalse($schema->hasSequence('a_seq'));
    }

    public function testAddSequenceTwiceThrowsException()
    {
        $this->expectException(SchemaException::class);

        $sequence = new Sequence('a_seq', 1, 1);

        $schema = new Schema([], [$sequence, $sequence]);
    }

    public function testConfigMaxIdentifierLength()
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setMaxIdentifierLength(5);

        $schema = new Schema([], [], $schemaConfig);
        $table  = $schema->createTable('smalltable');
        $table->addColumn('long_id', 'integer');
        $table->addIndex(['long_id']);

        $index = current($table->getIndexes());
        self::assertEquals(5, strlen($index->getName()));
    }

    public function testDeepClone()
    {
        $schema   = new Schema();
        $sequence = $schema->createSequence('baz');

        $tableA = $schema->createTable('foo');
        $tableA->addColumn('id', 'integer');

        $tableB = $schema->createTable('bar');
        $tableB->addColumn('id', 'integer');
        $tableB->addColumn('foo_id', 'integer');
        $tableB->addForeignKeyConstraint($tableA, ['foo_id'], ['id']);

        $schemaNew = clone $schema;

        self::assertNotSame($sequence, $schemaNew->getSequence('baz'));

        self::assertNotSame($tableA, $schemaNew->getTable('foo'));
        self::assertNotSame($tableA->getColumn('id'), $schemaNew->getTable('foo')->getColumn('id'));

        self::assertNotSame($tableB, $schemaNew->getTable('bar'));
        self::assertNotSame($tableB->getColumn('id'), $schemaNew->getTable('bar')->getColumn('id'));

        $fk = $schemaNew->getTable('bar')->getForeignKeys();
        $fk = current($fk);
        self::assertSame($schemaNew->getTable('bar'), $this->readAttribute($fk, '_localTable'));
    }

    /**
     * @group DBAL-219
     */
    public function testHasTableForQuotedAsset()
    {
        $schema = new Schema();

        $tableA = $schema->createTable('foo');
        $tableA->addColumn('id', 'integer');

        self::assertTrue($schema->hasTable('`foo`'));
    }

    /**
     * @group DBAL-669
     */
    public function testHasNamespace()
    {
        $schema = new Schema();

        self::assertFalse($schema->hasNamespace('foo'));

        $schema->createTable('foo');

        self::assertFalse($schema->hasNamespace('foo'));

        $schema->createTable('bar.baz');

        self::assertFalse($schema->hasNamespace('baz'));
        self::assertTrue($schema->hasNamespace('bar'));
        self::assertFalse($schema->hasNamespace('tab'));

        $schema->createTable('tab.taz');

        self::assertTrue($schema->hasNamespace('tab'));
    }

    /**
     * @group DBAL-669
     */
    public function testCreatesNamespace()
    {
        $schema = new Schema();

        self::assertFalse($schema->hasNamespace('foo'));

        $schema->createNamespace('foo');

        self::assertTrue($schema->hasNamespace('foo'));
        self::assertTrue($schema->hasNamespace('FOO'));
        self::assertTrue($schema->hasNamespace('`foo`'));
        self::assertTrue($schema->hasNamespace('`FOO`'));

        $schema->createNamespace('`bar`');

        self::assertTrue($schema->hasNamespace('bar'));
        self::assertTrue($schema->hasNamespace('BAR'));
        self::assertTrue($schema->hasNamespace('`bar`'));
        self::assertTrue($schema->hasNamespace('`BAR`'));

        self::assertSame(['foo' => 'foo', 'bar' => '`bar`'], $schema->getNamespaces());
    }

    /**
     * @group DBAL-669
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

        self::assertFalse($schema->hasNamespace('foo'));

        $schema->createTable('baz');

        self::assertFalse($schema->hasNamespace('foo'));
        self::assertFalse($schema->hasNamespace('baz'));

        $schema->createTable('foo.bar');

        self::assertTrue($schema->hasNamespace('foo'));
        self::assertFalse($schema->hasNamespace('bar'));

        $schema->createTable('`baz`.bloo');

        self::assertTrue($schema->hasNamespace('baz'));
        self::assertFalse($schema->hasNamespace('bloo'));

        $schema->createTable('`baz`.moo');

        self::assertTrue($schema->hasNamespace('baz'));
        self::assertFalse($schema->hasNamespace('moo'));
    }

    /**
     * @group DBAL-669
     */
    public function testCreatesNamespaceThroughAddingSequenceImplicitly()
    {
        $schema = new Schema();

        self::assertFalse($schema->hasNamespace('foo'));

        $schema->createSequence('baz');

        self::assertFalse($schema->hasNamespace('foo'));
        self::assertFalse($schema->hasNamespace('baz'));

        $schema->createSequence('foo.bar');

        self::assertTrue($schema->hasNamespace('foo'));
        self::assertFalse($schema->hasNamespace('bar'));

        $schema->createSequence('`baz`.bloo');

        self::assertTrue($schema->hasNamespace('baz'));
        self::assertFalse($schema->hasNamespace('bloo'));

        $schema->createSequence('`baz`.moo');

        self::assertTrue($schema->hasNamespace('baz'));
        self::assertFalse($schema->hasNamespace('moo'));
    }

    /**
     * @group DBAL-669
     */
    public function testVisitsVisitor()
    {
        $schema  = new Schema();
        $visitor = $this->createMock(Visitor::class);

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

        self::assertNull($schema->visit($visitor));
    }

    /**
     * @group DBAL-669
     */
    public function testVisitsNamespaceVisitor()
    {
        $schema  = new Schema();
        $visitor = $this->createMock(AbstractVisitor::class);

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

        self::assertNull($schema->visit($visitor));
    }
}

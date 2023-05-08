<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

use function array_shift;
use function strlen;

class SchemaTest extends TestCase
{
    public function testAddTable(): void
    {
        $tableName = 'public.foo';
        $table     = new Table($tableName);

        $schema = new Schema([$table]);

        self::assertTrue($schema->hasTable($tableName));

        self::assertSame([$table], $schema->getTables());
        self::assertSame($table, $schema->getTable($tableName));
        self::assertTrue($schema->hasTable($tableName));
    }

    public function testTableMatchingCaseInsensitive(): void
    {
        $table = new Table('Foo');

        $schema = new Schema([$table]);
        self::assertTrue($schema->hasTable('foo'));
        self::assertTrue($schema->hasTable('FOO'));

        self::assertSame($table, $schema->getTable('FOO'));
        self::assertSame($table, $schema->getTable('foo'));
        self::assertSame($table, $schema->getTable('Foo'));
    }

    public function testGetUnknownTableThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $schema = new Schema();
        $schema->getTable('unknown');
    }

    public function testCreateTableTwiceThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $tableName = 'foo';
        $table     = new Table($tableName);
        $tables    = [$table, $table];

        new Schema($tables);
    }

    public function testRenameTable(): void
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

    public function testDropTable(): void
    {
        $tableName = 'foo';
        $table     = new Table($tableName);
        $schema    = new Schema([$table]);

        self::assertTrue($schema->hasTable('foo'));

        $schema->dropTable('foo');

        self::assertFalse($schema->hasTable('foo'));
    }

    public function testCreateTable(): void
    {
        $schema = new Schema();

        self::assertFalse($schema->hasTable('foo'));

        $table = $schema->createTable('foo');

        self::assertEquals('foo', $table->getName());
        self::assertTrue($schema->hasTable('foo'));
    }

    public function testAddSequences(): void
    {
        $sequence = new Sequence('a_seq', 1, 1);

        $schema = new Schema([], [$sequence]);

        self::assertTrue($schema->hasSequence('a_seq'));
        self::assertSame('a_seq', $schema->getSequence('a_seq')->getName());

        self::assertEquals([$sequence], $schema->getSequences());
    }

    public function testSequenceAccessCaseInsensitive(): void
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

    public function testGetUnknownSequenceThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $schema = new Schema();
        $schema->getSequence('unknown');
    }

    public function testCreateSequence(): void
    {
        $schema   = new Schema();
        $sequence = $schema->createSequence('a_seq', 10, 20);

        self::assertEquals('a_seq', $sequence->getName());
        self::assertEquals(10, $sequence->getAllocationSize());
        self::assertEquals(20, $sequence->getInitialValue());

        self::assertTrue($schema->hasSequence('a_seq'));
        self::assertSame('a_seq', $schema->getSequence('a_seq')->getName());

        self::assertEquals([$sequence], $schema->getSequences());
    }

    public function testDropSequence(): void
    {
        $sequence = new Sequence('a_seq', 1, 1);

        $schema = new Schema([], [$sequence]);

        $schema->dropSequence('a_seq');
        self::assertFalse($schema->hasSequence('a_seq'));
    }

    public function testAddSequenceTwiceThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $sequence = new Sequence('a_seq', 1, 1);

        new Schema([], [$sequence, $sequence]);
    }

    public function testConfigMaxIdentifierLength(): void
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setMaxIdentifierLength(5);

        $schema = new Schema([], [], $schemaConfig);
        $table  = $schema->createTable('smalltable');
        $table->addColumn('long_id', Types::INTEGER);
        $table->addIndex(['long_id']);

        $indexes = $table->getIndexes();
        self::assertCount(1, $indexes);

        $index = array_shift($indexes);
        self::assertNotNull($index);
        self::assertEquals(5, strlen($index->getName()));
    }

    public function testDeepClone(): void
    {
        $schema   = new Schema();
        $sequence = $schema->createSequence('baz');

        $tableA = $schema->createTable('foo');
        $tableA->addColumn('id', Types::INTEGER);

        $tableB = $schema->createTable('bar');
        $tableB->addColumn('id', Types::INTEGER);
        $tableB->addColumn('foo_id', Types::INTEGER);
        $tableB->addForeignKeyConstraint($tableA->getName(), ['foo_id'], ['id']);

        $schemaNew = clone $schema;

        self::assertNotSame($sequence, $schemaNew->getSequence('baz'));

        self::assertNotSame($tableA, $schemaNew->getTable('foo'));
        self::assertNotSame($tableA->getColumn('id'), $schemaNew->getTable('foo')->getColumn('id'));

        self::assertNotSame($tableB, $schemaNew->getTable('bar'));
        self::assertNotSame($tableB->getColumn('id'), $schemaNew->getTable('bar')->getColumn('id'));
    }

    public function testHasTableForQuotedAsset(): void
    {
        $schema = new Schema();

        $tableA = $schema->createTable('foo');
        $tableA->addColumn('id', Types::INTEGER);

        self::assertTrue($schema->hasTable('`foo`'));
    }

    public function testHasNamespace(): void
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

    public function testCreatesNamespace(): void
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

        self::assertSame(['foo', '`bar`'], $schema->getNamespaces());
    }

    public function testThrowsExceptionOnCreatingNamespaceTwice(): void
    {
        $schema = new Schema();

        $schema->createNamespace('foo');

        $this->expectException(SchemaException::class);

        $schema->createNamespace('foo');
    }

    public function testCreatesNamespaceThroughAddingTableImplicitly(): void
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

    public function testCreatesNamespaceThroughAddingSequenceImplicitly(): void
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
}

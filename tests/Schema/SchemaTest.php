<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\ImproperlyQualifiedName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
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
        $table     = Table::editor()
            ->setUnquotedName($tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $schema = Schema::editor()
            ->addTable($table)
            ->create();

        self::assertTrue($schema->hasTable($tableName));

        self::assertSame([$table], $schema->getTables());
        self::assertSame($table, $schema->getTable($tableName));
        self::assertTrue($schema->hasTable($tableName));
    }

    public function testTableMatchingCaseInsensitive(): void
    {
        $table = $this->createTable('Foo');

        $schema = Schema::editor()
            ->addTable($table)
            ->create();

        self::assertTrue($schema->hasTable('foo'));
        self::assertTrue($schema->hasTable('FOO'));

        self::assertSame($table, $schema->getTable('FOO'));
        self::assertSame($table, $schema->getTable('foo'));
        self::assertSame($table, $schema->getTable('Foo'));
    }

    public function testGetUnknownTableThrowsException(): void
    {
        $schema = Schema::editor()
            ->create();

        $this->expectException(SchemaException::class);

        $schema->getTable('unknown');
    }

    public function testCreateTableTwiceThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $table  = $this->createTable('foo');
        $tables = [$table, $table];

        new Schema($tables);
    }

    public function testRenameTable(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $schema = Schema::editor()
            ->addTable($table)
            ->create();

        self::assertTrue($schema->hasTable('foo'));
        $schema->renameTable('foo', 'bar');
        self::assertFalse($schema->hasTable('foo'));
        self::assertTrue($schema->hasTable('bar'));
    }

    public function testDropTable(): void
    {
        $table = $this->createTable('foo');

        $schema = Schema::editor()
            ->addTable($table)
            ->create();

        self::assertTrue($schema->hasTable('foo'));

        $schema->dropTable('foo');

        self::assertFalse($schema->hasTable('foo'));
    }

    public function testCreateTable(): void
    {
        $schema = Schema::editor()
            ->create();

        self::assertFalse($schema->hasTable('foo'));

        $table = $schema->createTable('foo');

        self::assertEquals(OptionallyQualifiedName::unquoted('foo'), $table->getObjectName());
        self::assertTrue($schema->hasTable('foo'));
    }

    public function testAddSequences(): void
    {
        $sequence = Sequence::editor()
            ->setUnquotedName('a_seq')
            ->create();

        $schema = Schema::editor()
            ->addSequence($sequence)
            ->create();

        self::assertTrue($schema->hasSequence('a_seq'));
        self::assertEquals(
            OptionallyQualifiedName::unquoted('a_seq'),
            $schema->getSequence('a_seq')->getObjectName(),
        );

        self::assertEquals([$sequence], $schema->getSequences());
    }

    public function testSequenceAccessCaseInsensitive(): void
    {
        $sequence = Sequence::editor()
            ->setUnquotedName('a_Seq')
            ->create();

        $schema = Schema::editor()
            ->addSequence($sequence)
            ->create();

        self::assertTrue($schema->hasSequence('a_seq'));
        self::assertTrue($schema->hasSequence('a_Seq'));
        self::assertTrue($schema->hasSequence('A_SEQ'));

        self::assertEquals($sequence, $schema->getSequence('a_seq'));
        self::assertEquals($sequence, $schema->getSequence('a_Seq'));
        self::assertEquals($sequence, $schema->getSequence('A_SEQ'));
    }

    public function testGetUnknownSequenceThrowsException(): void
    {
        $schema = Schema::editor()
            ->create();

        $this->expectException(SchemaException::class);

        $schema->getSequence('unknown');
    }

    public function testCreateSequence(): void
    {
        $schema = Schema::editor()
            ->create();

        $sequence = $schema->createSequence('a_seq', 10, 20);

        self::assertEquals(
            OptionallyQualifiedName::unquoted('a_seq'),
            $sequence->getObjectName(),
        );
        self::assertEquals(10, $sequence->getAllocationSize());
        self::assertEquals(20, $sequence->getInitialValue());

        self::assertTrue($schema->hasSequence('a_seq'));
        self::assertEquals(
            OptionallyQualifiedName::unquoted('a_seq'),
            $schema->getSequence('a_seq')->getObjectName(),
        );

        self::assertEquals([$sequence], $schema->getSequences());
    }

    public function testDropSequence(): void
    {
        $sequence = Sequence::editor()
            ->setUnquotedName('a_seq')
            ->create();

        $schema = Schema::editor()
            ->addSequence($sequence)
            ->create();

        $schema->dropSequence('a_seq');
        self::assertFalse($schema->hasSequence('a_seq'));
    }

    public function testAddSequenceTwiceThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $sequence = Sequence::editor()
            ->setUnquotedName('a_seq')
            ->create();

        new Schema([], [$sequence, $sequence]);
    }

    public function testConfigMaxIdentifierLength(): void
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setMaxIdentifierLength(5);

        $schema = Schema::editor()
            ->setSchemaConfig($schemaConfig)
            ->create();

        $table = $schema->createTable('smalltable');
        $table->addColumn('long_id', Types::INTEGER);
        $table->addIndex(['long_id']);

        $indexes = $table->getIndexes();
        self::assertCount(1, $indexes);

        $index = array_shift($indexes);
        self::assertNotNull($index);
        self::assertEquals(5, strlen($index->getObjectName()->toString()));
    }

    public function testDeepClone(): void
    {
        $tableA = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $tableB = Table::editor()
            ->setUnquotedName('bar')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('foo_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('foo_id')
                    ->setUnquotedReferencedTableName('foo')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $sequence = Sequence::editor()->setUnquotedName('baz')->create();

        $schema = Schema::editor()
            ->setTables($tableA, $tableB)
            ->addSequence($sequence)
            ->create();

        $schemaNew = clone $schema;

        self::assertNotSame($sequence, $schemaNew->getSequence('baz'));

        self::assertNotSame($tableA, $schemaNew->getTable('foo'));
        self::assertNotSame($tableA->getColumn('id'), $schemaNew->getTable('foo')->getColumn('id'));

        self::assertNotSame($tableB, $schemaNew->getTable('bar'));
        self::assertNotSame($tableB->getColumn('id'), $schemaNew->getTable('bar')->getColumn('id'));
    }

    public function testHasTableForQuotedAsset(): void
    {
        $tableA = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $schema = Schema::editor()
            ->addTable($tableA)
            ->create();

        self::assertTrue($schema->hasTable('`foo`'));
    }

    public function testHasNamespace(): void
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setName('public');

        $schema = Schema::editor()
            ->setSchemaConfig($schemaConfig)
            ->create();

        self::assertFalse($schema->hasNamespace('foo'));

        $schema = $schema->edit()
            ->addTable($this->createTable('foo'))
            ->create();

        self::assertFalse($schema->hasNamespace('foo'));

        $schema = $schema->edit()
            ->addTable($this->createTable('baz', 'bar'))
            ->create();

        self::assertFalse($schema->hasNamespace('baz'));
        self::assertTrue($schema->hasNamespace('bar'));
        self::assertFalse($schema->hasNamespace('tab'));

        $schema = $schema->edit()
            ->addTable($this->createTable('taz', 'tab'))
            ->create();

        self::assertTrue($schema->hasNamespace('tab'));
    }

    public function testCreatesNamespaceThroughAddingTableImplicitly(): void
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setName('public');

        $schema = Schema::editor()
            ->setSchemaConfig($schemaConfig)
            ->create();

        self::assertFalse($schema->hasNamespace('foo'));

        $schema = $schema->edit()
            ->addTable($this->createTable('baz'))
            ->create();

        self::assertFalse($schema->hasNamespace('foo'));
        self::assertFalse($schema->hasNamespace('baz'));

        $schema = $schema->edit()
            ->addTable($this->createTable('bar', 'foo'))
            ->create();

        self::assertTrue($schema->hasNamespace('foo'));
        self::assertFalse($schema->hasNamespace('bar'));

        $schema = $schema->edit()
            ->addTable($this->createTable('bloo', 'baz'))
            ->create();

        self::assertTrue($schema->hasNamespace('baz'));
        self::assertFalse($schema->hasNamespace('bloo'));

        $schema = $schema->edit()
            ->addTable($this->createTable('moo', 'baz'))
            ->create();

        self::assertTrue($schema->hasNamespace('baz'));
        self::assertFalse($schema->hasNamespace('moo'));
    }

    public function testCreatesNamespaceThroughAddingSequenceImplicitly(): void
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setName('public');

        $schema = Schema::editor()
            ->setSchemaConfig($schemaConfig)
            ->create();

        self::assertFalse($schema->hasNamespace('foo'));

        $schema = $schema->edit()
            ->addSequence($this->createSequence('baz'))
            ->create();

        self::assertFalse($schema->hasNamespace('foo'));
        self::assertFalse($schema->hasNamespace('baz'));

        $schema = $schema->edit()
            ->addSequence($this->createSequence('bar', 'foo'))
            ->create();

        self::assertTrue($schema->hasNamespace('foo'));
        self::assertFalse($schema->hasNamespace('bar'));

        $schema = $schema->edit()
            ->addSequence($this->createSequence('bloo', 'baz'))
            ->create();

        self::assertTrue($schema->hasNamespace('baz'));
        self::assertFalse($schema->hasNamespace('bloo'));

        $schema = $schema->edit()
            ->addSequence($this->createSequence('moo', 'baz'))
            ->create();

        self::assertTrue($schema->hasNamespace('baz'));
        self::assertFalse($schema->hasNamespace('moo'));
    }

    public function testAddObjectWithQualifiedNameAfterUnqualifiedName(): void
    {
        $this->expectException(ImproperlyQualifiedName::class);

        new Schema([$this->createTable('t'), $this->createTable('t', 'public')]);
    }

    public function testAddObjectWithUnqualifiedNameAfterQualifiedName(): void
    {
        $this->expectException(ImproperlyQualifiedName::class);

        new Schema([$this->createTable('t', 'public'), $this->createTable('t')]);
    }

    public function testReferenceByQualifiedNameAmongUnqualifiedNames(): void
    {
        $schema = Schema::editor()
            ->addTable(
                $this->createTable('t'),
            )->create();

        $this->expectException(ImproperlyQualifiedName::class);

        $schema->hasTable('public.t');
    }

    public function testReferenceByUnqualifiedNameAmongQualifiedNames(): void
    {
        $schema = Schema::editor()
            ->addTable(
                $this->createTable('t', 'public'),
            )->create();

        $this->expectException(ImproperlyQualifiedName::class);

        $schema->hasTable('t');
    }

    public function testAddObjectWithQualifiedNameAfterUnqualifiedNameWithDefaultNamespace(): void
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setName('public');

        $schema = new Schema([
            $this->createTable('t'),
            $this->createTable('s', 'public'),
        ], [], $schemaConfig);

        self::assertTrue($schema->hasTable('t'));
        self::assertTrue($schema->hasTable('public.s'));
    }

    public function testAddObjectWithUnqualifiedNameAfterQualifiedNameWithDefaultNamespace(): void
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setName('public');

        $schema = new Schema([
            $this->createTable('t', 'public'),
            $this->createTable('s'),
        ], [], $schemaConfig);

        self::assertTrue($schema->hasTable('public.t'));
        self::assertTrue($schema->hasTable('s'));
    }

    public function testReferencingByUnqualifiedNameAmongQualifiedNamesWithDefaultNamespace(): void
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setName('public');

        $schema = Schema::editor()
            ->setSchemaConfig($schemaConfig)
            ->addTable(
                $this->createTable('t', 'public'),
            )
            ->create();

        self::assertTrue($schema->hasTable('t'));
        self::assertTrue($schema->hasTable('public.t'));

        self::assertFalse($schema->hasTable('s'));
        self::assertFalse($schema->hasTable('public.s'));
    }

    public function testGetNamespaces(): void
    {
        $schema = new Schema();

        $schema->createTable('public.t');
        self::assertEquals(['public'], $schema->getNamespaces());

        $schema->createSequence('public.s');
        self::assertEquals(['public'], $schema->getNamespaces());

        $schema->dropTable('public.t');
        self::assertEquals(['public'], $schema->getNamespaces());

        $schema->dropSequence('public.s');
        self::assertEmpty(
            $schema->getNamespaces(),
            'Dropping all objects inside a schema should result in the schema being dropped as well.',
        );
    }

    /**
     * @param non-empty-string  $unqualifiedName
     * @param ?non-empty-string $qualifier
     */
    private function createTable(string $unqualifiedName, ?string $qualifier = null): Table
    {
        return Table::editor()
            ->setUnquotedName($unqualifiedName, $qualifier)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();
    }

    /**
     * @param non-empty-string  $unqualifiedName
     * @param ?non-empty-string $qualifier
     */
    private function createSequence(string $unqualifiedName, ?string $qualifier = null): Sequence
    {
        return Sequence::editor()
            ->setUnquotedName($unqualifiedName, $qualifier)
            ->create();
    }
}

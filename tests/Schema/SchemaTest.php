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

    public function testAddSequenceTwiceThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $sequence = Sequence::editor()
            ->setUnquotedName('a_seq')
            ->create();

        new Schema([], [$sequence, $sequence]);
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
        $schema = Schema::editor()
            ->setDefaultNamespace('public')
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
        $schema = Schema::editor()
            ->setDefaultNamespace('public')
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
        $schema = Schema::editor()
            ->setDefaultNamespace('public')
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

        $schema = Schema::editor()
            ->setSchemaConfig($schemaConfig)
            ->setTables(
                $this->createTable('t'),
                $this->createTable('s', 'public'),
            )
            ->create();

        self::assertTrue($schema->hasTable('t'));
        self::assertTrue($schema->hasTable('public.s'));
    }

    public function testAddObjectWithUnqualifiedNameAfterQualifiedNameWithDefaultNamespace(): void
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setName('public');

        $schema = Schema::editor()
            ->setSchemaConfig($schemaConfig)
            ->setTables(
                $this->createTable('t', 'public'),
                $this->createTable('s'),
            )
            ->create();

        self::assertTrue($schema->hasTable('public.t'));
        self::assertTrue($schema->hasTable('s'));
    }

    public function testReferencingByUnqualifiedNameAmongQualifiedNamesWithDefaultNamespace(): void
    {
        $schema = Schema::editor()
            ->setDefaultNamespace('public')
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
        $schema = Schema::editor()
            ->addTable($this->createTable('t', 'public'))
            ->create();
        self::assertEquals(['public'], $schema->getNamespaces());

        $schema = $schema->edit()
            ->addSequence($this->createSequence('s', 'public'))
            ->create();
        self::assertEquals(['public'], $schema->getNamespaces());

        $schema = $schema->edit()
            ->dropTableByUnquotedName('t', 'public')
            ->create();
        self::assertEquals(['public'], $schema->getNamespaces());

        $schema = $schema->edit()
            ->dropSequenceByUnquotedName('s', 'public')
            ->create();
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

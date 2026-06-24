<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\ImproperlyQualifiedName;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    public function testAddTable(): void
    {
        $tableName = 'public.foo';
        $table     = Table::editor()
            ->setUnquotedName('foo', 'public')
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

    /** @param callable(Schema): mixed $lookup */
    #[DataProvider('lookupWithInvalidNameProvider')]
    public function testLookupWithInvalidName(callable $lookup): void
    {
        $schema = Schema::editor()
            ->create();

        $this->expectException(InvalidName::class);

        $lookup($schema);
    }

    /** @return iterable<string, array{callable(Schema): mixed}> */
    public static function lookupWithInvalidNameProvider(): iterable
    {
        yield 'get table' => [
            static fn (Schema $schema): Table => $schema->getTable('"orders'),
        ];

        yield 'has table' => [
            static fn (Schema $schema): bool => $schema->hasTable('"orders'),
        ];

        yield 'get sequence' => [
            static fn (Schema $schema): Sequence => $schema->getSequence('"id_seq'),
        ];

        yield 'has sequence' => [
            static fn (Schema $schema): bool => $schema->hasSequence('"id_seq'),
        ];

        yield 'has namespace' => [
            static fn (Schema $schema): bool => $schema->hasNamespace('"billing'),
        ];
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
        $schema = Schema::editor()
            ->setDefaultNamespace('public')
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
        $schema = Schema::editor()
            ->setDefaultNamespace('public')
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

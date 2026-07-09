<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\ForeignKeyDoesNotExist;
use Doctrine\DBAL\Schema\Exception\IndexDoesNotExist;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\InvalidTableDefinition;
use Doctrine\DBAL\Schema\Exception\UniqueConstraintDoesNotExist;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableConfiguration;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function strlen;

class TableTest extends TestCase
{
    public function testEmptyColumns(): void
    {
        $this->expectException(InvalidTableDefinition::class);

        new Table(
            OptionallyQualifiedName::unquoted('users'),
            [], // @phpstan-ignore argument.type
            [],
            [],
            [],
            [],
            [],
            new TableConfiguration(64),
            null,
            [],
        );
    }

    public function testGetName(): void
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

        self::assertEquals(
            OptionallyQualifiedName::unquoted('foo'),
            $table->getObjectName(),
        );
    }

    public function testColumns(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        self::assertTrue($table->hasColumn('foo'));
        self::assertTrue($table->hasColumn('bar'));
        self::assertFalse($table->hasColumn('baz'));

        self::assertEquals(
            UnqualifiedName::unquoted('foo'),
            $table->getColumn('foo')->getObjectName(),
        );

        self::assertEquals(
            UnqualifiedName::unquoted('foo'),
            $table->getColumn('foo')->getObjectName(),
        );

        self::assertCount(2, $table->getColumns());
    }

    public function testRenameColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->create();

        $table = $table->edit()
            ->renameColumnByUnquotedName('foo', 'bar')
            ->create();

        self::assertTrue($table->hasColumn('bar'), 'Should now have bar column');
        self::assertFalse($table->hasColumn('foo'), 'Should not have foo column anymore');
        self::assertCount(1, $table->getColumns());
        self::assertEquals(['bar' => 'foo'], $table->getRenamedColumns());

        $table = $table->edit()
            ->renameColumnByUnquotedName('bar', 'baz')
            ->create();

        self::assertTrue($table->hasColumn('baz'), 'Should now have baz column');
        self::assertFalse($table->hasColumn('bar'), 'Should not have bar column anymore');
        self::assertEquals(['baz' => 'bar'], $table->getRenamedColumns());
        self::assertCount(1, $table->getColumns());
    }

    public function testRenameColumnLoop(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('baz')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table = $table->edit()
            ->renameColumnByUnquotedName('baz', 'foo')
            ->renameColumnByUnquotedName('foo', 'Baz')
            ->create();

        self::assertCount(1, $table->getColumns());
        self::assertCount(0, $table->getRenamedColumns());
    }

    public function testRenameColumnInIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('t')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('c1')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('c2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx_c1_c2')
                    ->setUnquotedColumnNames('c1', 'c2')
                    ->create(),
            )
            ->create();

        $table = $table->edit()
            ->renameColumnByUnquotedName('c1', 'c1a')
            ->create();

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('c1a'), null),
            new IndexedColumn(UnqualifiedName::unquoted('c2'), null),
        ], $table->getIndex('idx_c1_c2')->getIndexedColumns());
    }

    public function testRenameColumnInForeignKeyConstraint(): void
    {
        $table = Table::editor()
            ->setUnquotedName('t1')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('c1')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('c2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_c1_c2')
                    ->setUnquotedReferencingColumnNames('c1', 'c2')
                    ->setUnquotedReferencedTableName('t2')
                    ->setUnquotedReferencedColumnNames('c1', 'c2')
                    ->create(),
            )
            ->create();

        $table = $table->edit()
            ->renameColumnByUnquotedName('c2', 'c2a')
            ->create();

        self::assertEquals([
            UnqualifiedName::unquoted('c1'),
            UnqualifiedName::unquoted('c2a'),
        ], $table->getForeignKey('fk_c1_c2')->getReferencingColumnNames());
    }

    public function testRenameColumnInUniqueConstraint(): void
    {
        $table = Table::editor()
            ->setUnquotedName('t')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('c1')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('c2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedName('uq_c1_c2')
                    ->setUnquotedColumnNames('c1', 'c2')
                    ->create(),
            )
            ->create();

        $table = $table->edit()
            ->renameColumnByUnquotedName('c1', 'c1a')
            ->create();

        self::assertEquals([
            UnqualifiedName::unquoted('c1a'),
            UnqualifiedName::unquoted('c2'),
        ], $table->getUniqueConstraint('uq_c1_c2')->getColumnNames());
    }

    public function testColumnsCaseInsensitive(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('Foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        self::assertTrue($table->hasColumn('Foo'));
        self::assertTrue($table->hasColumn('foo'));
        self::assertTrue($table->hasColumn('FOO'));

        $column = $table->getColumn('Foo');
        self::assertSame($column, $table->getColumn('foo'));
        self::assertSame($column, $table->getColumn('FOO'));
    }

    public function testCreateColumn(): void
    {
        $type = Type::getType(Types::INTEGER);

        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        self::assertTrue($table->hasColumn('bar'));
        self::assertSame($type, $table->getColumn('bar')->getType());
    }

    public function testDropColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        self::assertTrue($table->hasColumn('foo'));
        self::assertTrue($table->hasColumn('bar'));

        $table = $table->edit()
            ->dropColumnByUnquotedName('foo')
            ->create();

        self::assertFalse($table->hasColumn('foo'));
        self::assertTrue($table->hasColumn('bar'));
    }

    public function testGetUnknownColumnThrowsException(): void
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

        $this->expectException(SchemaException::class);

        $table->getColumn('unknown');
    }

    public function testAddColumnTwiceThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();
    }

    public function testCreateIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('baz')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('foo_foo_bar_idx')
                    ->setUnquotedColumnNames('foo', 'bar')
                    ->create(),
                Index::editor()
                    ->setUnquotedName('foo_bar_baz_uniq')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('bar', 'baz')
                    ->create(),
            )
            ->create();

        self::assertTrue($table->hasIndex('foo_foo_bar_idx'));
        self::assertTrue($table->hasIndex('foo_bar_baz_uniq'));
    }

    public function testIndexCaseInsensitive(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('baz')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('Foo_Idx')
                    ->setUnquotedColumnNames('foo', 'bar', 'baz')
                    ->create(),
            )
            ->create();

        self::assertTrue($table->hasIndex('foo_idx'));
        self::assertTrue($table->hasIndex('Foo_Idx'));
        self::assertTrue($table->hasIndex('FOO_IDX'));
    }

    public function testAddIndexes(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('foo_idx')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
                Index::editor()
                    ->setUnquotedName('bar_idx')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('bar')
                    ->create(),
            )
            ->create();

        self::assertTrue($table->hasIndex('foo_idx'));
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertFalse($table->hasIndex('some_idx'));

        self::assertEquals(
            UnqualifiedName::unquoted('foo_idx'),
            $table->getIndex('foo_idx')->getObjectName(),
        );

        self::assertEquals(
            UnqualifiedName::unquoted('bar_idx'),
            $table->getIndex('bar_idx')->getObjectName(),
        );
    }

    public function testGetUnknownIndexThrowsException(): void
    {
        $table = $this->createTableWithSingleColumn();

        $this->expectException(IndexDoesNotExist::class);

        $table->getIndex('unknown');
    }

    public function testGetUnknownForeignKeyThrowsException(): void
    {
        $table = $this->createTableWithSingleColumn();

        $this->expectException(ForeignKeyDoesNotExist::class);

        $table->getForeignKey('unknown');
    }

    public function testGetUnknownUniqueConstraintThrowsException(): void
    {
        $table = $this->createTableWithSingleColumn();

        $this->expectException(UniqueConstraintDoesNotExist::class);

        $table->getUniqueConstraint('unknown');
    }

    /** @param callable(Table): mixed $lookup */
    #[DataProvider('lookupWithInvalidNameProvider')]
    public function testLookupWithInvalidName(callable $lookup): void
    {
        $table = $this->createTableWithSingleColumn();

        $this->expectException(InvalidName::class);

        $lookup($table);
    }

    /** @return iterable<string, array{callable(Table): mixed}> */
    public static function lookupWithInvalidNameProvider(): iterable
    {
        yield 'has column' => [
            static fn (Table $table): bool => $table->hasColumn('"email'),
        ];

        yield 'get column' => [
            static fn (Table $table): Column => $table->getColumn('"email'),
        ];

        yield 'has index' => [
            static fn (Table $table): bool => $table->hasIndex('"idx_email'),
        ];

        yield 'get index' => [
            static fn (Table $table): Index => $table->getIndex('"idx_email'),
        ];

        yield 'has unique constraint' => [
            static fn (Table $table): bool => $table->hasUniqueConstraint('"uq_email'),
        ];

        yield 'get unique constraint' => [
            static fn (Table $table): UniqueConstraint => $table->getUniqueConstraint('"uq_email'),
        ];

        yield 'has foreign key' => [
            static fn (Table $table): bool => $table->hasForeignKey('"fk_users'),
        ];

        yield 'get foreign key' => [
            static fn (Table $table): ForeignKeyConstraint => $table->getForeignKey('"fk_users'),
        ];
    }

    private function createTableWithSingleColumn(): Table
    {
        return Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();
    }

    public function testAddTwoIndexesWithSameNameThrowsException(): void
    {
        $index1 = Index::editor()
            ->setUnquotedName('foo_idx')
            ->setUnquotedColumnNames('foo')
            ->create();

        $index2 = $index1->edit()
            ->setUnquotedColumnNames('bar')
            ->create();

        $columns = [
            Column::editor()
                ->setUnquotedName('foo')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('bar')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ];

        $this->expectException(SchemaException::class);

        Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(...$columns)
            ->setIndexes($index1, $index2)
            ->create();
    }

    public function testAddIndexWithUnknownColumnThrowsException(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('invalidName')
                    ->setUnquotedColumnNames('bar')
                    ->create(),
            );

        $this->expectException(SchemaException::class);

        $editor->create();
    }

    public function testAddPrimaryKeyConstraintWithUnknownColumnThrowsException(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('bar')
                    ->create(),
            );

        $this->expectException(SchemaException::class);

        $editor->create();
    }

    public function testAddUniqueConstraintWithUnknownColumnThrowsException(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedName('uq_bar')
                    ->setUnquotedColumnNames('bar')
                    ->create(),
            );

        $this->expectException(SchemaException::class);

        $editor->create();
    }

    public function testOptions(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setOptions(['foo' => 'bar'])
            ->create();

        self::assertTrue($table->hasOption('foo'));
        self::assertEquals('bar', $table->getOption('foo'));
    }

    public function testBuilderAddUniqueIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('my_idx')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('bar')
                    ->create(),
            )
            ->create();

        self::assertTrue($table->hasIndex('my_idx'));
        self::assertSame(IndexType::UNIQUE, $table->getIndex('my_idx')->getType());
    }

    public function testBuilderAddIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('my_idx')
                    ->setUnquotedColumnNames('bar')
                    ->create(),
            )
            ->create();

        self::assertTrue($table->hasIndex('my_idx'));
        self::assertSame(IndexType::REGULAR, $table->getIndex('my_idx')->getType());
    }

    public function testAddIndexTruncatesAutoGeneratedNameToMaxIdentifierLength(): void
    {
        $table = Table::editor()
            ->setConfiguration(new TableConfiguration(5))
            ->setUnquotedName('smalltable')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('long_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setUnquotedColumnNames('long_id'),
            )
            ->create();

        $indexes = $table->getIndexes();
        self::assertCount(1, $indexes);
        self::assertSame(5, strlen($indexes[0]->getObjectName()->getIdentifier()->getValue()));
    }

    public function testBuilderOptions(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setOptions(['foo' => 'bar'])
            ->create();

        self::assertTrue($table->hasOption('foo'));
        self::assertEquals('bar', $table->getOption('foo'));
    }

    public function testAllowImplicitSchemaTableInAutogeneratedIndexNames(): void
    {
        $table = Table::editor()
            ->setUnquotedName('bar', 'foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('baz')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setUnquotedColumnNames('baz'),
            )
            ->create();

        self::assertCount(1, $table->getIndexes());
    }

    public function testAddIndexViaEditorKeepsItsName(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('a')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('my_idx')
                    ->setUnquotedColumnNames('a'),
            )
            ->create();

        self::assertTrue($table->hasIndex('my_idx'));
    }

    public function testAddForeignKeyConstraintWithUnknownReferencingColumnThrowsException(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('bar')
                    ->setUnquotedReferencedTableName('baz')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            );

        $this->expectException(SchemaException::class);

        $editor->create();
    }

    public function testAddForeignKeyIndexImplicitly(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('id')
                    ->setUnquotedReferencedTableName('bar')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('id')
                    ->setUnquotedReferencedTableName('bar')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $indexes = $table->getIndexes();
        self::assertCount(1, $indexes);
        $index = $indexes[0];

        self::assertTrue($table->hasIndex($index->getObjectName()->toString()));

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('id'), null),
        ], $index->getIndexedColumns());
    }

    public function testAddForeignKeyDoesNotCreateDuplicateIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('bar_idx')
                    ->setUnquotedColumnNames('bar')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('bar')
                    ->setUnquotedReferencedTableName('foo')
                    ->setUnquotedReferencedColumnNames('foo')
                    ->create(),
            )
            ->create();

        self::assertCount(1, $table->getIndexes());
        self::assertTrue($table->hasIndex('bar_idx'));

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('bar'), null),
        ], $table->getIndex('bar_idx')->getIndexedColumns());
    }

    public function testAddForeignKeyAddsImplicitIndexIfIndexColumnsDoNotSpan(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('baz')
                    ->setTypeName(Types::STRING)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bloo')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('composite_idx')
                    ->setUnquotedColumnNames('baz', 'bar')
                    ->create(),
                Index::editor()
                    ->setUnquotedName('full_idx')
                    ->setUnquotedColumnNames('bar', 'baz', 'bloo')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('bar', 'baz')
                    ->setUnquotedReferencedTableName('bar')
                    ->setUnquotedReferencedColumnNames('foo', 'baz')
                    ->create(),
            )
            ->create();

        self::assertCount(3, $table->getIndexes());
        self::assertTrue($table->hasIndex('composite_idx'));
        self::assertTrue($table->hasIndex('full_idx'));
        self::assertTrue($table->hasIndex('idx_8c73652176ff8caa78240498'));

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('baz'), null),
            new IndexedColumn(UnqualifiedName::unquoted('bar'), null),
        ], $table->getIndex('composite_idx')->getIndexedColumns());

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('bar'), null),
            new IndexedColumn(UnqualifiedName::unquoted('baz'), null),
            new IndexedColumn(UnqualifiedName::unquoted('bloo'), null),
        ], $table->getIndex('full_idx')->getIndexedColumns());

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('bar'), null),
            new IndexedColumn(UnqualifiedName::unquoted('baz'), null),
        ], $table->getIndex('idx_8c73652176ff8caa78240498')->getIndexedColumns());
    }

    public function testOverrulingIndexDoesNotDropOverruledIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('bar')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('baz')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setUnquotedColumnNames('baz'),
            )
            ->create();

        $indexes = $table->getIndexes();
        self::assertCount(1, $indexes);
        $index = $indexes[0];

        $table = $table->edit()
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('baz'),
            )
            ->create();

        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex($index->getObjectName()->toString()));
    }

    public function testAllowsAddingDuplicateIndexesBasedOnColumns(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('bar_idx')
                    ->setUnquotedColumnNames('bar')
                    ->create(),
                Index::editor()
                    ->setUnquotedName('duplicate_idx')
                    ->setUnquotedColumnNames('bar')
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('bar_idx')
                    ->setUnquotedColumnNames('bar')
                    ->create(),
                Index::editor()
                    ->setUnquotedName('duplicate_idx')
                    ->setUnquotedColumnNames('bar')
                    ->create(),
            )
            ->create();

        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertTrue($table->hasIndex('duplicate_idx'));

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('bar'), null),
        ], $table->getIndex('bar_idx')->getIndexedColumns());

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('bar'), null),
        ], $table->getIndex('duplicate_idx')->getIndexedColumns());
    }

    public function testAllowsAddingFulfillingIndexesBasedOnColumns(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('baz')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('bar_idx')
                    ->setUnquotedColumnNames('bar')
                    ->create(),
                Index::editor()
                    ->setUnquotedName('fulfilling_idx')
                    ->setUnquotedColumnNames('bar', 'baz')
                    ->create(),
            )
            ->create();

        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertTrue($table->hasIndex('fulfilling_idx'));

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('bar'), null),
        ], $table->getIndex('bar_idx')->getIndexedColumns());

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('bar'), null),
            new IndexedColumn(UnqualifiedName::unquoted('baz'), null),
        ], $table->getIndex('fulfilling_idx')->getIndexedColumns());
    }

    public function testAddingFulfillingRegularIndexOverridesImplicitForeignKeyConstraintIndex(): void
    {
        $localTable = Table::editor()
            ->setUnquotedName('local')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('id')
                    ->setUnquotedReferencedTableName('foreign')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('id')
                    ->setUnquotedReferencedTableName('foreign')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertCount(1, $localTable->getIndexes());

        $localTable = $localTable->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('explicit_idx')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('explicit_idx'));
    }

    public function testAddingFulfillingUniqueIndexOverridesImplicitForeignKeyConstraintIndex(): void
    {
        $localTable = Table::editor()
            ->setUnquotedName('local')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('id')
                    ->setUnquotedReferencedTableName('foreign')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertCount(1, $localTable->getIndexes());

        $localTable = $localTable->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('explicit_idx')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('explicit_idx'));
    }

    public function testAddingFulfillingExplicitIndexOverridingImplicitForeignKeyConstraintIndexWithSameName(): void
    {
        $localTable = Table::editor()
            ->setUnquotedName('local')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('id')
                    ->setUnquotedReferencedTableName('foreign')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('id')
                    ->setUnquotedReferencedTableName('foreign')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('IDX_8BD688E8BF396750'));

        $implicitIndex = $localTable->getIndex('IDX_8BD688E8BF396750');

        $localTable = $localTable->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('IDX_8BD688E8BF396750')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('IDX_8BD688E8BF396750'));
        self::assertNotSame($implicitIndex, $localTable->getIndex('IDX_8BD688E8BF396750'));
    }

    public function testQuotedTableName(): void
    {
        $table = Table::editor()
            ->setQuotedName('bar')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $mysqlPlatform  = new MySQLPlatform();
        $sqlitePlatform = new SQLitePlatform();

        self::assertEquals('bar', $table->getObjectName()->getUnqualifiedName()->getValue());
        self::assertEquals('`bar`', $table->getObjectName()->toSQL($mysqlPlatform));
        self::assertEquals('"bar"', $table->getObjectName()->toSQL($sqlitePlatform));
    }

    public function testTableHasPrimaryKey(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        self::assertNull($table->getPrimaryKeyConstraint());

        $table = $table->edit()
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->create();

        self::assertNotNull($table->getPrimaryKeyConstraint());

        $table = $table->edit()
            ->dropPrimaryKeyConstraint()
            ->create();

        self::assertNull($table->getPrimaryKeyConstraint());
    }

    public function testAddForeignKeyWithQuotedColumnsAndTable(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setQuotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setQuotedReferencingColumnNames('foo', 'bar')
                    ->setQuotedReferencedTableName('boing')
                    ->setUnquotedReferencedColumnNames('id1', 'id2')
                    ->create(),
            )
            ->create();

        self::assertCount(1, $table->getForeignKeys());
    }

    public function testQuoteSchemaPrefixed(): void
    {
        $table = Table::editor()
            ->setQuotedName('test', 'test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        self::assertEquals('"test"."test"', $table->getObjectName()->toString());
        self::assertEquals('`test`.`test`', $table->getObjectName()->toSQL(new MySQLPlatform()));
    }

    public function testDropIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertTrue($table->hasIndex('idx'));

        $table = $table->edit()
            ->dropIndexByUnquotedName('idx')
            ->create();

        self::assertFalse($table->hasIndex('idx'));
    }

    public function testRenameIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $table = $table->edit()
            ->renameIndexByUnquotedName('idx', 'idx_new')
            ->create();

        self::assertFalse($table->hasIndex('idx'));
        self::assertTrue($table->hasIndex('idx_new'));
    }

    public function testKeepsPredicateOnRenamingRegularIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx_bar')
                    ->setUnquotedColumnNames('id')
                    ->setPredicate('1 = 1')
                    ->create(),
            )
            ->create();

        $table = $table->edit()
            ->renameIndexByUnquotedName('idx_bar', 'idx_baz')
            ->create();

        self::assertSame('1 = 1', $table->getIndex('idx_baz')->getPredicate());
    }

    public function testKeepsPredicateOnRenamingUniqueIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx_bar')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('id')
                    ->setPredicate('1 = 1')
                    ->create(),
            )
            ->create();

        $table = $table->edit()
            ->renameIndexByUnquotedName('idx_bar', 'idx_baz')
            ->create();

        self::assertSame('1 = 1', $table->getIndex('idx_baz')->getPredicate());
    }

    public function testTableComment(): void
    {
        $table = Table::editor()
            ->setUnquotedName('bar')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        self::assertNull($table->getComment());

        $table = $table->edit()
            ->setComment('foo')
            ->create();

        self::assertEquals('foo', $table->getComment());
    }

    public function testUniqueConstraintWithEmptyName(): void
    {
        $columns = [
            Column::editor()
                ->setUnquotedName('column1')
                ->setTypeName(Types::STRING)
                ->create(),
            Column::editor()
                ->setUnquotedName('column2')
                ->setTypeName(Types::STRING)
                ->create(),
            Column::editor()
                ->setUnquotedName('column3')
                ->setTypeName(Types::STRING)
                ->create(),
            Column::editor()
                ->setUnquotedName('column4')
                ->setTypeName(Types::STRING)
                ->create(),
        ];

        $uniqueConstraints = [
            UniqueConstraint::editor()
                ->setUnquotedColumnNames('column1', 'column2')
                ->create(),
            UniqueConstraint::editor()
                ->setUnquotedColumnNames('column3', 'column4')
                ->create(),
        ];

        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(...$columns)
            ->setUniqueConstraints(...$uniqueConstraints)
            ->create();

        self::assertSame($uniqueConstraints, $table->getUniqueConstraints());
    }

    public function testAddIndexByColumnsAutoGeneratesName(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('a')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('b')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setUnquotedColumnNames('a', 'b'),
            )
            ->create();

        self::assertTrue($table->hasIndex('IDX_8C736521E8B7BE4371BEEFF9'));

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('a'), null),
            new IndexedColumn(UnqualifiedName::unquoted('b'), null),
        ], $table->getIndex('IDX_8C736521E8B7BE4371BEEFF9')->getIndexedColumns());
    }

    public function testAddUniqueIndexByColumnsAutoGeneratesName(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('a')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('b')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('a', 'b'),
            )
            ->create();

        self::assertTrue($table->hasIndex('UNIQ_8C736521E8B7BE4371BEEFF9'));
        self::assertSame(IndexType::UNIQUE, $table->getIndex('UNIQ_8C736521E8B7BE4371BEEFF9')->getType());
    }

    public function testAddIndexEditorWithExplicitNameKeepsIt(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('a')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('my_index')
                    ->setUnquotedColumnNames('a'),
            )
            ->create();

        self::assertTrue($table->hasIndex('my_index'));
    }

    public function testAddIndexEditorNamesAfterRenamedTable(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setUnquotedColumnNames('id'),
            )
            ->setUnquotedName('bar')
            ->create();

        // The index is named after the table's final name, not the one set when the index was added.
        self::assertTrue($table->hasIndex('IDX_76FF8CAABF396750'));
    }
}

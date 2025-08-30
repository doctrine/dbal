<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\ForeignKeyAlreadyExists;
use Doctrine\DBAL\Schema\Exception\IndexDoesNotExist;
use Doctrine\DBAL\Schema\Exception\InvalidForeignKeyConstraintDefinition;
use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ValueError;

class TableTest extends TestCase
{
    use VerifyDeprecations;

    public function testCreateWithInvalidTableName(): void
    {
        $this->expectException(Exception::class);

        new Table('');
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
        $typeTxt = Type::getType(Types::TEXT);
        $table   = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->create();

        self::assertFalse($table->hasColumn('bar'));
        self::assertTrue($table->hasColumn('foo'));

        $column = $table->renameColumn('foo', 'bar');
        $column->setType($typeTxt);
        self::assertTrue($table->hasColumn('bar'), 'Should now have bar column');
        self::assertFalse($table->hasColumn('foo'), 'Should not have foo column anymore');
        self::assertCount(1, $table->getColumns());

        self::assertEquals(['bar' => 'foo'], $table->getRenamedColumns());
        $table->renameColumn('bar', 'baz');

        self::assertTrue($table->hasColumn('baz'), 'Should now have baz column');
        self::assertFalse($table->hasColumn('bar'), 'Should not have bar column anymore');
        self::assertEquals(['baz' => 'foo'], $table->getRenamedColumns());
        self::assertCount(1, $table->getColumns());
    }

    public function testRenameColumnException(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('baz')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Attempt to rename column "foo.baz" to the same name.');

        $table->renameColumn('baz', '`BaZ`');
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

        $table->renameColumn('baz', '`foo`');
        self::assertCount(1, $table->getRenamedColumns());
        $table->renameColumn('foo', 'Baz');
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

        $table->renameColumn('c1', 'c1a');

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

        $table->renameColumn('c2', 'c2a');

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

        $table->renameColumn('c1', 'c1a');

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
        $this->expectException(SchemaException::class);

        $table = Table::editor()
            ->setUnquotedName('foo')
            ->create();
        $table->getIndex('unknownIndex');
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

    public function testBuilderAddIndexWithInvalidNameThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table->addIndex(['bar'], 'invalid name %&/');
    }

    public function testBuilderAddIndexWithUnknownColumnThrowsException(): void
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

        $table->addIndex(['bar'], 'invalidName');
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

    public function testAddForeignKeyConstraintUnknownLocalColumnThrowsException(): void
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

        $table->addForeignKeyConstraint('bar', ['foo'], ['id']);
    }

    /** @throws Exception */
    public function testAddForeignKeyConstraintWithInvalidMatchType(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->expectException(ValueError::class);

        $table->addForeignKeyConstraint('bar', ['bar_id'], ['id'], ['match' => 'MAYBE']);
    }

    /** @throws Exception */
    public function testAddForeignKeyConstraintWithInvalidOnUpdateAction(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->expectException(ValueError::class);

        $table->addForeignKeyConstraint('bar', ['bar_id'], ['id'], ['onUpdate' => 'DROP']);
    }

    /** @throws Exception */
    public function testAddForeignKeyConstraintWithInvalidOnDeleteAction(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->expectException(ValueError::class);

        $table->addForeignKeyConstraint('bar', ['bar_id'], ['id'], ['onDelete' => 'DROP']);
    }

    public function testAddForeignKeyConstraintWithInvalidDeferrability(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->expectException(InvalidForeignKeyConstraintDefinition::class);

        $table->addForeignKeyConstraint('bar', ['bar_id'], ['id'], [
            'deferrable' => false,
            'deferred' => true,
        ]);
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
            ->create();

        $table->addIndex(['baz']);

        self::assertCount(1, $table->getIndexes());
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
            ->create();

        $table->addIndex(['baz']);

        $indexes = $table->getIndexes();
        self::assertCount(1, $indexes);
        $index = $indexes[0];

        $table->addUniqueIndex(['baz']);
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
                    ->setUnquotedName('idx')
                    ->setUnquotedColumnNames('foo')
                    ->setIsClustered(true)
                    ->create(),
                Index::editor()
                    ->setUnquotedName('uniq')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('bar', 'baz')
                    ->create(),
            )
            ->create();

        // Rename to custom name.
        self::assertSame($table, $table->renameIndex('idx', 'idx_new'));
        self::assertSame($table, $table->renameIndex('uniq', 'uniq_new'));

        self::assertTrue($table->hasIndex('idx_new'));
        self::assertTrue($table->hasIndex('uniq_new'));

        self::assertFalse($table->hasIndex('idx'));
        self::assertFalse($table->hasIndex('uniq'));

        self::assertEquals(
            Index::editor()
                ->setUnquotedName('idx_new')
                ->setUnquotedColumnNames('foo')
                ->setIsClustered(true)
                ->create(),
            $table->getIndex('idx_new'),
        );

        self::assertEquals(
            Index::editor()
                ->setUnquotedName('uniq_new')
                ->setUnquotedColumnNames('bar', 'baz')
                ->setType(IndexType::UNIQUE)
                ->create(),
            $table->getIndex('uniq_new'),
        );

        // Rename to auto-generated name.
        self::assertSame($table, $table->renameIndex('idx_new', null));
        self::assertSame($table, $table->renameIndex('uniq_new', null));

        self::assertTrue($table->hasIndex('IDX_D87F7E0C8C736521'));
        self::assertTrue($table->hasIndex('UNIQ_D87F7E0C76FF8CAA78240498'));

        self::assertFalse($table->hasIndex('idx_new'));
        self::assertFalse($table->hasIndex('uniq_new'));

        self::assertEquals(
            Index::editor()
                ->setUnquotedName('IDX_D87F7E0C8C736521')
                ->setUnquotedColumnNames('foo')
                ->setIsClustered(true)
                ->create(),
            $table->getIndex('IDX_D87F7E0C8C736521'),
        );

        self::assertEquals(
            Index::editor()
                ->setUnquotedName('UNIQ_D87F7E0C76FF8CAA78240498')
                ->setUnquotedColumnNames('bar', 'baz')
                ->setType(IndexType::UNIQUE)
                ->create(),
            $table->getIndex('UNIQ_D87F7E0C76FF8CAA78240498'),
        );

        // Rename to same name (changed case).
        self::assertSame($table, $table->renameIndex('IDX_D87F7E0C8C736521', 'idx_D87F7E0C8C736521'));
        self::assertSame($table, $table->renameIndex('UNIQ_D87F7E0C76FF8CAA78240498', 'uniq_D87F7E0C76FF8CAA78240498'));

        self::assertTrue($table->hasIndex('IDX_D87F7E0C8C736521'));
        self::assertTrue($table->hasIndex('UNIQ_D87F7E0C76FF8CAA78240498'));
    }

    public function testRenameNonExistingIndexToTheSameName(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->expectException(IndexDoesNotExist::class);
        $table->renameIndex('test', 'test');
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

        $table->renameIndex('idx_bar', 'idx_baz');

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

        $table->renameIndex('idx_bar', 'idx_baz');

        self::assertSame('1 = 1', $table->getIndex('idx_baz')->getPredicate());
    }

    public function testThrowsExceptionOnRenamingNonExistingIndex(): void
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

        $this->expectException(SchemaException::class);

        $table->renameIndex('foo', 'bar');
    }

    public function testThrowsExceptionOnRenamingToAlreadyExistingIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx_id')
                    ->setUnquotedColumnNames('id')
                    ->create(),
                Index::editor()
                    ->setUnquotedName('idx_foo')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->create();

        $this->expectException(SchemaException::class);

        $table->renameIndex('idx_id', 'idx_foo');
    }

    #[DataProvider('getNormalizesAssetNames')]
    public function testNormalizesColumnNames(string $assetName): void
    {
        $table = new Table('test');

        $table->addColumn($assetName, Types::INTEGER);
        $table->addIndex([$assetName], $assetName);
        $table->addForeignKeyConstraint('test', [$assetName], [$assetName], [], $assetName);

        self::assertTrue($table->hasColumn($assetName));
        self::assertTrue($table->hasColumn('foo'));

        self::assertTrue($table->hasIndex($assetName));
        self::assertTrue($table->hasIndex('foo'));

        self::assertTrue($table->hasForeignKey($assetName));
        self::assertTrue($table->hasForeignKey('foo'));

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
        $table->dropForeignKey($assetName);

        self::assertFalse($table->hasColumn($assetName));
        self::assertFalse($table->hasColumn('foo'));
        self::assertFalse($table->hasIndex($assetName));
        self::assertFalse($table->hasIndex('foo'));
        self::assertFalse($table->hasForeignKey($assetName));
        self::assertFalse($table->hasForeignKey('foo'));
    }

    /** @return mixed[][] */
    public static function getNormalizesAssetNames(): iterable
    {
        return [
            ['foo'],
            ['FOO'],
            ['`foo`'],
            ['`FOO`'],
            ['"foo"'],
            ['"FOO"'],
            ['"foo"'],
            ['"FOO"'],
        ];
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

        $table->setComment('foo');
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

    public function testDropUniqueConstraint(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedName('unique_constraint')
                    ->setUnquotedColumnNames('bar')
                    ->create(),
            )
            ->create();

        $table->dropUniqueConstraint('unique_constraint');

        self::assertFalse($table->hasUniqueConstraint('unique_constraint'));
    }

    public function testDropUniqueConstraintUnknownNameThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table->dropUniqueConstraint('unique_constraint');
    }

    public function testDropColumnWithForeignKeyConstraint(): void
    {
        $table = Table::editor()
            ->setUnquotedName('t1')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('id')
                    ->setUnquotedReferencedTableName('t2')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6559');
        $table->dropColumn('id');
    }

    public function testDropColumnWithUniqueConstraint(): void
    {
        $table = Table::editor()
            ->setUnquotedName('t')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6559');
        $table->dropColumn('id');
    }

    public function testDropColumnWithoutConstraints(): void
    {
        $table = Table::editor()
            ->setUnquotedName('t')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6559');
        $table->dropColumn('id');
    }

    public function testOverqualifiedName(): void
    {
        $this->expectException(InvalidName::class);

        new Table('warehouse.inventory.products');
    }

    /** @throws Exception */
    public function testGetUnqualifiedObjectName(): void
    {
        $table = new Table('products');
        $name  = $table->getObjectName();

        self::assertEquals(Identifier::unquoted('products'), $name->getUnqualifiedName());
        self::assertNull($name->getQualifier());
    }

    /** @throws Exception */
    public function testGetQualifiedObjectName(): void
    {
        $table = new Table('inventory.products');
        $name  = $table->getObjectName();

        self::assertEquals(Identifier::unquoted('products'), $name->getUnqualifiedName());
        self::assertEquals(Identifier::unquoted('inventory'), $name->getQualifier());
    }

    public function testAddIndexWithNonIntegerColumnLength(): void
    {
        $table = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->create();

        $this->expectException(InvalidIndexDefinition::class);
        $table->addIndex(['name'], null, [], ['lengths' => ['8']]);
    }

    public function testAddIndexWithNonPositiveColumnLength(): void
    {
        $table = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->create();

        $this->expectException(InvalidIndexDefinition::class);
        $table->addIndex(['name'], null, [], ['lengths' => [-1]]);
    }

    public function testAddIndexWithColumnLength(): void
    {
        $table = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('first_name')
                    ->setTypeName(Types::STRING)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('last_name')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->create();

        $table->addIndex(['first_name', 'last_name'], 'idx_user_name', [], ['lengths' => [16]]);

        $indexedColumns = $table->getIndex('idx_user_name')->getIndexedColumns();

        self::assertCount(2, $indexedColumns);

        self::assertEquals(UnqualifiedName::unquoted('first_name'), $indexedColumns[0]->getColumnName());
        self::assertEquals(16, $indexedColumns[0]->getLength());

        self::assertEquals(UnqualifiedName::unquoted('last_name'), $indexedColumns[1]->getColumnName());
        self::assertNull($indexedColumns[1]->getLength());
    }

    /** @param list<string> $flags */
    #[TestWith([[], IndexType::REGULAR])]
    #[TestWith([['fulltext'], IndexType::FULLTEXT])]
    #[TestWith([['spatial'], IndexType::SPATIAL])]
    public function testParseNonUniqueIndexType(array $flags, IndexType $expectedType): void
    {
        $table = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('user_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table->addIndex(['user_id'], 'idx_user_id', $flags);

        $index = $table->getIndex('idx_user_id');
        self::assertEquals($expectedType, $index->getType());
    }

    public function testAddIndexWithInvalidFlag(): void
    {
        $table = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('user_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->expectException(InvalidIndexDefinition::class);
        $table->addIndex(['user_id'], null, ['banana']);
    }

    public function testAddIndexWithInvalidOption(): void
    {
        $table = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('user_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->expectException(InvalidIndexDefinition::class);
        $table->addIndex(['user_id'], null, [], ['potato' => true]);
    }

    /** @param list<string> $flags */
    #[TestWith([['nonclustered', 'clustered']])]
    #[TestWith([['fulltext', 'spatial']])]
    public function testAddIndexWithConflictingFlags(array $flags): void
    {
        $table = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('user_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->expectException(InvalidIndexDefinition::class);
        $table->addIndex(['user_id'], null, $flags);
    }

    public function testOverwritingForeignKeyConstraint(): void
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

        $table->addForeignKeyConstraint('bar', ['id'], ['id']);

        $this->expectException(ForeignKeyAlreadyExists::class);
        $table->addForeignKeyConstraint('baz', ['id'], ['id']);
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\ForeignKeyDoesNotExist;
use Doctrine\DBAL\Schema\Exception\IndexDoesNotExist;
use Doctrine\DBAL\Schema\Exception\InvalidState;
use Doctrine\DBAL\Schema\Exception\PrimaryKeyAlreadyExists;
use Doctrine\DBAL\Schema\Exception\UniqueConstraintDoesNotExist;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_shift;

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

    public function testRenameColumnThroughEditor(): void
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

        // The result of multiple consecutive edit-rename-column-and-create operations is different from
        // Table::renameColumn(): each edit records the rename relative to the table it started from, so the renames
        // do not chain across edits.
        self::assertEquals(['baz' => 'bar'], $table->getRenamedColumns());
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

    public function testRenameColumnLoopThroughEditor(): void
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

        $table->renameColumn('c1', 'c1a');
        self::assertSame(['c1a', 'c2'], $table->getIndex('idx_c1_c2')->getColumns());
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
        self::assertSame(['c1', 'c2a'], $table->getForeignKey('fk_c1_c2')->getLocalColumns());
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
        self::assertSame(['c1a', 'c2'], $table->getUniqueConstraint('uq_c1_c2')->getColumns());
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

        $table->dropColumn('foo')->dropColumn('bar');

        self::assertFalse($table->hasColumn('foo'));
        self::assertFalse($table->hasColumn('bar'));
    }

    public function testDropColumnThroughEditor(): void
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
                new Index('the_primary', ['foo'], true, true),
                Index::editor()
                    ->setUnquotedName('bar_idx')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('bar')
                    ->create(),
            )
            ->create();

        self::assertTrue($table->hasIndex('the_primary'));
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertFalse($table->hasIndex('some_idx'));

        self::assertNotNull($table->getPrimaryKey());

        self::assertEquals(
            UnqualifiedName::unquoted('the_primary'),
            $table->getIndex('the_primary')->getObjectName(),
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

    public function testAddTwoPrimaryThrowsException(): void
    {
        $this->expectException(SchemaException::class);

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

        $indexes = [
            new Index('the_primary', ['foo'], true, true),
            new Index('other_primary', ['bar'], true, true),
        ];
        Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(...$columns)
            ->setIndexes(...$indexes)
            ->create();
    }

    public function testAddTwoIndexesWithSameNameThrowsException(): void
    {
        $this->expectException(SchemaException::class);

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
        $indexes = [
            new Index('an_idx', ['foo'], false, false),
            new Index('an_idx', ['bar'], false, false),
        ];
        Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(...$columns)
            ->setIndexes(...$indexes)
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

    public function testBuilderSetPrimaryKey(): void
    {
        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6787');

        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table->setPrimaryKey(['bar']);

        self::assertTrue($table->hasIndex('primary'));
        self::assertInstanceOf(Index::class, $table->getPrimaryKey());
        self::assertTrue($table->getIndex('primary')->isUnique());
        self::assertTrue($table->getIndex('primary')->isPrimary());
    }

    public function testSetPrimaryKeyOnANullableColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setNotNull(false)
                    ->create(),
            )
            ->create();

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6787');

        $table->setPrimaryKey(['id']);

        self::assertTrue($table->getColumn('id')->getNotnull());
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
        self::assertTrue($table->getIndex('my_idx')->isUnique());
        self::assertFalse($table->getIndex('my_idx')->isPrimary());
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
        self::assertFalse($table->getIndex('my_idx')->isUnique());
        self::assertFalse($table->getIndex('my_idx')->isPrimary());
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
                    ->setUnquotedReferencingColumnNames('foo')
                    ->setUnquotedReferencedTableName('bar')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            );

        $this->expectException(SchemaException::class);

        $editor->create();
    }

    public function testAddForeignKeyConstraint(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->addForeignKeyConstraint(
                new ForeignKeyConstraint(['id'], 'bar', ['id'], '', ['foo' => 'bar']),
            )
            ->create();

        $constraints = $table->getForeignKeys();
        self::assertCount(1, $constraints);
        $constraint = array_shift($constraints);

        self::assertInstanceOf(ForeignKeyConstraint::class, $constraint);

        self::assertTrue($constraint->hasOption('foo'));
        self::assertEquals('bar', $constraint->getOption('foo'));
    }

    public function testAddIndexWithCaseSensitiveColumnProblem(): void
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
                    ->setUnquotedName('my_idx')
                    ->setUnquotedColumnNames('ID')
                    ->create(),
            )
            ->create();

        self::assertTrue($table->hasIndex('my_idx'));
        self::assertEquals(['ID'], $table->getIndex('my_idx')->getColumns());
        self::assertTrue($table->getIndex('my_idx')->spansColumns(['id']));
    }

    public function testAddPrimaryKeyColumnsAreExplicitlySetToNotNull(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                ->setNotNull(false)
                    ->create(),
            )
            ->create();

        self::assertFalse($table->getColumn('id')->getNotnull());

        $table->setPrimaryKey(['id']);

        self::assertTrue($table->getColumn('id')->getNotnull());
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

    public function testAddIndexViaEditorGeneratesSameNamesAsMutators(): void
    {
        $columns = [
            Column::editor()
                ->setUnquotedName('a')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('b')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ];

        $viaEditor = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(...$columns)
            ->addIndex(
                Index::editor()
                    ->setUnquotedColumnNames('a', 'b'),
            )
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('a', 'b'),
            )
            ->create();

        $viaMutators = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(...$columns)
            ->create();
        $viaMutators->addIndex(['a', 'b']);
        $viaMutators->addUniqueIndex(['a', 'b']);

        self::assertEquals($viaMutators->getIndexes(), $viaEditor->getIndexes());
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
                    ->setUnquotedName('my_idx')
                    ->setUnquotedColumnNames('a'),
            )
            ->create();

        self::assertTrue($table->hasIndex('my_idx'));
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
        $index = array_shift($indexes);
        self::assertNotNull($index);

        self::assertTrue($table->hasIndex($index->getName()));
        self::assertEquals(['id'], $index->getColumns());
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
        self::assertSame(['bar'], $table->getIndex('bar_idx')->getColumns());
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
        self::assertSame(['baz', 'bar'], $table->getIndex('composite_idx')->getColumns());
        self::assertSame(['bar', 'baz', 'bloo'], $table->getIndex('full_idx')->getColumns());
        self::assertSame(['bar', 'baz'], $table->getIndex('idx_8c73652176ff8caa78240498')->getColumns());
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
        $index = array_shift($indexes);
        self::assertNotNull($index);

        $table = $table->edit()
            ->addIndex(
                Index::editor()
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('baz'),
            )
            ->create();

        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex($index->getName()));
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
        self::assertSame(['bar'], $table->getIndex('bar_idx')->getColumns());
        self::assertSame(['bar'], $table->getIndex('duplicate_idx')->getColumns());
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
        self::assertSame(['bar'], $table->getIndex('bar_idx')->getColumns());
        self::assertSame(['bar', 'baz'], $table->getIndex('fulfilling_idx')->getColumns());
    }

    public function testPrimaryKeyOverrulingUniqueIndexDoesNotDropUniqueIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('bar')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('baz')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx_unique')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('baz')
                    ->create(),
            )
            ->create();

        $table->setPrimaryKey(['baz']);

        $indexes = $table->getIndexes();

        // Table should only contain both the primary key table index and the unique one, even though it was overruled
        self::assertCount(2, $indexes);

        self::assertNotNull($table->getPrimaryKey());
        self::assertTrue($table->hasIndex('idx_unique'));
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

    public function testAddingFulfillingPrimaryKeyOverridesImplicitForeignKeyConstraintIndex(): void
    {
        $localTable = Table::editor()
            ->setUnquotedName('local')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $localTable->addForeignKeyConstraint('foreign', ['id'], ['id']);

        self::assertCount(1, $localTable->getIndexes());

        $localTable->setPrimaryKey(['id'], 'explicit_idx');

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
        self::assertEquals('`bar`', $table->getQuotedName($mysqlPlatform));
        self::assertEquals('"bar"', $table->getQuotedName($sqlitePlatform));
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

        self::assertNull($table->getPrimaryKey());

        $table->setPrimaryKey(['foo']);

        self::assertNotNull($table->getPrimaryKey());
    }

    public function testAddIndexWithQuotedColumns(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
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
            ->addIndex(
                Index::editor()
                    ->setQuotedColumnNames('foo', 'bar'),
            )
            ->create();

        self::assertTrue($table->columnsAreIndexed(['"foo"', '"bar"']));
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
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setQuotedReferencingColumnNames('foo', 'bar')
                    ->setQuotedReferencedTableName('boing')
                    ->setUnquotedReferencedColumnNames('id')
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
        self::assertEquals('`test`.`test`', $table->getQuotedName(new MySQLPlatform()));
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
            ->create();

        $table->addIndex(['id'], 'idx');

        self::assertTrue($table->hasIndex('idx'));

        $table->dropIndex('idx');
        self::assertFalse($table->hasIndex('idx'));
    }

    public function testDropIndexThroughEditor(): void
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

    public function testDropPrimaryKey(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertNotNull($table->getPrimaryKey());

        $table->dropPrimaryKey();
        self::assertNull($table->getPrimaryKey());
    }

    public function testDropPrimaryKeyConstraintThroughEditor(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertNotNull($table->getPrimaryKey());

        $table = $table->edit()
            ->dropPrimaryKeyConstraint()
            ->create();

        self::assertNull($table->getPrimaryKey());
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
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedName('pk')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $table->addIndex(['foo'], 'idx', ['flag']);
        $table->addUniqueIndex(['bar', 'baz'], 'uniq');

        // Rename to custom name.
        self::assertSame($table, $table->renameIndex('pk', 'pk_new'));
        self::assertSame($table, $table->renameIndex('idx', 'idx_new'));
        self::assertSame($table, $table->renameIndex('uniq', 'uniq_new'));

        self::assertNotNull($table->getPrimaryKey());
        self::assertTrue($table->hasIndex('pk_new'));
        self::assertTrue($table->hasIndex('idx_new'));
        self::assertTrue($table->hasIndex('uniq_new'));

        self::assertFalse($table->hasIndex('pk'));
        self::assertFalse($table->hasIndex('idx'));
        self::assertFalse($table->hasIndex('uniq'));

        self::assertEquals(new Index('pk_new', ['id'], true, true), $table->getPrimaryKey());
        self::assertEquals(new Index('pk_new', ['id'], true, true), $table->getIndex('pk_new'));
        self::assertEquals(
            new Index('idx_new', ['foo'], false, false, ['flag']),
            $table->getIndex('idx_new'),
        );
        self::assertEquals(new Index('uniq_new', ['bar', 'baz'], true), $table->getIndex('uniq_new'));

        // Rename to auto-generated name.
        self::assertSame($table, $table->renameIndex('pk_new', null));
        self::assertSame($table, $table->renameIndex('idx_new', null));
        self::assertSame($table, $table->renameIndex('uniq_new', null));

        self::assertNotNull($table->getPrimaryKey());
        self::assertTrue($table->hasIndex('primary'));
        self::assertTrue($table->hasIndex('IDX_D87F7E0C8C736521'));
        self::assertTrue($table->hasIndex('UNIQ_D87F7E0C76FF8CAA78240498'));

        self::assertFalse($table->hasIndex('pk_new'));
        self::assertFalse($table->hasIndex('idx_new'));
        self::assertFalse($table->hasIndex('uniq_new'));

        self::assertEquals(new Index('primary', ['id'], true, true), $table->getPrimaryKey());
        self::assertEquals(new Index('primary', ['id'], true, true), $table->getIndex('primary'));
        self::assertEquals(
            new Index('IDX_D87F7E0C8C736521', ['foo'], false, false, ['flag']),
            $table->getIndex('IDX_D87F7E0C8C736521'),
        );
        self::assertEquals(
            new Index('UNIQ_D87F7E0C76FF8CAA78240498', ['bar', 'baz'], true),
            $table->getIndex('UNIQ_D87F7E0C76FF8CAA78240498'),
        );

        // Rename to same name (changed case).
        self::assertSame($table, $table->renameIndex('primary', 'PRIMARY'));
        self::assertSame($table, $table->renameIndex('IDX_D87F7E0C8C736521', 'idx_D87F7E0C8C736521'));
        self::assertSame($table, $table->renameIndex('UNIQ_D87F7E0C76FF8CAA78240498', 'uniq_D87F7E0C76FF8CAA78240498'));

        self::assertNotNull($table->getPrimaryKey());
        self::assertTrue($table->hasIndex('primary'));
        self::assertTrue($table->hasIndex('IDX_D87F7E0C8C736521'));
        self::assertTrue($table->hasIndex('UNIQ_D87F7E0C76FF8CAA78240498'));
    }

    public function testRenameIndexThroughEditor(): void
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

    public function testKeepsIndexOptionsOnRenamingRegularIndex(): void
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

        $table->addIndex(['id'], 'idx_bar', [], ['where' => '1 = 1']);

        $table->renameIndex('idx_bar', 'idx_baz');

        self::assertSame(['where' => '1 = 1'], $table->getIndex('idx_baz')->getOptions());
    }

    public function testKeepsIndexOptionsOnRenamingUniqueIndex(): void
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

        $table->addUniqueIndex(['id'], 'idx_bar', ['where' => '1 = 1']);

        $table->renameIndex('idx_bar', 'idx_baz');

        self::assertSame(['where' => '1 = 1'], $table->getIndex('idx_baz')->getOptions());
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
            ->create();

        $table->addIndex(['id'], 'idx');

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
            ->create();

        $table->addIndex(['id'], 'idx_id');
        $table->addIndex(['foo'], 'idx_foo');

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
        $table->removeForeignKey($assetName);

        self::assertFalse($table->hasColumn($assetName));
        self::assertFalse($table->hasColumn('foo'));
        self::assertFalse($table->hasIndex($assetName));
        self::assertFalse($table->hasIndex('foo'));
        self::assertFalse($table->hasForeignKey($assetName));
        self::assertFalse($table->hasForeignKey('foo'));
    }

    /** @return list<array{string}> */
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
            new UniqueConstraint('', ['column1', 'column2']),
            new UniqueConstraint('', ['column3', 'column4']),
        ];

        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(...$columns)
            ->setUniqueConstraints(...$uniqueConstraints)
            ->create();

        $constraints = $table->getUniqueConstraints();

        self::assertCount(2, $constraints);

        $constraintNames = array_keys($constraints);

        self::assertSame('fk_d87f7e0c341ce00bad15b1b1', $constraintNames[0]);
        self::assertSame('fk_d87f7e0cda12812744761484', $constraintNames[1]);

        self::assertSame($uniqueConstraints[0], $constraints['fk_d87f7e0c341ce00bad15b1b1']);
        self::assertSame($uniqueConstraints[1], $constraints['fk_d87f7e0cda12812744761484']);
    }

    public function testRemoveUniqueConstraint(): void
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

        $table->removeUniqueConstraint('unique_constraint');

        self::assertFalse($table->hasUniqueConstraint('unique_constraint'));
    }

    public function testRemoveUniqueConstraintUnknownNameThrowsException(): void
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

        $table->removeUniqueConstraint('unique_constraint');
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
            ->create();

        $table->addForeignKeyConstraint('t2', ['id'], ['id']);

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

    public function testPrimaryKeyConstraintIsDerivedFromIndex(): void
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

        $table->setPrimaryKey(['id']);

        self::assertEquals(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
            $table->getPrimaryKeyConstraint(),
        );
    }

    public function testIndexIsDerivedFromPrimaryKeyConstraint(): void
    {
        $table = Table::editor()
            ->setUnquotedName('t')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertEquals(
            new Index('primary', ['id'], false, true),
            $table->getPrimaryKey(),
        );
    }

    public function testDroppingIndexDropsPrimaryKeyConstraint(): void
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

        $table->setPrimaryKey(['id']);

        self::assertNotNull($table->getPrimaryKeyConstraint());

        $table->dropPrimaryKey();

        self::assertNull($table->getPrimaryKeyConstraint());
    }

    public function testCannotAddIndexToExistingPrimaryKeyConstraint(): void
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

        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        $this->expectException(PrimaryKeyAlreadyExists::class);
        $table->setPrimaryKey(['id']);
    }

    public function testCannotAddPrimaryKeyConstrainToExistingIndex(): void
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

        $table->setPrimaryKey(['id']);

        $this->expectException(PrimaryKeyAlreadyExists::class);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
    }

    public function testInvalidPrimaryKeyIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('t')
            ->setColumns(
                Column::editor()
                    ->setQuotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6787');
        $table->setPrimaryKey(['"id']);

        self::assertNotNull($table->getPrimaryKey());

        $this->expectException(InvalidState::class);
        $table->getPrimaryKeyConstraint();
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

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7125');
        $table->addForeignKeyConstraint('baz', ['id'], ['id']);
    }

    public function testAddColumnUsesConfigurationTypeRegistry(): void
    {
        $customType = new IntegerType();
        $registry   = new TypeRegistry([Types::INTEGER => $customType]);

        $table = new Table('foo');
        $table->setTypeRegistry($registry);
        $column = $table->addColumn('id', Types::INTEGER);

        self::assertSame($customType, $column->getType());
        self::assertNotSame(Type::getType(Types::INTEGER), $column->getType());
    }

    public function testEditPreservesTypeRegistry(): void
    {
        $customType = new IntegerType();
        $registry   = new TypeRegistry([Types::INTEGER => $customType]);

        $table = new Table('foo');
        $table->setTypeRegistry($registry);
        $table->addColumn('id', Types::INTEGER);

        $editedTable = $table->edit()->create();
        $column      = $editedTable->addColumn('name', Types::INTEGER);

        self::assertSame($customType, $column->getType());
    }
}

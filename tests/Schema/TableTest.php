<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\IndexDoesNotExist;
use Doctrine\DBAL\Schema\Exception\InvalidForeignKeyConstraintDefinition;
use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\Identifier;
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
        $table =  new Table('foo', [], [], [], []);
        self::assertEquals('foo', $table->getName());
    }

    public function testColumns(): void
    {
        $type      = Type::getType(Types::INTEGER);
        $columns   = [];
        $columns[] = new Column('foo', $type);
        $columns[] = new Column('bar', $type);
        $table     = new Table('foo', $columns, [], []);

        self::assertTrue($table->hasColumn('foo'));
        self::assertTrue($table->hasColumn('bar'));
        self::assertFalse($table->hasColumn('baz'));

        self::assertSame('foo', $table->getColumn('foo')->getName());
        self::assertSame('bar', $table->getColumn('bar')->getName());

        self::assertCount(2, $table->getColumns());
    }

    public function testRenameColumn(): void
    {
        $typeStr   = Type::getType(Types::STRING);
        $typeTxt   = Type::getType(Types::TEXT);
        $columns   = [];
        $columns[] = new Column('foo', $typeStr);
        $table     = new Table('foo', $columns, [], []);

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
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Attempt to rename column "foo.baz" to the same name.');

        $table = new Table('foo');
        $table->renameColumn('baz', '`BaZ`');
    }

    public function testRenameColumnLoop(): void
    {
        $table = new Table('foo');
        $table->addColumn('baz', Types::INTEGER);
        $table->renameColumn('baz', '`foo`');
        self::assertCount(1, $table->getRenamedColumns());
        $table->renameColumn('foo', 'Baz');
        self::assertCount(1, $table->getColumns());
        self::assertCount(0, $table->getRenamedColumns());
    }

    public function testRenameColumnInIndex(): void
    {
        $table = new Table('t');
        $table->addColumn('c1', Types::INTEGER);
        $table->addColumn('c2', Types::INTEGER);
        $table->addIndex(['c1', 'c2'], 'idx_c1_c2');
        $table->renameColumn('c1', 'c1a');

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('c1a'), null),
            new IndexedColumn(UnqualifiedName::unquoted('c2'), null),
        ], $table->getIndex('idx_c1_c2')->getIndexedColumns());
    }

    public function testRenameColumnInForeignKeyConstraint(): void
    {
        $table = new Table('t1');
        $table->addColumn('c1', Types::INTEGER);
        $table->addColumn('c2', Types::INTEGER);
        $table->addForeignKeyConstraint('t2', ['c1', 'c2'], ['c1', 'c2'], [], 'fk_c1_c2');
        $table->renameColumn('c2', 'c2a');

        self::assertEquals([
            UnqualifiedName::unquoted('c1'),
            UnqualifiedName::unquoted('c2a'),
        ], $table->getForeignKey('fk_c1_c2')->getReferencingColumnNames());
    }

    public function testRenameColumnInUniqueConstraint(): void
    {
        $table = new Table('t');
        $table->addColumn('c1', Types::INTEGER);
        $table->addColumn('c2', Types::INTEGER);
        $table->addUniqueConstraint(['c1', 'c2'], 'uq_c1_c2');
        $table->renameColumn('c1', 'c1a');

        self::assertEquals([
            UnqualifiedName::unquoted('c1a'),
            UnqualifiedName::unquoted('c2'),
        ], $table->getUniqueConstraint('uq_c1_c2')->getColumnNames());
    }

    public function testColumnsCaseInsensitive(): void
    {
        $table  = new Table('foo');
        $column = $table->addColumn('Foo', Types::INTEGER);

        self::assertTrue($table->hasColumn('Foo'));
        self::assertTrue($table->hasColumn('foo'));
        self::assertTrue($table->hasColumn('FOO'));

        self::assertSame($column, $table->getColumn('Foo'));
        self::assertSame($column, $table->getColumn('foo'));
        self::assertSame($column, $table->getColumn('FOO'));
    }

    public function testCreateColumn(): void
    {
        $type = Type::getType(Types::INTEGER);

        $table = new Table('foo');

        self::assertFalse($table->hasColumn('bar'));
        $table->addColumn('bar', Types::INTEGER);
        self::assertTrue($table->hasColumn('bar'));
        self::assertSame($type, $table->getColumn('bar')->getType());
    }

    public function testDropColumn(): void
    {
        $type      = Type::getType(Types::INTEGER);
        $columns   = [];
        $columns[] = new Column('foo', $type);
        $columns[] = new Column('bar', $type);
        $table     = new Table('foo', $columns, [], []);

        self::assertTrue($table->hasColumn('foo'));
        self::assertTrue($table->hasColumn('bar'));

        $table->dropColumn('foo')->dropColumn('bar');

        self::assertFalse($table->hasColumn('foo'));
        self::assertFalse($table->hasColumn('bar'));
    }

    public function testGetUnknownColumnThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $table = new Table('foo', [], [], []);
        $table->getColumn('unknown');
    }

    public function testAddColumnTwiceThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $type      = Type::getType(Types::INTEGER);
        $columns   = [];
        $columns[] = new Column('foo', $type);
        $columns[] = new Column('foo', $type);
        new Table('foo', $columns, [], []);
    }

    public function testCreateIndex(): void
    {
        $type    = Type::getType(Types::INTEGER);
        $columns = [new Column('foo', $type), new Column('bar', $type), new Column('baz', $type)];
        $table   = new Table('foo', $columns);

        $table->addIndex(['foo', 'bar'], 'foo_foo_bar_idx');
        $table->addUniqueIndex(['bar', 'baz'], 'foo_bar_baz_uniq');

        self::assertTrue($table->hasIndex('foo_foo_bar_idx'));
        self::assertTrue($table->hasIndex('foo_bar_baz_uniq'));
    }

    public function testIndexCaseInsensitive(): void
    {
        $type    = Type::getType(Types::INTEGER);
        $columns = [
            new Column('foo', $type),
            new Column('bar', $type),
            new Column('baz', $type),
        ];
        $table   = new Table('foo', $columns);

        $table->addIndex(['foo', 'bar', 'baz'], 'Foo_Idx');

        self::assertTrue($table->hasIndex('foo_idx'));
        self::assertTrue($table->hasIndex('Foo_Idx'));
        self::assertTrue($table->hasIndex('FOO_IDX'));
    }

    public function testAddIndexes(): void
    {
        $type    = Type::getType(Types::INTEGER);
        $columns = [
            new Column('foo', $type),
            new Column('bar', $type),
        ];
        $indexes = [
            Index::editor()
                ->setName(UnqualifiedName::unquoted('foo_idx'))
                ->setColumnNames(UnqualifiedName::unquoted('foo'))
                ->create(),
            Index::editor()
                ->setName(UnqualifiedName::unquoted('bar_idx'))
                ->setColumnNames(UnqualifiedName::unquoted('bar'))
                ->create(),
        ];

        $table = new Table('foo', $columns, $indexes);

        self::assertTrue($table->hasIndex('foo_idx'));
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertFalse($table->hasIndex('some_idx'));

        self::assertSame('foo_idx', $table->getIndex('foo_idx')->getName());
        self::assertSame('bar_idx', $table->getIndex('bar_idx')->getName());
    }

    public function testGetUnknownIndexThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $table = new Table('foo', [], [], [], []);
        $table->getIndex('unknownIndex');
    }

    public function testAddTwoIndexesWithSameNameThrowsException(): void
    {
        $index1 = Index::editor()
            ->setName(UnqualifiedName::unquoted('idx'))
            ->setColumnNames(UnqualifiedName::unquoted('foo'))
            ->create();

        $index2 = $index1->edit()
            ->setColumnNames(UnqualifiedName::unquoted('bar'))
            ->create();

        $type    = Type::getType(Types::INTEGER);
        $columns = [new Column('foo', $type), new Column('bar', $type)];
        $indexes = [$index1, $index2];

        $this->expectException(SchemaException::class);
        new Table('foo', $columns, $indexes);
    }

    public function testOptions(): void
    {
        $table = new Table('foo', [], [], [], [], ['foo' => 'bar']);

        self::assertTrue($table->hasOption('foo'));
        self::assertEquals('bar', $table->getOption('foo'));
    }

    public function testBuilderAddUniqueIndex(): void
    {
        $table = new Table('foo');

        $table->addColumn('bar', Types::INTEGER);
        $table->addUniqueIndex(['bar'], 'my_idx');

        self::assertTrue($table->hasIndex('my_idx'));
        self::assertSame(IndexType::UNIQUE, $table->getIndex('my_idx')->getType());
    }

    public function testBuilderAddIndex(): void
    {
        $table = new Table('foo');

        $table->addColumn('bar', Types::INTEGER);
        $table->addIndex(['bar'], 'my_idx');

        self::assertTrue($table->hasIndex('my_idx'));
        self::assertSame(IndexType::REGULAR, $table->getIndex('my_idx')->getType());
    }

    public function testBuilderAddIndexWithInvalidNameThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $table = new Table('foo');
        $table->addColumn('bar', Types::INTEGER);
        $table->addIndex(['bar'], 'invalid name %&/');
    }

    public function testBuilderAddIndexWithUnknownColumnThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $table = new Table('foo');
        $table->addIndex(['bar'], 'invalidName');
    }

    public function testBuilderOptions(): void
    {
        $table = new Table('foo');
        $table->addOption('foo', 'bar');
        self::assertTrue($table->hasOption('foo'));
        self::assertEquals('bar', $table->getOption('foo'));
    }

    public function testAddForeignKeyConstraintUnknownLocalColumnThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $table = new Table('foo');
        $table->addColumn('id', Types::INTEGER);

        $foreignTable = new Table('bar');
        $foreignTable->addColumn('id', Types::INTEGER);

        $table->addForeignKeyConstraint($foreignTable->getName(), ['foo'], ['id']);
    }

    /** @throws Exception */
    public function testAddForeignKeyConstraintWithInvalidMatchType(): void
    {
        $table = new Table('foo');
        $table->addColumn('bar_id', Types::INTEGER);

        $this->expectException(ValueError::class);

        $table->addForeignKeyConstraint('bar', ['bar_id'], ['id'], ['match' => 'MAYBE']);
    }

    /** @throws Exception */
    public function testAddForeignKeyConstraintWithInvalidOnUpdateAction(): void
    {
        $table = new Table('foo');
        $table->addColumn('bar_id', Types::INTEGER);

        $this->expectException(ValueError::class);

        $table->addForeignKeyConstraint('bar', ['bar_id'], ['id'], ['onUpdate' => 'DROP']);
    }

    /** @throws Exception */
    public function testAddForeignKeyConstraintWithInvalidOnDeleteAction(): void
    {
        $table = new Table('foo');
        $table->addColumn('bar_id', Types::INTEGER);

        $this->expectException(ValueError::class);

        $table->addForeignKeyConstraint('bar', ['bar_id'], ['id'], ['onDelete' => 'DROP']);
    }

    public function testAddForeignKeyConstraintWithInvalidDeferrability(): void
    {
        $table = new Table('foo');
        $table->addColumn('bar_id', Types::INTEGER);

        $this->expectException(InvalidForeignKeyConstraintDefinition::class);

        $table->addForeignKeyConstraint('bar', ['bar_id'], ['id'], [
            'deferrable' => false,
            'deferred' => true,
        ]);
    }

    public function testAllowImplicitSchemaTableInAutogeneratedIndexNames(): void
    {
        $table = new Table('foo.bar');
        $table->addColumn('baz', Types::INTEGER, []);
        $table->addIndex(['baz']);

        self::assertCount(1, $table->getIndexes());
    }

    public function testAddForeignKeyIndexImplicitly(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', Types::INTEGER);

        $foreignTable = new Table('bar');
        $foreignTable->addColumn('id', Types::INTEGER);

        $table->addForeignKeyConstraint($foreignTable->getName(), ['id'], ['id'], ['foo' => 'bar']);

        $indexes = $table->getIndexes();
        self::assertCount(1, $indexes);
        $index = array_shift($indexes);
        self::assertNotNull($index);

        self::assertTrue($table->hasIndex($index->getName()));

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('id'), null),
        ], $index->getIndexedColumns());
    }

    public function testAddForeignKeyDoesNotCreateDuplicateIndex(): void
    {
        $table = new Table('foo');
        $table->addColumn('bar', Types::INTEGER);
        $table->addIndex(['bar'], 'bar_idx');

        $foreignTable = new Table('bar');
        $foreignTable->addColumn('foo', Types::INTEGER);

        $table->addForeignKeyConstraint($foreignTable->getName(), ['bar'], ['foo']);

        self::assertCount(1, $table->getIndexes());
        self::assertTrue($table->hasIndex('bar_idx'));

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('bar'), null),
        ], $table->getIndex('bar_idx')->getIndexedColumns());
    }

    public function testAddForeignKeyAddsImplicitIndexIfIndexColumnsDoNotSpan(): void
    {
        $table = new Table('foo');
        $table->addColumn('bar', Types::INTEGER);
        $table->addColumn('baz', Types::STRING);
        $table->addColumn('bloo', Types::STRING);
        $table->addIndex(['baz', 'bar'], 'composite_idx');
        $table->addIndex(['bar', 'baz', 'bloo'], 'full_idx');

        $foreignTable = new Table('bar');
        $foreignTable->addColumn('foo', Types::INTEGER);
        $foreignTable->addColumn('baz', Types::STRING);

        $table->addForeignKeyConstraint($foreignTable->getName(), ['bar', 'baz'], ['foo', 'baz']);

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
        $table = new Table('bar');
        $table->addColumn('baz', Types::INTEGER, []);
        $table->addIndex(['baz']);

        $indexes = $table->getIndexes();
        self::assertCount(1, $indexes);
        $index = array_shift($indexes);
        self::assertNotNull($index);

        $table->addUniqueIndex(['baz']);
        self::assertCount(2, $table->getIndexes());
        self::assertTrue($table->hasIndex($index->getName()));
    }

    public function testAllowsAddingDuplicateIndexesBasedOnColumns(): void
    {
        $table = new Table('foo');
        $table->addColumn('bar', Types::INTEGER);
        $table->addIndex(['bar'], 'bar_idx');
        $table->addIndex(['bar'], 'duplicate_idx');

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
        $table = new Table('foo');
        $table->addColumn('bar', Types::INTEGER);
        $table->addColumn('baz', Types::STRING);
        $table->addIndex(['bar'], 'bar_idx');
        $table->addIndex(['bar', 'baz'], 'fulfilling_idx');

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
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('id', Types::INTEGER);

        $localTable = new Table('local');
        $localTable->addColumn('id', Types::INTEGER);
        $localTable->addForeignKeyConstraint($foreignTable->getName(), ['id'], ['id']);

        self::assertCount(1, $localTable->getIndexes());

        $localTable->addIndex(['id'], 'explicit_idx');

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('explicit_idx'));
    }

    public function testAddingFulfillingUniqueIndexOverridesImplicitForeignKeyConstraintIndex(): void
    {
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('id', Types::INTEGER);

        $localTable = new Table('local');
        $localTable->addColumn('id', Types::INTEGER);
        $localTable->addForeignKeyConstraint($foreignTable->getName(), ['id'], ['id']);

        self::assertCount(1, $localTable->getIndexes());

        $localTable->addUniqueIndex(['id'], 'explicit_idx');

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('explicit_idx'));
    }

    public function testAddingFulfillingExplicitIndexOverridingImplicitForeignKeyConstraintIndexWithSameName(): void
    {
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('id', Types::INTEGER);

        $localTable = new Table('local');
        $localTable->addColumn('id', Types::INTEGER);
        $localTable->addForeignKeyConstraint($foreignTable->getName(), ['id'], ['id']);

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('IDX_8BD688E8BF396750'));

        $implicitIndex = $localTable->getIndex('IDX_8BD688E8BF396750');

        $localTable->addIndex(['id'], 'IDX_8BD688E8BF396750');

        self::assertCount(1, $localTable->getIndexes());
        self::assertTrue($localTable->hasIndex('IDX_8BD688E8BF396750'));
        self::assertNotSame($implicitIndex, $localTable->getIndex('IDX_8BD688E8BF396750'));
    }

    public function testQuotedTableName(): void
    {
        $table = new Table('`bar`');

        $mysqlPlatform  = new MySQLPlatform();
        $sqlitePlatform = new SQLitePlatform();

        self::assertEquals('bar', $table->getName());
        self::assertEquals('`bar`', $table->getObjectName()->toSQL($mysqlPlatform));
        self::assertEquals('"bar"', $table->getObjectName()->toSQL($sqlitePlatform));
    }

    public function testTableHasPrimaryKey(): void
    {
        $table = new Table('test');
        $table->addColumn('foo', Types::INTEGER);

        self::assertNull($table->getPrimaryKeyConstraint());

        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );

        self::assertNotNull($table->getPrimaryKeyConstraint());

        $table->dropPrimaryKey();

        self::assertNull($table->getPrimaryKeyConstraint());
    }

    public function testAddForeignKeyWithQuotedColumnsAndTable(): void
    {
        $table = new Table('test');
        $table->addColumn('"foo"', Types::INTEGER);
        $table->addColumn('bar', Types::INTEGER);
        $table->addForeignKeyConstraint('"boing"', ['"foo"', '"bar"'], ['id1', 'id2']);

        self::assertCount(1, $table->getForeignKeys());
    }

    public function testQuoteSchemaPrefixed(): void
    {
        $table = new Table('`test`.`test`');
        self::assertEquals('test.test', $table->getName());
        self::assertEquals('`test`.`test`', $table->getObjectName()->toSQL(new MySQLPlatform()));
    }

    public function testDropIndex(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER);
        $table->addIndex(['id'], 'idx');

        self::assertTrue($table->hasIndex('idx'));

        $table->dropIndex('idx');
        self::assertFalse($table->hasIndex('idx'));
    }

    public function testRenameIndex(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('foo', Types::INTEGER);
        $table->addColumn('bar', Types::INTEGER);
        $table->addColumn('baz', Types::INTEGER);
        $table->addIndex(['foo'], 'idx', ['clustered']);
        $table->addUniqueIndex(['bar', 'baz'], 'uniq');

        // Rename to custom name.
        self::assertSame($table, $table->renameIndex('idx', 'idx_new'));
        self::assertSame($table, $table->renameIndex('uniq', 'uniq_new'));

        self::assertTrue($table->hasIndex('idx_new'));
        self::assertTrue($table->hasIndex('uniq_new'));

        self::assertFalse($table->hasIndex('idx'));
        self::assertFalse($table->hasIndex('uniq'));

        self::assertEquals(
            Index::editor()
                ->setName(UnqualifiedName::unquoted('idx_new'))
                ->setColumnNames(UnqualifiedName::unquoted('foo'))
                ->setIsClustered(true)
                ->create(),
            $table->getIndex('idx_new'),
        );

        self::assertEquals(
            Index::editor()
                ->setName(UnqualifiedName::unquoted('uniq_new'))
                ->setColumnNames(
                    UnqualifiedName::unquoted('bar'),
                    UnqualifiedName::unquoted('baz'),
                )
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
                ->setName(UnqualifiedName::unquoted('IDX_D87F7E0C8C736521'))
                ->setColumnNames(UnqualifiedName::unquoted('foo'))
                ->setIsClustered(true)
                ->create(),
            $table->getIndex('IDX_D87F7E0C8C736521'),
        );

        self::assertEquals(
            Index::editor()
                ->setName(UnqualifiedName::unquoted('UNIQ_D87F7E0C76FF8CAA78240498'))
                ->setColumnNames(
                    UnqualifiedName::unquoted('bar'),
                    UnqualifiedName::unquoted('baz'),
                )
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
        $table = new Table('test');

        $this->expectException(IndexDoesNotExist::class);
        $table->renameIndex('test', 'test');
    }

    public function testKeepsPredicateOnRenamingRegularIndex(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', Types::INTEGER);
        $table->addIndex(['id'], 'idx_bar', [], ['where' => '1 = 1']);

        $table->renameIndex('idx_bar', 'idx_baz');

        self::assertSame('1 = 1', $table->getIndex('idx_baz')->getPredicate());
    }

    public function testKeepsPredicateOnRenamingUniqueIndex(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', Types::INTEGER);
        $table->addUniqueIndex(['id'], 'idx_bar', ['where' => '1 = 1']);

        $table->renameIndex('idx_bar', 'idx_baz');

        self::assertSame('1 = 1', $table->getIndex('idx_baz')->getPredicate());
    }

    public function testThrowsExceptionOnRenamingNonExistingIndex(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER);
        $table->addIndex(['id'], 'idx');

        $this->expectException(SchemaException::class);

        $table->renameIndex('foo', 'bar');
    }

    public function testThrowsExceptionOnRenamingToAlreadyExistingIndex(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('foo', Types::INTEGER);
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
        $table = new Table('bar');
        self::assertNull($table->getComment());

        $table->setComment('foo');
        self::assertEquals('foo', $table->getComment());
    }

    public function testUniqueConstraintWithEmptyName(): void
    {
        $column1 = new Column('column1', Type::getType(Types::STRING));
        $column2 = new Column('column2', Type::getType(Types::STRING));
        $column3 = new Column('column3', Type::getType(Types::STRING));
        $column4 = new Column('column4', Type::getType(Types::STRING));

        $columns = [$column1, $column2, $column3, $column4];

        $uniqueConstraints = [
            UniqueConstraint::editor()
                ->setColumnNames($column1->getObjectName(), $column2->getObjectName())
                ->create(),
            UniqueConstraint::editor()
                ->setColumnNames($column3->getObjectName(), $column4->getObjectName())
                ->create(),
        ];

        $table = new Table('test', $columns, [], $uniqueConstraints);

        $constraints = $table->getUniqueConstraints();

        self::assertCount(2, $constraints);

        $constraintNames = array_keys($constraints);

        self::assertSame('fk_d87f7e0c341ce00bad15b1b1', $constraintNames[0]);
        self::assertSame('fk_d87f7e0cda12812744761484', $constraintNames[1]);

        self::assertSame($uniqueConstraints[0], $constraints['fk_d87f7e0c341ce00bad15b1b1']);
        self::assertSame($uniqueConstraints[1], $constraints['fk_d87f7e0cda12812744761484']);
    }

    public function testDropUniqueConstraint(): void
    {
        $table = new Table('foo');
        $table->addColumn('bar', Types::INTEGER);
        $table->addUniqueConstraint(['bar'], 'unique_constraint');

        $table->dropUniqueConstraint('unique_constraint');

        self::assertFalse($table->hasUniqueConstraint('unique_constraint'));
    }

    public function testDropUniqueConstraintUnknownNameThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $table = new Table('foo');
        $table->addColumn('bar', Types::INTEGER);

        $table->dropUniqueConstraint('unique_constraint');
    }

    public function testDropColumnWithForeignKeyConstraint(): void
    {
        $table = new Table('t1');
        $table->addColumn('id', Types::INTEGER);
        $table->addForeignKeyConstraint('t2', ['id'], ['id']);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6559');
        $table->dropColumn('id');
    }

    public function testDropColumnWithUniqueConstraint(): void
    {
        $table = new Table('t');
        $table->addColumn('id', Types::INTEGER);
        $table->addUniqueConstraint(['id']);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6559');
        $table->dropColumn('id');
    }

    public function testDropColumnWithoutConstraints(): void
    {
        $table = new Table('t');
        $table->addColumn('id', Types::INTEGER);

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
        $table = new Table('users');
        $table->addColumn('name', Types::STRING);

        $this->expectException(InvalidIndexDefinition::class);
        $table->addIndex(['name'], null, [], ['lengths' => ['8']]);
    }

    public function testAddIndexWithNonPositiveColumnLength(): void
    {
        $table = new Table('users');
        $table->addColumn('name', Types::STRING);

        $this->expectException(InvalidIndexDefinition::class);
        $table->addIndex(['name'], null, [], ['lengths' => [-1]]);
    }

    public function testAddIndexWithColumnLength(): void
    {
        $table = new Table('users');
        $table->addColumn('first_name', Types::STRING);
        $table->addColumn('last_name', Types::STRING);
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
        $table = new Table('users');
        $table->addColumn('user_id', Types::INTEGER);
        $table->addIndex(['user_id'], 'idx_user_id', $flags);

        $index = $table->getIndex('idx_user_id');
        self::assertEquals($expectedType, $index->getType());
    }

    public function testAddIndexWithInvalidFlag(): void
    {
        $table = new Table('users');
        $table->addColumn('user_id', Types::INTEGER);

        $this->expectException(InvalidIndexDefinition::class);
        $table->addIndex(['user_id'], null, ['banana']);
    }

    public function testAddIndexWithInvalidOption(): void
    {
        $table = new Table('users');
        $table->addColumn('user_id', Types::INTEGER);

        $this->expectException(InvalidIndexDefinition::class);
        $table->addIndex(['user_id'], null, [], ['potato' => true]);
    }

    /** @param list<string> $flags */
    #[TestWith([['nonclustered', 'clustered']])]
    #[TestWith([['fulltext', 'spatial']])]
    public function testAddIndexWithConflictingFlags(array $flags): void
    {
        $table = new Table('users');
        $table->addColumn('user_id', Types::INTEGER);

        $this->expectException(InvalidIndexDefinition::class);
        $table->addIndex(['user_id'], null, $flags);
    }
}

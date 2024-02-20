<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_shift;

class TableTest extends TestCase
{
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
            new Index('the_primary', ['foo'], true, true),
            new Index('bar_idx', ['bar'], false, false),
        ];
        $table   = new Table('foo', $columns, $indexes, []);

        self::assertTrue($table->hasIndex('the_primary'));
        self::assertTrue($table->hasIndex('bar_idx'));
        self::assertFalse($table->hasIndex('some_idx'));

        self::assertNotNull($table->getPrimaryKey());
        self::assertSame('the_primary', $table->getIndex('the_primary')->getName());
        self::assertSame('bar_idx', $table->getIndex('bar_idx')->getName());
    }

    public function testGetUnknownIndexThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $table = new Table('foo', [], [], [], []);
        $table->getIndex('unknownIndex');
    }

    public function testAddTwoPrimaryThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $type    = Type::getType(Types::INTEGER);
        $columns = [new Column('foo', $type), new Column('bar', $type)];
        $indexes = [
            new Index('the_primary', ['foo'], true, true),
            new Index('other_primary', ['bar'], true, true),
        ];
        new Table('foo', $columns, $indexes, [], []);
    }

    public function testAddTwoIndexesWithSameNameThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $type    = Type::getType(Types::INTEGER);
        $columns = [new Column('foo', $type), new Column('bar', $type)];
        $indexes = [
            new Index('an_idx', ['foo'], false, false),
            new Index('an_idx', ['bar'], false, false),
        ];
        new Table('foo', $columns, $indexes, [], []);
    }

    public function testConstraints(): void
    {
        $constraint = new ForeignKeyConstraint([], 'foo', []);

        $tableA      = new Table('foo', [], [], [], [$constraint]);
        $constraints = $tableA->getForeignKeys();

        self::assertCount(1, $constraints);

        $constraintNames = array_keys($constraints);

        self::assertSame('fk_8c736521', $constraintNames[0]);
        self::assertSame($constraint, $constraints['fk_8c736521']);
    }

    public function testOptions(): void
    {
        $table = new Table('foo', [], [], [], [], ['foo' => 'bar']);

        self::assertTrue($table->hasOption('foo'));
        self::assertEquals('bar', $table->getOption('foo'));
    }

    public function testBuilderSetPrimaryKey(): void
    {
        $table = new Table('foo');

        $table->addColumn('bar', Types::INTEGER);
        $table->setPrimaryKey(['bar']);

        self::assertTrue($table->hasIndex('primary'));
        self::assertInstanceOf(Index::class, $table->getPrimaryKey());
        self::assertTrue($table->getIndex('primary')->isUnique());
        self::assertTrue($table->getIndex('primary')->isPrimary());
    }

    public function testBuilderAddUniqueIndex(): void
    {
        $table = new Table('foo');

        $table->addColumn('bar', Types::INTEGER);
        $table->addUniqueIndex(['bar'], 'my_idx');

        self::assertTrue($table->hasIndex('my_idx'));
        self::assertTrue($table->getIndex('my_idx')->isUnique());
        self::assertFalse($table->getIndex('my_idx')->isPrimary());
    }

    public function testBuilderAddIndex(): void
    {
        $table = new Table('foo');

        $table->addColumn('bar', Types::INTEGER);
        $table->addIndex(['bar'], 'my_idx');

        self::assertTrue($table->hasIndex('my_idx'));
        self::assertFalse($table->getIndex('my_idx')->isUnique());
        self::assertFalse($table->getIndex('my_idx')->isPrimary());
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

    public function testAddForeignKeyConstraint(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', Types::INTEGER);

        $foreignTable = new Table('bar');
        $foreignTable->addColumn('id', Types::INTEGER);

        $table->addForeignKeyConstraint($foreignTable->getName(), ['id'], ['id'], ['foo' => 'bar']);

        $constraints = $table->getForeignKeys();
        self::assertCount(1, $constraints);
        $constraint = array_shift($constraints);

        self::assertInstanceOf(ForeignKeyConstraint::class, $constraint);

        self::assertTrue($constraint->hasOption('foo'));
        self::assertEquals('bar', $constraint->getOption('foo'));
    }

    public function testAddIndexWithCaseSensitiveColumnProblem(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', Types::INTEGER);

        $table->addIndex(['ID'], 'my_idx');

        self::assertTrue($table->hasIndex('my_idx'));
        self::assertEquals(['ID'], $table->getIndex('my_idx')->getColumns());
        self::assertTrue($table->getIndex('my_idx')->spansColumns(['id']));
    }

    public function testAddPrimaryKeyColumnsAreExplicitlySetToNotNull(): void
    {
        $table  = new Table('foo');
        $column = $table->addColumn('id', Types::INTEGER, ['notnull' => false]);

        self::assertFalse($column->getNotnull());

        $table->setPrimaryKey(['id']);

        self::assertTrue($column->getNotnull());
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
        self::assertEquals(['id'], $index->getColumns());
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
        self::assertSame(['bar'], $table->getIndex('bar_idx')->getColumns());
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
        self::assertSame(['baz', 'bar'], $table->getIndex('composite_idx')->getColumns());
        self::assertSame(['bar', 'baz', 'bloo'], $table->getIndex('full_idx')->getColumns());
        self::assertSame(['bar', 'baz'], $table->getIndex('idx_8c73652176ff8caa78240498')->getColumns());
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
        self::assertSame(['bar'], $table->getIndex('bar_idx')->getColumns());
        self::assertSame(['bar'], $table->getIndex('duplicate_idx')->getColumns());
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
        self::assertSame(['bar'], $table->getIndex('bar_idx')->getColumns());
        self::assertSame(['bar', 'baz'], $table->getIndex('fulfilling_idx')->getColumns());
    }

    public function testPrimaryKeyOverrulingUniqueIndexDoesNotDropUniqueIndex(): void
    {
        $table = new Table('bar');
        $table->addColumn('baz', Types::INTEGER, []);
        $table->addUniqueIndex(['baz'], 'idx_unique');

        $table->setPrimaryKey(['baz']);

        $indexes = $table->getIndexes();

        // Table should only contain both the primary key table index and the unique one, even though it was overruled
        self::assertCount(2, $indexes);

        self::assertNotNull($table->getPrimaryKey());
        self::assertTrue($table->hasIndex('idx_unique'));
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

    public function testAddingFulfillingPrimaryKeyOverridesImplicitForeignKeyConstraintIndex(): void
    {
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('id', Types::INTEGER);

        $localTable = new Table('local');
        $localTable->addColumn('id', Types::INTEGER);
        $localTable->addForeignKeyConstraint($foreignTable->getName(), ['id'], ['id']);

        self::assertCount(1, $localTable->getIndexes());

        $localTable->setPrimaryKey(['id'], 'explicit_idx');

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
        self::assertEquals('`bar`', $table->getQuotedName($mysqlPlatform));
        self::assertEquals('"bar"', $table->getQuotedName($sqlitePlatform));
    }

    public function testTableHasPrimaryKey(): void
    {
        $table = new Table('test');

        self::assertNull($table->getPrimaryKey());

        $table->addColumn('foo', Types::INTEGER);
        $table->setPrimaryKey(['foo']);

        self::assertNotNull($table->getPrimaryKey());
    }

    public function testAddIndexWithQuotedColumns(): void
    {
        $table = new Table('test');
        $table->addColumn('"foo"', Types::INTEGER);
        $table->addColumn('bar', Types::INTEGER);
        $table->addIndex(['"foo"', '"bar"']);

        self::assertTrue($table->columnsAreIndexed(['"foo"', '"bar"']));
    }

    public function testAddForeignKeyWithQuotedColumnsAndTable(): void
    {
        $table = new Table('test');
        $table->addColumn('"foo"', Types::INTEGER);
        $table->addColumn('bar', Types::INTEGER);
        $table->addForeignKeyConstraint('"boing"', ['"foo"', '"bar"'], ['id']);

        self::assertCount(1, $table->getForeignKeys());
    }

    public function testQuoteSchemaPrefixed(): void
    {
        $table = new Table('`test`.`test`');
        self::assertEquals('test.test', $table->getName());
        self::assertEquals('`test`.`test`', $table->getQuotedName(new MySQLPlatform()));
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

    public function testDropPrimaryKey(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        self::assertNotNull($table->getPrimaryKey());

        $table->dropPrimaryKey();
        self::assertNull($table->getPrimaryKey());
    }

    public function testRenameIndex(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('foo', Types::INTEGER);
        $table->addColumn('bar', Types::INTEGER);
        $table->addColumn('baz', Types::INTEGER);
        $table->setPrimaryKey(['id'], 'pk');
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

    public function testKeepsIndexOptionsOnRenamingRegularIndex(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', Types::INTEGER);
        $table->addIndex(['id'], 'idx_bar', [], ['where' => '1 = 1']);

        $table->renameIndex('idx_bar', 'idx_baz');

        self::assertSame(['where' => '1 = 1'], $table->getIndex('idx_baz')->getOptions());
    }

    public function testKeepsIndexOptionsOnRenamingUniqueIndex(): void
    {
        $table = new Table('foo');
        $table->addColumn('id', Types::INTEGER);
        $table->addUniqueIndex(['id'], 'idx_bar', ['where' => '1 = 1']);

        $table->renameIndex('idx_bar', 'idx_baz');

        self::assertSame(['where' => '1 = 1'], $table->getIndex('idx_baz')->getOptions());
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
        $table->removeForeignKey($assetName);

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
        $columns = [
            new Column('column1', Type::getType(Types::STRING)),
            new Column('column2', Type::getType(Types::STRING)),
            new Column('column3', Type::getType(Types::STRING)),
            new Column('column4', Type::getType(Types::STRING)),
        ];

        $uniqueConstraints = [
            new UniqueConstraint('', ['column1', 'column2']),
            new UniqueConstraint('', ['column3', 'column4']),
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

    public function testRemoveUniqueConstraint(): void
    {
        $table = new Table('foo');
        $table->addColumn('bar', Types::INTEGER);
        $table->addUniqueConstraint(['bar'], 'unique_constraint');

        $table->removeUniqueConstraint('unique_constraint');

        self::assertFalse($table->hasUniqueConstraint('unique_constraint'));
    }

    public function testRemoveUniqueConstraintUnknownNameThrowsException(): void
    {
        $this->expectException(SchemaException::class);

        $table = new Table('foo');
        $table->addColumn('bar', Types::INTEGER);

        $table->removeUniqueConstraint('unique_constraint');
    }
}

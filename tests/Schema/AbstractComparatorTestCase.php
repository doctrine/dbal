<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\NamedObject;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Tests\Functional\Platform\RenameColumnTest;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function current;

abstract class AbstractComparatorTestCase extends TestCase
{
    use VerifyDeprecations;

    private Comparator $comparator;

    abstract protected function createComparator(ComparatorConfig $config): Comparator;

    protected function setUp(): void
    {
        $this->comparator = $this->createComparator(new ComparatorConfig());
    }

    public function testCompareSame1(): void
    {
        $schema1 = new Schema([
            Table::editor()
                ->setUnquotedName('bugdb')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('integercolumn1')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                )
                ->create(),
        ]);
        $schema2 = new Schema([
            Table::editor()
                ->setUnquotedName('bugdb')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('integercolumn1')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                )
                ->create(),
        ]);

        self::assertEquals(
            new SchemaDiff([], [], [], [], [], [], [], []),
            $this->comparator->compareSchemas($schema1, $schema2),
        );
    }

    public function testCompareSame2(): void
    {
        $schema1 = new Schema([
            Table::editor()
                ->setUnquotedName('bugdb')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('integercolumn1')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('integercolumn2')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                )
                ->create(),
        ]);
        $schema2 = new Schema([
            Table::editor()
                ->setUnquotedName('bugdb')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('integercolumn2')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('integercolumn1')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                )
                ->create(),
        ]);

        self::assertEquals(
            new SchemaDiff([], [], [], [], [], [], [], []),
            $this->comparator->compareSchemas($schema1, $schema2),
        );
    }

    public function testCompareMissingTable(): void
    {
        $schemaConfig = new SchemaConfig();

        $table = Table::editor()
            ->setUnquotedName('bugdb')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('integercolumn1')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setConfiguration($schemaConfig->toTableConfiguration())
            ->create();

        $schema1 = new Schema([$table], [], $schemaConfig);
        $schema2 = new Schema([], [], $schemaConfig);

        self::assertEquals(
            new SchemaDiff([], [], [], [], [$table], [], [], []),
            $this->comparator->compareSchemas($schema1, $schema2),
        );
    }

    public function testCompareNewTable(): void
    {
        $schemaConfig = new SchemaConfig();

        $table = Table::editor()
            ->setUnquotedName('bugdb')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('integercolumn1')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setConfiguration($schemaConfig->toTableConfiguration())
            ->create();

        $schema1 = new Schema([], [], $schemaConfig);
        $schema2 = new Schema([$table], [], $schemaConfig);

        $expected = new SchemaDiff([], [], [$table], [], [], [], [], []);

        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareAutoIncrementChanged(): void
    {
        $column1 = Column::editor()
            ->setUnquotedName('foo')
            ->setTypeName(Types::INTEGER)
            ->setAutoincrement(true)
            ->create();

        $column2 = $column1->edit()
            ->setAutoincrement(false)
            ->create();

        $diff = new ColumnDiff($column2, $column1);

        self::assertTrue($diff->hasAutoIncrementChanged());
    }

    public function testCompareChangedColumnsChangeType(): void
    {
        $column1 = Column::editor()
            ->setUnquotedName('id')
            ->setTypeName(Types::STRING)
            ->create();

        $column2 = $column1->edit()
            ->setTypeName(Types::INTEGER)
            ->create();

        $diff12 = new ColumnDiff($column2, $column1);
        self::assertTrue($diff12->hasTypeChanged());

        $diff11 = new ColumnDiff($column1, $column1);
        self::assertFalse($diff11->hasTypeChanged());
    }

    public function testDifferentTypeInstancesOfTheSameType(): void
    {
        $type1 = Type::getType(Types::INTEGER);
        $type2 = clone $type1;

        self::assertNotSame($type1, $type2);

        $column1 = Column::editor()
            ->setUnquotedName('id')
            ->setType($type1)
            ->create();

        $column2 = $column1->edit()
            ->setType($type2)
            ->create();

        $diff = new ColumnDiff($column2, $column1);
        self::assertFalse($diff->hasTypeChanged());
    }

    public function testOverriddenType(): void
    {
        $defaultStringType = Type::getType(Types::STRING);
        $integerType       = Type::getType(Types::INTEGER);

        Type::overrideType(Types::STRING, $integerType::class);
        $overriddenStringType = Type::getType(Types::STRING);

        Type::overrideType(Types::STRING, $defaultStringType::class);

        $column1 = Column::editor()
            ->setUnquotedName('id')
            ->setType($integerType)
            ->create();

        $column2 = $column1->edit()
            ->setType($overriddenStringType)
            ->create();

        $diff = new ColumnDiff($column2, $column1);
        self::assertFalse($diff->hasTypeChanged());
    }

    public function testCompareChangeColumnsMultipleNewColumnsRename(): void
    {
        $tableA = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('datecolumn1')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->create(),
            )
            ->create();

        $tableB = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('new_datecolumn1')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('new_datecolumn2')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($tableA, $tableB);

        $renamedColumns = RenameColumnTest::getRenamedColumns($tableDiff);
        self::assertCount(1, $renamedColumns);
        self::assertArrayHasKey('datecolumn1', $renamedColumns);
        self::assertEquals(['new_datecolumn2'], $this->getObjectNames($tableDiff->getAddedColumns()));

        self::assertCount(0, $tableDiff->getDroppedColumns());
        self::assertCount(1, $tableDiff->getChangedColumns());
    }

    public function testCompareSequences(): void
    {
        $sequence1 = Sequence::editor()
            ->setUnquotedName('foo')
            ->create();

        $sequence2 = Sequence::editor()
            ->setUnquotedName('foo')
            ->setInitialValue(2)
            ->create();

        $sequence3 = Sequence::editor()
            ->setUnquotedName('foo')
            ->setAllocationSize(2)
            ->create();

        self::assertTrue($this->comparator->diffSequence($sequence1, $sequence2));
        self::assertTrue($this->comparator->diffSequence($sequence1, $sequence3));
    }

    public function testRemovedSequence(): void
    {
        $schema1 = new Schema();
        $seq     = $schema1->createSequence('foo');

        $schema2 = new Schema();

        $diffSchema = $this->comparator->compareSchemas($schema1, $schema2);

        self::assertSame([$seq], $diffSchema->getDroppedSequences());
    }

    public function testAddedSequence(): void
    {
        $schema1 = new Schema();

        $schema2 = new Schema();
        $seq     = $schema2->createSequence('foo');

        $diffSchema = $this->comparator->compareSchemas($schema1, $schema2);

        self::assertSame([$seq], $diffSchema->getCreatedSequences());
    }

    public function testTableAddForeignKey(): void
    {
        $tableForeign = Table::editor()
            ->setUnquotedName('bar')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table1 = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('fk')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table2 = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('fk')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('fk')
                    ->setUnquotedReferencedTableName('bar')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($table1, $table2);

        self::assertCount(1, $tableDiff->getAddedForeignKeys());
    }

    public function testTableRemoveForeignKey(): void
    {
        $table1 = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('fk')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table2 = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('fk')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_bar')
                    ->setUnquotedReferencingColumnNames('fk')
                    ->setUnquotedReferencedTableName('bar')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($table2, $table1);

        self::assertCount(1, $tableDiff->getDroppedForeignKeyConstraintNames());
    }

    public function testTableUpdateForeignKey(): void
    {
        $table1 = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('fk')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_bar')
                    ->setUnquotedReferencingColumnNames('fk')
                    ->setUnquotedReferencedTableName('bar')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $table2 = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('fk')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('fk')
                    ->setUnquotedReferencedTableName('bar')
                    ->setUnquotedReferencedColumnNames('id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($table1, $table2);

        self::assertCount(1, $tableDiff->getDroppedForeignKeyConstraintNames());
        self::assertCount(1, $tableDiff->getAddedForeignKeys());
    }

    public function testMovedForeignKeyForeignTable(): void
    {
        $table1 = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('fk')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_bar')
                    ->setUnquotedReferencingColumnNames('fk')
                    ->setUnquotedReferencedTableName('bar')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $table2 = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('fk')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('fk')
                    ->setUnquotedReferencedTableName('bar2')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($table1, $table2);

        self::assertCount(1, $tableDiff->getDroppedForeignKeyConstraintNames());
        self::assertCount(1, $tableDiff->getAddedForeignKeys());
    }

    public function testTablesCaseInsensitive(): void
    {
        $schemaA = new Schema([
            $this->createTable('foo'),
            $this->createTable('bAr'),
            $this->createTable('BAZ'),
            $this->createTable('new'),
        ]);

        $schemaB = new Schema([
            $this->createTable('FOO'),
            $this->createTable('bar'),
            $this->createTable('Baz'),
            $this->createTable('old'),
        ]);

        $diff = $this->comparator->compareSchemas($schemaA, $schemaB);

        self::assertCount(1, $diff->getCreatedTables());
        self::assertCount(0, $diff->getAlteredTables());
        self::assertCount(1, $diff->getDroppedTables());
    }

    public function testSequencesCaseInsensitive(): void
    {
        $schemaA = new Schema();
        $schemaA->createSequence('foo');
        $schemaA->createSequence('BAR');
        $schemaA->createSequence('Baz');
        $schemaA->createSequence('new');

        $schemaB = new Schema();
        $schemaB->createSequence('FOO');
        $schemaB->createSequence('Bar');
        $schemaB->createSequence('baz');
        $schemaB->createSequence('old');

        $diff = $this->comparator->compareSchemas($schemaA, $schemaB);

        self::assertCount(1, $diff->getCreatedSequences());
        self::assertCount(0, $diff->getAlteredSequences());
        self::assertCount(1, $diff->getDroppedSequences());
    }

    public function testCompareColumnCompareCaseInsensitive(): void
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
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('ID')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($tableA, $tableB);

        self::assertTrue($tableDiff->isEmpty());
    }

    public function testCompareIndexBasedOnPropertiesNotName(): void
    {
        $tableA = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('foo_bar_idx')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $tableB = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('ID')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('bar_foo_idx')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertEquals(
            new TableDiff($tableA, renamedIndexes: [
                'foo_bar_idx' => new Index('bar_foo_idx', ['id']),
            ]),
            $this->comparator->compareTables($tableA, $tableB),
        );
    }

    public function testCompareForeignKeyBasedOnPropertiesNotName(): void
    {
        $tableA = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('foo_constraint')
                    ->setUnquotedReferencingColumnNames('id')
                    ->setUnquotedReferencedTableName('bar')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        $tableB = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('ID')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('bar_constraint')
                    ->setUnquotedReferencingColumnNames('id')
                    ->setUnquotedReferencedTableName('bar')
                    ->setUnquotedReferencedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertEquals(
            new TableDiff($tableA),
            $this->comparator->compareTables($tableA, $tableB),
        );
    }

    public function testDetectRenameColumn(): void
    {
        $tableA = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $tableB = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($tableA, $tableB);

        self::assertCount(0, $tableDiff->getAddedColumns());
        self::assertCount(0, $tableDiff->getDroppedColumns());

        $renamedColumns = RenameColumnTest::getRenamedColumns($tableDiff);
        self::assertArrayHasKey('foo', $renamedColumns);
        self::assertEquals('bar', $renamedColumns['foo']->getObjectName()->toString());
    }

    public function testDetectRenameColumnDisabled(): void
    {
        $tableA = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $tableB = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->comparator = $this->createComparator((new ComparatorConfig())->withDetectRenamedColumns(false));
        $tableDiff        = $this->comparator->compareTables($tableA, $tableB);

        self::assertCount(1, $tableDiff->getAddedColumns());
        self::assertCount(1, $tableDiff->getDroppedColumns());
        self::assertCount(0, $tableDiff->getRenamedColumns());
    }

    /**
     * You can easily have ambiguities in the column renaming. If these
     * are detected no renaming should take place, instead adding and dropping
     * should be used exclusively.
     */
    public function testDetectRenameColumnAmbiguous(): void
    {
        $tableA = Table::editor()
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

        $tableB = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('baz')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($tableA, $tableB);

        self::assertEquals(['baz'], $this->getObjectNames($tableDiff->getAddedColumns()));
        self::assertEquals(['foo', 'bar'], $this->getObjectNames($tableDiff->getDroppedColumns()));
        self::assertCount(0, RenameColumnTest::getRenamedColumns($tableDiff));
    }

    public function testDetectRenameIndex(): void
    {
        $prototype = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table1 = $prototype->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_foo')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->create();

        $table2 = $prototype->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_bar')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($table1, $table2);

        self::assertCount(0, $tableDiff->getAddedColumns());
        self::assertCount(0, $tableDiff->getDroppedIndexes());

        $renamedIndexes = $tableDiff->getRenamedIndexes();
        self::assertArrayHasKey('idx_foo', $renamedIndexes);
        self::assertEquals('idx_bar', $renamedIndexes['idx_foo']->getObjectName()->toString());
    }

    public function testDetectRenameIndexDisabled(): void
    {
        $prototype = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table1 = $prototype->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_foo')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->create();

        $table2 = $prototype->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_bar')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->create();

        $this->comparator = $this->createComparator((new ComparatorConfig())->withDetectRenamedIndexes(false));
        $tableDiff        = $this->comparator->compareTables($table1, $table2);

        self::assertCount(1, $tableDiff->getAddedIndexes());
        self::assertCount(1, $tableDiff->getDroppedIndexes());
        self::assertCount(0, $tableDiff->getRenamedIndexes());
    }

    /**
     * You can easily have ambiguities in the index renaming. If these
     * are detected no renaming should take place, instead adding and dropping
     * should be used exclusively.
     */
    public function testDetectRenameIndexAmbiguous(): void
    {
        $prototype = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table1 = $prototype->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_foo')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_bar')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->create();

        $table2 = $prototype->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_baz')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($table1, $table2);

        self::assertEquals(['idx_baz'], $this->getObjectNames($tableDiff->getAddedIndexes()));
        self::assertEquals(['idx_foo', 'idx_bar'], $this->getObjectNames($tableDiff->getDroppedIndexes()));
        self::assertCount(0, $tableDiff->getRenamedIndexes());
    }

    public function testDetectRenameIndexAmbiguousWithMultipleAddedIndexes(): void
    {
        $prototype = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $table1 = $prototype->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_foo')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->create();

        $table2 = $prototype->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_bar')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_baz')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($table1, $table2);

        self::assertEquals(['idx_bar', 'idx_baz'], $this->getObjectNames($tableDiff->getAddedIndexes()));
        self::assertEquals(['idx_foo'], $this->getObjectNames($tableDiff->getDroppedIndexes()));
        self::assertCount(0, $tableDiff->getRenamedIndexes());
    }

    public function testDetectChangeIdentifierType(): void
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
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($tableA, $tableB);

        $modifiedColumns = $tableDiff->getChangedColumns();
        self::assertCount(1, $modifiedColumns);
        /** @var ColumnDiff $modifiedColumn */
        $modifiedColumn = current($modifiedColumns);
        self::assertEquals('id', $modifiedColumn->getOldColumn()->getObjectName()->toString());
    }

    public function testReportModifiedIndexesEnabled(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6890');

        $tableDiff = $this->compareTablesWithModifiedIndex(true);

        self::assertCount(0, $tableDiff->getDroppedIndexes());
        self::assertCount(0, $tableDiff->getAddedIndexes());
        self::assertCount(1, $tableDiff->getModifiedIndexes());
    }

    public function testReportModifiedIndexesDisabled(): void
    {
        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6890');

        $tableDiff = $this->compareTablesWithModifiedIndex(false);

        self::assertCount(1, $tableDiff->getDroppedIndexes());
        self::assertCount(1, $tableDiff->getAddedIndexes());
        self::assertCount(0, $tableDiff->getModifiedIndexes());
    }

    private function compareTablesWithModifiedIndex(bool $reportModifiedIndexes): TableDiff
    {
        $tableA = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx_id')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $tableB = $tableA->edit()
            ->dropIndexByUnquotedName('idx_id')
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_id')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        return $this->createComparator(
            (new ComparatorConfig())->withReportModifiedIndexes($reportModifiedIndexes),
        )->compareTables($tableA, $tableB);
    }

    public function testDiff(): void
    {
        $table = Table::editor()
            ->setUnquotedName('twitter_users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('twitterId')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('displayName')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $newtable = Table::editor()
            ->setUnquotedName('twitter_users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('twitter_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('display_name')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('logged_in_at')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $tableDiff = $this->comparator->compareTables($table, $newtable);

        self::assertEquals(['twitterId', 'displayName'], array_keys(RenameColumnTest::getRenamedColumns($tableDiff)));
        self::assertEquals(['logged_in_at'], $this->getObjectNames($tableDiff->getAddedColumns()));
        self::assertCount(0, $tableDiff->getDroppedColumns());
    }

    public function testAlteredSequence(): void
    {
        $oldSchema = new Schema();
        $oldSchema->createSequence('baz');

        $newSchema = clone $oldSchema;
        $newSchema->getSequence('baz')->setAllocationSize(20);

        $diff = $this->comparator->compareSchemas($oldSchema, $newSchema);

        self::assertSame([$newSchema->getSequence('baz')], $diff->getAlteredSequences());
    }

    public function testFqnSchemaComparison(): void
    {
        $config = new SchemaConfig();
        $config->setName('foo');

        $oldSchema = new Schema([
            $this->createTable('bar'),
        ], [], $config);

        $newSchema = new Schema([
            $this->createTable('foo.bar'),
        ], [], $config);

        self::assertEquals(
            new SchemaDiff([], [], [], [], [], [], [], []),
            $this->comparator->compareSchemas($oldSchema, $newSchema),
        );
    }

    public function testNamespacesComparison(): void
    {
        $config = new SchemaConfig();
        $config->setName('schemaName');

        $oldSchema = new Schema([
            $this->createTable('taz'),
            $this->createTable('war.tab'),
        ], [], $config);

        $newSchema = new Schema([
            $this->createTable('bar.tab'),
            $this->createTable('baz.tab'),
            $this->createTable('war.tab'),
        ], [], $config);

        $diff = $this->comparator->compareSchemas($oldSchema, $newSchema);

        self::assertEquals(['bar', 'baz'], $diff->getCreatedSchemas());
        self::assertCount(2, $diff->getCreatedTables());
    }

    public function testFqnSchemaComparisonDifferentSchemaNameButSameTableNoDiff(): void
    {
        $config = new SchemaConfig();
        $config->setName('foo');

        $oldSchema = new Schema([], [], $config);
        $oldSchema->createTable('foo.bar');

        $newSchema = new Schema();
        $newSchema->createTable('bar');

        self::assertEquals(
            new SchemaDiff([], [], [], [], [], [], [], []),
            $this->comparator->compareSchemas($oldSchema, $newSchema),
        );
    }

    public function testFqnSchemaComparisonNoSchemaSame(): void
    {
        $config = new SchemaConfig();
        $config->setName('foo');
        $oldSchema = new Schema([$this->createTable('bar')], [], $config);

        $newSchema = new Schema([$this->createTable('bar')]);

        self::assertEquals(
            new SchemaDiff([], [], [], [], [], [], [], []),
            $this->comparator->compareSchemas($oldSchema, $newSchema),
        );
    }

    public function testAutoIncrementSequences(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $oldSchema = new Schema([$table]);
        $oldSchema->createSequence('foo_id_seq');

        $newSchema = new Schema([$table]);

        $diff = $this->comparator->compareSchemas($oldSchema, $newSchema);

        self::assertCount(0, $diff->getDroppedSequences());
    }

    /**
     * Check that added autoincrement sequence is not populated in newSequences
     */
    public function testAutoIncrementNoSequences(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $oldSchema = new Schema([$table]);

        $newSchema = new Schema([$table]);
        $newSchema->createSequence('foo_id_seq');

        $diff = $this->comparator->compareSchemas($oldSchema, $newSchema);

        self::assertCount(0, $diff->getCreatedSequences());
    }

    public function testComparesNamespaces(): void
    {
        $oldSchema = new Schema([], [], null, ['foo', 'bar']);
        $newSchema = new Schema([], [], null, ['bar', 'baz']);

        $diff = $this->comparator->compareSchemas($oldSchema, $newSchema);

        self::assertEquals(['baz'], $diff->getCreatedSchemas());
        self::assertEquals(['foo'], $diff->getDroppedSchemas());
    }

    #[DataProvider('getCompareColumnComments')]
    public function testCompareColumnComments(string $comment1, string $comment2, bool $equals): void
    {
        $column1 = Column::editor()
            ->setUnquotedName('foo')
            ->setTypeName(Types::INTEGER)
            ->setComment($comment1)
            ->create();

        $column2 = $column1->edit()
            ->setComment($comment2)
            ->create();

        $diff1 = new ColumnDiff($column2, $column1);
        $diff2 = new ColumnDiff($column1, $column2);

        self::assertSame(! $equals, $diff1->hasCommentChanged());
        self::assertSame(! $equals, $diff2->hasCommentChanged());
    }

    /** @return list<array{string, string, bool}> */
    public static function getCompareColumnComments(): iterable
    {
        return [
            ['', '', true],
            [' ', ' ', true],
            ['0', '0', true],
            ['foo', 'foo', true],

            ['', ' ', false],
            ['', '0', false],
            ['', 'foo', false],

            [' ', '0', false],
            [' ', 'foo', false],

            ['0', 'foo', false],
        ];
    }

    public function testForeignKeyRemovalWithRenamedLocalColumn(): void
    {
        $oldSchema = new Schema([
            Table::editor()
                ->setUnquotedName('table1')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('id')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                )
                ->create(),
            Table::editor()
                ->setUnquotedName('table2')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('id')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('id_table1')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                )
                ->setForeignKeyConstraints(
                    ForeignKeyConstraint::editor()
                        ->setUnquotedReferencingColumnNames('id_table1')
                        ->setUnquotedReferencedTableName('table1')
                        ->setUnquotedReferencedColumnNames('fk_table2_table1')
                        ->create(),
                )
                ->create(),
        ]);
        $newSchema = new Schema([
            Table::editor()
                ->setUnquotedName('table2')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('id')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('id_table3')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                )
                ->setForeignKeyConstraints(
                    ForeignKeyConstraint::editor()
                        ->setUnquotedName('fk_table2_table3')
                        ->setUnquotedReferencingColumnNames('id_table3')
                        ->setUnquotedReferencedTableName('table3')
                        ->setUnquotedReferencedColumnNames('id')
                        ->create(),
                )
                ->create(),
            Table::editor()
                ->setUnquotedName('table3')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('id')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                )
                ->create(),
        ]);

        $schemaDiff = $this->comparator->compareSchemas($oldSchema, $newSchema);

        $alteredTables = $schemaDiff->getAlteredTables();
        self::assertCount(1, $alteredTables);

        $addedForeignKeys = $alteredTables[0]->getAddedForeignKeys();
        self::assertCount(1, $addedForeignKeys, 'FK to table3 should be added.');
        self::assertEquals('table3', $addedForeignKeys[0]->getForeignTableName());
    }

    public function testWillNotProduceSchemaDiffOnTableWithAddedCustomSchemaDefinition(): void
    {
        $oldSchema = new Schema([
            Table::editor()
                ->setUnquotedName('a_table')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('is_default')
                        ->setTypeName(Types::STRING)
                        ->setLength(32)
                        ->create(),
                )
                ->create(),
        ]);
        $newSchema = new Schema([
            Table::editor()
                ->setUnquotedName('a_table')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('is_default')
                        ->setTypeName(Types::STRING)
                        ->setLength(32)
                        ->setColumnDefinition('ENUM(\'default\')')
                        ->create(),
                )
                ->create(),
        ]);

        self::assertEmpty(
            $this->comparator->compareSchemas($oldSchema, $newSchema)
                ->getAlteredTables(),
            'Schema diff is empty, since only `columnDefinition` changed from `null` (not detected) to a defined one',
        );
    }

    /**
     * @param array<NamedObject<UnqualifiedName>> $objects
     *
     * @return array<string>
     */
    protected function getObjectNames(array $objects): array
    {
        $names = [];

        foreach ($objects as $asset) {
            $names[] = $asset->getObjectName()->toString();
        }

        return $names;
    }

    /** @param non-empty-string $name */
    private function createTable(string $name): Table
    {
        return Table::editor()
            ->setUnquotedName($name)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();
    }
}

<?php

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function get_class;

class AbstractComparatorTestCase extends TestCase
{
    use VerifyDeprecations;

    protected Comparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new Comparator();
    }

    public function testCompareSame1(): void
    {
        $schema1 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                ],
            ),
        ]);
        $schema2 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                ],
            ),
        ]);

        $expected             = new SchemaDiff();
        $expected->fromSchema = $schema1;
        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareSame2(): void
    {
        $schema1 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                ],
            ),
        ]);
        $schema2 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                ],
            ),
        ]);

        $expected             = new SchemaDiff();
        $expected->fromSchema = $schema1;
        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareMissingTable(): void
    {
        $schemaConfig = new SchemaConfig();

        $table = new Table('bugdb', ['integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER))]);
        $table->setSchemaConfig($schemaConfig);

        $schema1 = new Schema([$table], [], $schemaConfig);
        $schema2 = new Schema([], [], $schemaConfig);

        $expected = new SchemaDiff([], [], ['bugdb' => $table], $schema1);

        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareNewTable(): void
    {
        $schemaConfig = new SchemaConfig();

        $table = new Table('bugdb', ['integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER))]);
        $table->setSchemaConfig($schemaConfig);

        $schema1 = new Schema([], [], $schemaConfig);
        $schema2 = new Schema([$table], [], $schemaConfig);

        $expected = new SchemaDiff(['bugdb' => $table], [], [], $schema1);

        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareOnlyAutoincrementChanged(): void
    {
        $column1 = new Column('foo', Type::getType(Types::INTEGER), ['autoincrement' => true]);
        $column2 = new Column('foo', Type::getType(Types::INTEGER), ['autoincrement' => false]);

        $changedProperties = $this->comparator->diffColumn($column1, $column2);

        self::assertEquals(['autoincrement'], $changedProperties);
    }

    public function testCompareMissingField(): void
    {
        $missingColumn = new Column('integercolumn1', Type::getType(Types::INTEGER));
        $schema1       = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => $missingColumn,
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                ],
            ),
        ]);
        $schema2       = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                ],
            ),
        ]);

        $expected                                    = new SchemaDiff(
            [],
            [
                'bugdb' => new TableDiff(
                    'bugdb',
                    [],
                    [],
                    ['integercolumn1' => $missingColumn],
                ),
            ],
        );
        $expected->fromSchema                        = $schema1;
        $expected->changedTables['bugdb']->fromTable = $schema1->getTable('bugdb');

        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareNewField(): void
    {
        $schema1 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                ],
            ),
        ]);
        $schema2 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                ],
            ),
        ]);

        $expected                                    = new SchemaDiff(
            [],
            [
                'bugdb' => new TableDiff(
                    'bugdb',
                    [
                        'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                    ],
                ),
            ],
        );
        $expected->fromSchema                        = $schema1;
        $expected->changedTables['bugdb']->fromTable = $schema1->getTable('bugdb');

        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareChangedColumnsChangeType(): void
    {
        $column1 = new Column('charcolumn1', Type::getType(Types::STRING));
        $column2 = new Column('charcolumn1', Type::getType(Types::INTEGER));

        self::assertEquals(['type'], $this->comparator->diffColumn($column1, $column2));
        self::assertEquals([], $this->comparator->diffColumn($column1, $column1));
    }

    public function testCompareColumnsMultipleTypeInstances(): void
    {
        $integerType1 = Type::getType(Types::INTEGER);
        Type::overrideType(Types::INTEGER, get_class($integerType1));
        $integerType2 = Type::getType(Types::INTEGER);

        $column1 = new Column('integercolumn1', $integerType1);
        $column2 = new Column('integercolumn1', $integerType2);

        self::assertEquals([], $this->comparator->diffColumn($column1, $column2));
    }

    public function testCompareColumnsOverriddenType(): void
    {
        $oldStringInstance = Type::getType(Types::STRING);
        $integerType       = Type::getType(Types::INTEGER);

        Type::overrideType(Types::STRING, get_class($integerType));
        $overriddenStringType = Type::getType(Types::STRING);

        Type::overrideType(Types::STRING, get_class($oldStringInstance));

        $column1 = new Column('integercolumn1', $integerType);
        $column2 = new Column('integercolumn1', $overriddenStringType);

        self::assertEquals([], $this->comparator->diffColumn($column1, $column2));
    }

    public function testCompareChangedColumnsChangeCustomSchemaOption(): void
    {
        $column1 = new Column('charcolumn1', Type::getType(Types::STRING));
        $column2 = new Column('charcolumn1', Type::getType(Types::STRING));

        $column1->setCustomSchemaOption('foo', 'bar');
        $column2->setCustomSchemaOption('foo', 'bar');

        $column1->setCustomSchemaOption('foo1', 'bar1');
        $column2->setCustomSchemaOption('foo2', 'bar2');

        self::assertEquals(['foo1', 'foo2'], $this->comparator->diffColumn($column1, $column2));
        self::assertEquals([], $this->comparator->diffColumn($column1, $column1));
    }

    public function testCompareChangeColumnsMultipleNewColumnsRename(): void
    {
        $tableA = new Table('foo');
        $tableA->addColumn('datecolumn1', Types::DATETIME_MUTABLE);

        $tableB = new Table('foo');
        $tableB->addColumn('new_datecolumn1', Types::DATETIME_MUTABLE);
        $tableB->addColumn('new_datecolumn2', Types::DATETIME_MUTABLE);

        $tableDiff = $this->comparator->diffTable($tableA, $tableB);
        self::assertNotFalse($tableDiff);

        self::assertCount(1, $tableDiff->renamedColumns);
        self::assertArrayHasKey('datecolumn1', $tableDiff->renamedColumns);
        self::assertCount(1, $tableDiff->addedColumns);
        self::assertArrayHasKey('new_datecolumn2', $tableDiff->addedColumns);
        self::assertCount(0, $tableDiff->removedColumns);
        self::assertCount(0, $tableDiff->changedColumns);
    }

    public function testCompareRemovedIndex(): void
    {
        $schema1 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                ],
                [
                    'primary' => new Index(
                        'primary',
                        ['integercolumn1'],
                        true,
                    ),
                ],
            ),
        ]);
        $schema2 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                ],
            ),
        ]);

        $expected                                    = new SchemaDiff(
            [],
            [
                'bugdb' => new TableDiff(
                    'bugdb',
                    [],
                    [],
                    [],
                    [],
                    [],
                    [
                        'primary' => new Index(
                            'primary',
                            ['integercolumn1'],
                            true,
                        ),
                    ],
                ),
            ],
        );
        $expected->fromSchema                        = $schema1;
        $expected->changedTables['bugdb']->fromTable = $schema1->getTable('bugdb');

        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareNewIndex(): void
    {
        $schema1 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                ],
            ),
        ]);
        $schema2 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                ],
                [
                    'primary' => new Index(
                        'primary',
                        ['integercolumn1'],
                        true,
                    ),
                ],
            ),
        ]);

        $expected                                    = new SchemaDiff(
            [],
            [
                'bugdb' => new TableDiff(
                    'bugdb',
                    [],
                    [],
                    [],
                    [
                        'primary' => new Index(
                            'primary',
                            ['integercolumn1'],
                            true,
                        ),
                    ],
                ),
            ],
        );
        $expected->fromSchema                        = $schema1;
        $expected->changedTables['bugdb']->fromTable = $schema1->getTable('bugdb');

        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareChangedIndex(): void
    {
        $schema1 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                ],
                [
                    'primary' => new Index(
                        'primary',
                        ['integercolumn1'],
                        true,
                    ),
                ],
            ),
        ]);
        $schema2 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                ],
                [
                    'primary' => new Index(
                        'primary',
                        ['integercolumn1', 'integercolumn2'],
                        true,
                    ),
                ],
            ),
        ]);

        $expected                                    = new SchemaDiff(
            [],
            [
                'bugdb' => new TableDiff(
                    'bugdb',
                    [],
                    [],
                    [],
                    [],
                    [
                        'primary' => new Index(
                            'primary',
                            [
                                'integercolumn1',
                                'integercolumn2',
                            ],
                            true,
                        ),
                    ],
                ),
            ],
        );
        $expected->fromSchema                        = $schema1;
        $expected->changedTables['bugdb']->fromTable = $schema1->getTable('bugdb');

        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareChangedIndexFieldPositions(): void
    {
        $schema1 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                ],
                [
                    'primary' => new Index('primary', ['integercolumn1', 'integercolumn2'], true),
                ],
            ),
        ]);
        $schema2 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType(Types::INTEGER)),
                    'integercolumn2' => new Column('integercolumn2', Type::getType(Types::INTEGER)),
                ],
                [
                    'primary' => new Index('primary', ['integercolumn2', 'integercolumn1'], true),
                ],
            ),
        ]);

        $expected                                    = new SchemaDiff(
            [],
            [
                'bugdb' => new TableDiff(
                    'bugdb',
                    [],
                    [],
                    [],
                    [],
                    [
                        'primary' => new Index('primary', ['integercolumn2', 'integercolumn1'], true),
                    ],
                ),
            ],
        );
        $expected->fromSchema                        = $schema1;
        $expected->changedTables['bugdb']->fromTable = $schema1->getTable('bugdb');

        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareSequences(): void
    {
        $seq1 = new Sequence('foo', 1, 1);
        $seq2 = new Sequence('foo', 1, 2);
        $seq3 = new Sequence('foo', 2, 1);
        $seq4 = new Sequence('foo', 1, 1);

        self::assertTrue($this->comparator->diffSequence($seq1, $seq2));
        self::assertTrue($this->comparator->diffSequence($seq1, $seq3));
        self::assertFalse($this->comparator->diffSequence($seq1, $seq4));
    }

    public function testRemovedSequence(): void
    {
        $schema1 = new Schema();
        $seq     = $schema1->createSequence('foo');

        $schema2 = new Schema();

        $diffSchema = $this->comparator->compare($schema1, $schema2);

        self::assertCount(1, $diffSchema->removedSequences);
        self::assertSame($seq, $diffSchema->removedSequences[0]);
    }

    public function testAddedSequence(): void
    {
        $schema1 = new Schema();

        $schema2 = new Schema();
        $seq     = $schema2->createSequence('foo');

        $diffSchema = $this->comparator->compare($schema1, $schema2);

        self::assertCount(1, $diffSchema->newSequences);
        self::assertSame($seq, $diffSchema->newSequences[0]);
    }

    public function testTableAddForeignKey(): void
    {
        $tableForeign = new Table('bar');
        $tableForeign->addColumn('id', Types::INTEGER);

        $table1 = new Table('foo');
        $table1->addColumn('fk', Types::INTEGER);

        $table2 = new Table('foo');
        $table2->addColumn('fk', Types::INTEGER);
        $table2->addForeignKeyConstraint($tableForeign, ['fk'], ['id']);

        $tableDiff = $this->comparator->diffTable($table1, $table2);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertCount(1, $tableDiff->addedForeignKeys);
    }

    public function testTableRemoveForeignKey(): void
    {
        $tableForeign = new Table('bar');
        $tableForeign->addColumn('id', Types::INTEGER);

        $table1 = new Table('foo');
        $table1->addColumn('fk', Types::INTEGER);

        $table2 = new Table('foo');
        $table2->addColumn('fk', Types::INTEGER);
        $table2->addForeignKeyConstraint($tableForeign, ['fk'], ['id']);

        $tableDiff = $this->comparator->diffTable($table2, $table1);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertCount(1, $tableDiff->removedForeignKeys);
    }

    public function testTableUpdateForeignKey(): void
    {
        $tableForeign = new Table('bar');
        $tableForeign->addColumn('id', Types::INTEGER);

        $table1 = new Table('foo');
        $table1->addColumn('fk', Types::INTEGER);
        $table1->addForeignKeyConstraint($tableForeign, ['fk'], ['id']);

        $table2 = new Table('foo');
        $table2->addColumn('fk', Types::INTEGER);
        $table2->addForeignKeyConstraint($tableForeign, ['fk'], ['id'], ['onUpdate' => 'CASCADE']);

        $tableDiff = $this->comparator->diffTable($table1, $table2);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertCount(1, $tableDiff->changedForeignKeys);
    }

    public function testMovedForeignKeyForeignTable(): void
    {
        $tableForeign = new Table('bar');
        $tableForeign->addColumn('id', Types::INTEGER);

        $tableForeign2 = new Table('bar2');
        $tableForeign2->addColumn('id', Types::INTEGER);

        $table1 = new Table('foo');
        $table1->addColumn('fk', Types::INTEGER);
        $table1->addForeignKeyConstraint($tableForeign, ['fk'], ['id']);

        $table2 = new Table('foo');
        $table2->addColumn('fk', Types::INTEGER);
        $table2->addForeignKeyConstraint($tableForeign2, ['fk'], ['id']);

        $tableDiff = $this->comparator->diffTable($table1, $table2);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertCount(1, $tableDiff->changedForeignKeys);
    }

    public function testTablesCaseInsensitive(): void
    {
        $schemaA = new Schema();
        $schemaA->createTable('foo');
        $schemaA->createTable('bAr');
        $schemaA->createTable('BAZ');
        $schemaA->createTable('new');

        $schemaB = new Schema();
        $schemaB->createTable('FOO');
        $schemaB->createTable('bar');
        $schemaB->createTable('Baz');
        $schemaB->createTable('old');

        $diff = $this->comparator->compare($schemaA, $schemaB);

        $this->assertSchemaTableChangeCount($diff, 1, 0, 1);
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

        $diff = $this->comparator->compare($schemaA, $schemaB);

        $this->assertSchemaSequenceChangeCount($diff, 1, 0, 1);
    }

    public function testCompareColumnCompareCaseInsensitive(): void
    {
        $tableA = new Table('foo');
        $tableA->addColumn('id', Types::INTEGER);

        $tableB = new Table('foo');
        $tableB->addColumn('ID', Types::INTEGER);

        $tableDiff = $this->comparator->diffTable($tableA, $tableB);

        self::assertFalse($tableDiff);
    }

    public function testCompareIndexBasedOnPropertiesNotName(): void
    {
        $tableA = new Table('foo');
        $tableA->addColumn('id', Types::INTEGER);
        $tableA->addIndex(['id'], 'foo_bar_idx');

        $tableB = new Table('foo');
        $tableB->addColumn('ID', Types::INTEGER);
        $tableB->addIndex(['id'], 'bar_foo_idx');

        $tableDiff                                = new TableDiff('foo');
        $tableDiff->fromTable                     = $tableA;
        $tableDiff->renamedIndexes['foo_bar_idx'] = new Index('bar_foo_idx', ['id']);

        self::assertEquals(
            $tableDiff,
            $this->comparator->diffTable($tableA, $tableB),
        );
    }

    public function testCompareForeignKeyBasedOnPropertiesNotName(): void
    {
        $tableA = new Table('foo');
        $tableA->addColumn('id', Types::INTEGER);
        $tableA->addForeignKeyConstraint('bar', ['id'], ['id'], [], 'foo_constraint');

        $tableB = new Table('foo');
        $tableB->addColumn('ID', Types::INTEGER);
        $tableB->addForeignKeyConstraint('bar', ['id'], ['id'], [], 'bar_constraint');

        $tableDiff = $this->comparator->diffTable($tableA, $tableB);

        self::assertFalse($tableDiff);
    }

    public function testCompareForeignKeyRestrictNoActionAreTheSame(): void
    {
        $fk1 = new ForeignKeyConstraint(['foo'], 'bar', ['baz'], 'fk1', ['onDelete' => 'NO ACTION']);
        $fk2 = new ForeignKeyConstraint(['foo'], 'bar', ['baz'], 'fk1', ['onDelete' => 'RESTRICT']);

        self::assertFalse($this->comparator->diffForeignKey($fk1, $fk2));
    }

    public function testCompareForeignKeyNamesUnqualifiedAsNoSchemaInformationIsAvailable(): void
    {
        $fk1 = new ForeignKeyConstraint(['foo'], 'foo.bar', ['baz'], 'fk1');
        $fk2 = new ForeignKeyConstraint(['foo'], 'baz.bar', ['baz'], 'fk1');

        self::assertFalse($this->comparator->diffForeignKey($fk1, $fk2));
    }

    public function testDetectRenameColumn(): void
    {
        $tableA = new Table('foo');
        $tableA->addColumn('foo', Types::INTEGER);

        $tableB = new Table('foo');
        $tableB->addColumn('bar', Types::INTEGER);

        $tableDiff = $this->comparator->diffTable($tableA, $tableB);
        self::assertNotFalse($tableDiff);

        self::assertCount(0, $tableDiff->addedColumns);
        self::assertCount(0, $tableDiff->removedColumns);
        self::assertArrayHasKey('foo', $tableDiff->renamedColumns);
        self::assertEquals('bar', $tableDiff->renamedColumns['foo']->getName());
    }

    /**
     * You can easily have ambiguities in the column renaming. If these
     * are detected no renaming should take place, instead adding and dropping
     * should be used exclusively.
     */
    public function testDetectRenameColumnAmbiguous(): void
    {
        $tableA = new Table('foo');
        $tableA->addColumn('foo', Types::INTEGER);
        $tableA->addColumn('bar', Types::INTEGER);

        $tableB = new Table('foo');
        $tableB->addColumn('baz', Types::INTEGER);

        $tableDiff = $this->comparator->diffTable($tableA, $tableB);
        self::assertNotFalse($tableDiff);

        self::assertCount(1, $tableDiff->addedColumns);
        self::assertArrayHasKey('baz', $tableDiff->addedColumns);
        self::assertCount(2, $tableDiff->removedColumns);
        self::assertArrayHasKey('foo', $tableDiff->removedColumns);
        self::assertArrayHasKey('bar', $tableDiff->removedColumns);
        self::assertCount(0, $tableDiff->renamedColumns);
    }

    public function testDetectRenameIndex(): void
    {
        $table1 = new Table('foo');
        $table1->addColumn('foo', Types::INTEGER);

        $table2 = clone $table1;

        $table1->addIndex(['foo'], 'idx_foo');

        $table2->addIndex(['foo'], 'idx_bar');

        $tableDiff = $this->comparator->diffTable($table1, $table2);
        self::assertNotFalse($tableDiff);

        self::assertCount(0, $tableDiff->addedIndexes);
        self::assertCount(0, $tableDiff->removedIndexes);
        self::assertArrayHasKey('idx_foo', $tableDiff->renamedIndexes);
        self::assertEquals('idx_bar', $tableDiff->renamedIndexes['idx_foo']->getName());
    }

    /**
     * You can easily have ambiguities in the index renaming. If these
     * are detected no renaming should take place, instead adding and dropping
     * should be used exclusively.
     */
    public function testDetectRenameIndexAmbiguous(): void
    {
        $table1 = new Table('foo');
        $table1->addColumn('foo', Types::INTEGER);

        $table2 = clone $table1;

        $table1->addIndex(['foo'], 'idx_foo');
        $table1->addIndex(['foo'], 'idx_bar');

        $table2->addIndex(['foo'], 'idx_baz');

        $tableDiff = $this->comparator->diffTable($table1, $table2);
        self::assertNotFalse($tableDiff);

        self::assertCount(1, $tableDiff->addedIndexes);
        self::assertArrayHasKey('idx_baz', $tableDiff->addedIndexes);
        self::assertCount(2, $tableDiff->removedIndexes);
        self::assertArrayHasKey('idx_foo', $tableDiff->removedIndexes);
        self::assertArrayHasKey('idx_bar', $tableDiff->removedIndexes);
        self::assertCount(0, $tableDiff->renamedIndexes);
    }

    public function testDetectChangeIdentifierType(): void
    {
        $tableA = new Table('foo');
        $tableA->addColumn('id', Types::INTEGER, ['autoincrement' => false]);

        $tableB = new Table('foo');
        $tableB->addColumn('id', Types::INTEGER, ['autoincrement' => true]);

        $tableDiff = $this->comparator->diffTable($tableA, $tableB);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertArrayHasKey('id', $tableDiff->changedColumns);
    }

    public function testDiff(): void
    {
        $table = new Table('twitter_users');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('twitterId', Types::INTEGER);
        $table->addColumn('displayName', Types::STRING);
        $table->setPrimaryKey(['id']);

        $newtable = new Table('twitter_users');
        $newtable->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $newtable->addColumn('twitter_id', Types::INTEGER);
        $newtable->addColumn('display_name', Types::STRING);
        $newtable->addColumn('logged_in_at', Types::DATETIME_MUTABLE);
        $newtable->setPrimaryKey(['id']);

        $tableDiff = $this->comparator->diffTable($table, $newtable);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertEquals(['twitterId', 'displayName'], array_keys($tableDiff->renamedColumns));
        self::assertEquals(['logged_in_at'], array_keys($tableDiff->addedColumns));
        self::assertCount(0, $tableDiff->removedColumns);
    }

    public function testChangedSequence(): void
    {
        $schema = new Schema();
        $schema->createSequence('baz');

        $schemaNew = clone $schema;
        $schemaNew->getSequence('baz')->setAllocationSize(20);

        $diff = $this->comparator->compare($schema, $schemaNew);

        self::assertSame($diff->changedSequences[0], $schemaNew->getSequence('baz'));
    }

    /** @psalm-suppress NullArgument */
    public function testDiffDecimalWithNullPrecision(): void
    {
        $column = new Column('foo', Type::getType(Types::DECIMAL));
        $column->setPrecision(null);

        $column2 = new Column('foo', Type::getType(Types::DECIMAL));

        self::assertEquals([], $this->comparator->diffColumn($column, $column2));
    }

    public function testFqnSchemaComparison(): void
    {
        $config = new SchemaConfig();
        $config->setName('foo');

        $oldSchema = new Schema([], [], $config);
        $oldSchema->createTable('bar');

        $newSchema = new Schema([], [], $config);
        $newSchema->createTable('foo.bar');

        $expected             = new SchemaDiff();
        $expected->fromSchema = $oldSchema;

        self::assertEquals($expected, $this->comparator->compareSchemas($oldSchema, $newSchema));
    }

    public function testNamespacesComparison(): void
    {
        $config = new SchemaConfig();
        $config->setName('schemaName');

        $oldSchema = new Schema([], [], $config);
        $oldSchema->createTable('taz');
        $oldSchema->createTable('war.tab');

        $newSchema = new Schema([], [], $config);
        $newSchema->createTable('bar.tab');
        $newSchema->createTable('baz.tab');
        $newSchema->createTable('war.tab');

        $expected                = new SchemaDiff();
        $expected->fromSchema    = $oldSchema;
        $expected->newNamespaces = ['bar' => 'bar', 'baz' => 'baz'];

        $diff = $this->comparator->compareSchemas($oldSchema, $newSchema);

        self::assertEquals(['bar' => 'bar', 'baz' => 'baz'], $diff->newNamespaces);
        self::assertCount(2, $diff->newTables);
    }

    public function testFqnSchemaComparisonDifferentSchemaNameButSameTableNoDiff(): void
    {
        $config = new SchemaConfig();
        $config->setName('foo');

        $oldSchema = new Schema([], [], $config);
        $oldSchema->createTable('foo.bar');

        $newSchema = new Schema();
        $newSchema->createTable('bar');

        $expected             = new SchemaDiff();
        $expected->fromSchema = $oldSchema;

        self::assertEquals($expected, $this->comparator->compareSchemas($oldSchema, $newSchema));
    }

    public function testFqnSchemaComparisonNoSchemaSame(): void
    {
        $config = new SchemaConfig();
        $config->setName('foo');
        $oldSchema = new Schema([], [], $config);
        $oldSchema->createTable('bar');

        $newSchema = new Schema();
        $newSchema->createTable('bar');

        $expected             = new SchemaDiff();
        $expected->fromSchema = $oldSchema;

        self::assertEquals($expected, $this->comparator->compareSchemas($oldSchema, $newSchema));
    }

    public function testAutoIncrementSequences(): void
    {
        $oldSchema = new Schema();
        $table     = $oldSchema->createTable('foo');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        $oldSchema->createSequence('foo_id_seq');

        $newSchema = new Schema();
        $table     = $newSchema->createTable('foo');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        $diff = $this->comparator->compare($oldSchema, $newSchema);

        self::assertCount(0, $diff->removedSequences);
    }

    /**
     * Check that added autoincrement sequence is not populated in newSequences
     */
    public function testAutoIncrementNoSequences(): void
    {
        $oldSchema = new Schema();
        $table     = $oldSchema->createTable('foo');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        $newSchema = new Schema();
        $table     = $newSchema->createTable('foo');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        $newSchema->createSequence('foo_id_seq');

        $diff = $this->comparator->compare($oldSchema, $newSchema);

        self::assertCount(0, $diff->newSequences);
    }

    /**
     * You can get multiple drops for a FK when a table referenced by a foreign
     * key is deleted, as this FK is referenced twice, once on the orphanedForeignKeys
     * array because of the dropped table, and once on changedTables array. We
     * now check that the key is present once.
     */
    public function testAvoidMultipleDropForeignKey(): void
    {
        $oldSchema = new Schema();

        $tableA = $oldSchema->createTable('table_a');
        $tableA->addColumn('id', Types::INTEGER);

        $tableB = $oldSchema->createTable('table_b');
        $tableB->addColumn('id', Types::INTEGER);

        $tableC = $oldSchema->createTable('table_c');
        $tableC->addColumn('id', Types::INTEGER);
        $tableC->addColumn('table_a_id', Types::INTEGER);
        $tableC->addColumn('table_b_id', Types::INTEGER);

        $tableC->addForeignKeyConstraint($tableA, ['table_a_id'], ['id']);
        $tableC->addForeignKeyConstraint($tableB, ['table_b_id'], ['id']);

        $newSchema = new Schema();

        $tableB = $newSchema->createTable('table_b');
        $tableB->addColumn('id', Types::INTEGER);

        $tableC = $newSchema->createTable('table_c');
        $tableC->addColumn('id', Types::INTEGER);

        $schemaDiff = $this->comparator->compare($oldSchema, $newSchema);

        self::assertCount(1, $schemaDiff->changedTables['table_c']->removedForeignKeys);
        self::assertCount(1, $schemaDiff->orphanedForeignKeys);
    }

    public function testCompareChangedColumn(): void
    {
        $oldSchema = new Schema();

        $tableFoo = $oldSchema->createTable('foo');
        $tableFoo->addColumn('id', Types::INTEGER);

        $newSchema = new Schema();
        $table     = $newSchema->createTable('foo');
        $table->addColumn('id', Types::STRING);

        $expected             = new SchemaDiff();
        $expected->fromSchema = $oldSchema;

        $tableDiff            = $expected->changedTables['foo'] = new TableDiff('foo');
        $tableDiff->fromTable = $tableFoo;

        $columnDiff = $tableDiff->changedColumns['id'] = new ColumnDiff('id', $table->getColumn('id'));

        $columnDiff->fromColumn        = $tableFoo->getColumn('id');
        $columnDiff->changedProperties = ['type'];

        self::assertEquals($expected, $this->comparator->compareSchemas($oldSchema, $newSchema));
    }

    public function testCompareChangedBinaryColumn(): void
    {
        $oldSchema = new Schema();

        $tableFoo = $oldSchema->createTable('foo');
        $tableFoo->addColumn('id', Types::BINARY);

        $newSchema = new Schema();
        $table     = $newSchema->createTable('foo');
        $table->addColumn('id', Types::BINARY, ['length' => 42, 'fixed' => true]);

        $expected             = new SchemaDiff();
        $expected->fromSchema = $oldSchema;

        $tableDiff            = $expected->changedTables['foo'] = new TableDiff('foo');
        $tableDiff->fromTable = $tableFoo;

        $columnDiff = $tableDiff->changedColumns['id'] = new ColumnDiff('id', $table->getColumn('id'));

        $columnDiff->fromColumn        = $tableFoo->getColumn('id');
        $columnDiff->changedProperties = ['length', 'fixed'];

        self::assertEquals($expected, $this->comparator->compareSchemas($oldSchema, $newSchema));
    }

    public function testCompareQuotedAndUnquotedForeignKeyColumns(): void
    {
        $fk1 = new ForeignKeyConstraint(['foo'], 'bar', ['baz'], 'fk1', ['onDelete' => 'NO ACTION']);
        $fk2 = new ForeignKeyConstraint(['`foo`'], 'bar', ['`baz`'], 'fk1', ['onDelete' => 'NO ACTION']);

        $diff = $this->comparator->diffForeignKey($fk1, $fk2);

        self::assertFalse($diff);
    }

    public function assertSchemaTableChangeCount(
        SchemaDiff $diff,
        int $newTableCount = 0,
        int $changeTableCount = 0,
        int $removeTableCount = 0
    ): void {
        self::assertCount($newTableCount, $diff->newTables);
        self::assertCount($changeTableCount, $diff->changedTables);
        self::assertCount($removeTableCount, $diff->removedTables);
    }

    public function assertSchemaSequenceChangeCount(
        SchemaDiff $diff,
        int $newSequenceCount = 0,
        int $changeSequenceCount = 0,
        int $removeSequenceCount = 0
    ): void {
        self::assertCount($newSequenceCount, $diff->newSequences);
        self::assertCount($changeSequenceCount, $diff->changedSequences);
        self::assertCount($removeSequenceCount, $diff->removedSequences);
    }

    public function testDiffColumnPlatformOptions(): void
    {
        $column1 = new Column('foo', Type::getType(Types::STRING), [
            'platformOptions' => [
                'foo' => 'foo',
                'bar' => 'bar',
            ],
        ]);

        $column2 = new Column('foo', Type::getType(Types::STRING), [
            'platformOptions' => [
                'foo' => 'foo',
                'foobar' => 'foobar',
            ],
        ]);

        $column3 = new Column('foo', Type::getType(Types::STRING), [
            'platformOptions' => [
                'foo' => 'foo',
                'bar' => 'rab',
            ],
        ]);

        $column4 = new Column('foo', Type::getType(Types::STRING));

        self::assertEquals([], $this->comparator->diffColumn($column1, $column2));
        self::assertEquals([], $this->comparator->diffColumn($column2, $column1));
        self::assertEquals(['bar'], $this->comparator->diffColumn($column1, $column3));
        self::assertEquals(['bar'], $this->comparator->diffColumn($column3, $column1));
        self::assertEquals([], $this->comparator->diffColumn($column1, $column4));
        self::assertEquals([], $this->comparator->diffColumn($column4, $column1));
    }

    public function testComplexDiffColumn(): void
    {
        $column1 = new Column('foo', Type::getType(Types::STRING), [
            'platformOptions' => ['foo' => 'foo'],
            'customSchemaOptions' => ['foo' => 'bar'],
        ]);

        $column2 = new Column('foo', Type::getType(Types::STRING), [
            'platformOptions' => ['foo' => 'bar'],
        ]);

        self::assertEquals([], $this->comparator->diffColumn($column1, $column2));
        self::assertEquals([], $this->comparator->diffColumn($column2, $column1));
    }

    public function testComparesNamespaces(): void
    {
        $fromSchema = new Schema([], [], null, ['foo', 'bar']);
        $toSchema   = new Schema([], [], null, ['bar', 'baz']);

        $expected                    = new SchemaDiff();
        $expected->fromSchema        = $fromSchema;
        $expected->newNamespaces     = ['baz' => 'baz'];
        $expected->removedNamespaces = ['foo' => 'foo'];

        self::assertEquals($expected, $this->comparator->compare($fromSchema, $toSchema));
    }

    public function testCompareGuidColumns(): void
    {
        $column1 = new Column('foo', Type::getType(Types::GUID), ['comment' => 'GUID 1']);
        $column2 = new Column(
            'foo',
            Type::getType(Types::GUID),
            ['notnull' => false, 'length' => '36', 'fixed' => true, 'default' => 'NEWID()', 'comment' => 'GUID 2.'],
        );

        self::assertEquals(['notnull', 'default', 'comment'], $this->comparator->diffColumn($column1, $column2));
        self::assertEquals(['notnull', 'default', 'comment'], $this->comparator->diffColumn($column2, $column1));
    }

    /** @dataProvider getCompareColumnComments */
    public function testCompareColumnComments(?string $comment1, ?string $comment2, bool $equals): void
    {
        $column1 = new Column('foo', Type::getType(Types::INTEGER), ['comment' => $comment1]);
        $column2 = new Column('foo', Type::getType(Types::INTEGER), ['comment' => $comment2]);

        $expectedDiff = $equals ? [] : ['comment'];

        $actualDiff = $this->comparator->diffColumn($column1, $column2);

        self::assertSame($expectedDiff, $actualDiff);

        $actualDiff = $this->comparator->diffColumn($column2, $column1);

        self::assertSame($expectedDiff, $actualDiff);
    }

    /** @return mixed[][] */
    public static function getCompareColumnComments(): iterable
    {
        return [
            [null, null, true],
            ['', '', true],
            [' ', ' ', true],
            ['0', '0', true],
            ['foo', 'foo', true],

            [null, '', true],
            [null, ' ', false],
            [null, '0', false],
            [null, 'foo', false],

            ['', ' ', false],
            ['', '0', false],
            ['', 'foo', false],

            [' ', '0', false],
            [' ', 'foo', false],

            ['0', 'foo', false],
        ];
    }

    /** @psalm-suppress DeprecatedConstant */
    public function testCompareCommentedTypes(): void
    {
        $column1 = new Column('foo', Type::getType(Types::ARRAY));
        $column2 = new Column('foo', Type::getType(Types::OBJECT));

        self::assertFalse($this->comparator->columnsEqual($column1, $column2));
    }

    public function testForeignKeyRemovalWithRenamedLocalColumn(): void
    {
        $fromSchema = new Schema([
            'table1' => new Table(
                'table1',
                [
                    'id' => new Column('id', Type::getType(Types::INTEGER)),
                ],
            ),
            'table2' => new Table(
                'table2',
                [
                    'id' => new Column('id', Type::getType(Types::INTEGER)),
                    'id_table1' => new Column('id_table1', Type::getType(Types::INTEGER)),
                ],
                [],
                [],
                [
                    new ForeignKeyConstraint(['id_table1'], 'table1', ['id'], 'fk_table2_table1'),
                ],
            ),
        ]);
        $toSchema   = new Schema([
            'table2' => new Table(
                'table2',
                [
                    'id' => new Column('id', Type::getType(Types::INTEGER)),
                    'id_table3' => new Column('id_table3', Type::getType(Types::INTEGER)),
                ],
                [],
                [],
                [
                    new ForeignKeyConstraint(['id_table3'], 'table3', ['id'], 'fk_table2_table3'),
                ],
            ),
            'table3' => new Table(
                'table3',
                [
                    'id' => new Column('id', Type::getType(Types::INTEGER)),
                ],
            ),
        ]);
        $actual     = $this->comparator->compareSchemas($fromSchema, $toSchema);

        self::assertArrayHasKey('table2', $actual->changedTables);
        self::assertCount(1, $actual->orphanedForeignKeys);
        self::assertEquals('fk_table2_table1', $actual->orphanedForeignKeys[0]->getName());
        self::assertCount(1, $actual->changedTables['table2']->addedForeignKeys, 'FK to table3 should be added.');
        self::assertEquals('table3', $actual->changedTables['table2']->addedForeignKeys[0]->getForeignTableName());
    }

    public function testCallingCompareSchemasStaticallyIsDeprecated(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4707');
        Comparator::compareSchemas(new Schema(), new Schema());
    }

    public function testWillNotProduceSchemaDiffOnTableWithAddedCustomSchemaDefinition(): void
    {
        $fromSchema = new Schema(
            [
                new Table(
                    'a_table',
                    [
                        new Column('is_default', Type::getType(Types::STRING)),
                    ],
                ),
            ],
        );
        $toSchema   = new Schema(
            [
                new Table(
                    'a_table',
                    [
                        new Column(
                            'is_default',
                            Type::getType(Types::STRING),
                            ['columnDefinition' => 'ENUM(\'default\')'],
                        ),
                    ],
                ),
            ],
        );

        self::assertEmpty(
            $this->comparator->compareSchemas($fromSchema, $toSchema)
                ->changedTables,
            'Schema diff is empty, since only `columnDefinition` changed from `null` (not detected) to a defined one',
        );
    }

    public function testNoOrphanedForeignKeyIfReferencingTableIsDropped(): void
    {
        $schema1 = new Schema();

        $parent = $schema1->createTable('parent');
        $parent->addColumn('id', Types::INTEGER);

        $child = $schema1->createTable('child');
        $child->addColumn('id', Types::INTEGER);
        $child->addColumn('parent_id', Types::INTEGER);
        $child->addForeignKeyConstraint('parent', ['parent_id'], ['id']);

        $schema2 = new Schema();

        $diff = $this->comparator->compareSchemas($schema1, $schema2);

        self::assertEmpty($diff->orphanedForeignKeys);
    }
}

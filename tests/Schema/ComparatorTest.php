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

class ComparatorTest extends TestCase
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
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
                ],
            ),
        ]);
        $schema2 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
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
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
                ],
            ),
        ]);
        $schema2 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
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

        $table = new Table('bugdb', ['integercolumn1' => new Column('integercolumn1', Type::getType('integer'))]);
        $table->setSchemaConfig($schemaConfig);

        $schema1 = new Schema([$table], [], $schemaConfig);
        $schema2 = new Schema([], [], $schemaConfig);

        $expected = new SchemaDiff([], [], ['bugdb' => $table], $schema1);

        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareNewTable(): void
    {
        $schemaConfig = new SchemaConfig();

        $table = new Table('bugdb', ['integercolumn1' => new Column('integercolumn1', Type::getType('integer'))]);
        $table->setSchemaConfig($schemaConfig);

        $schema1 = new Schema([], [], $schemaConfig);
        $schema2 = new Schema([$table], [], $schemaConfig);

        $expected = new SchemaDiff(['bugdb' => $table], [], [], $schema1);

        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareOnlyAutoincrementChanged(): void
    {
        $column1 = new Column('foo', Type::getType('integer'), ['autoincrement' => true]);
        $column2 = new Column('foo', Type::getType('integer'), ['autoincrement' => false]);

        $changedProperties = $this->comparator->diffColumn($column1, $column2);

        self::assertEquals(['autoincrement'], $changedProperties);
    }

    public function testCompareMissingField(): void
    {
        $missingColumn = new Column('integercolumn1', Type::getType('integer'));
        $schema1       = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => $missingColumn,
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
                ],
            ),
        ]);
        $schema2       = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
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
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
                ],
            ),
        ]);
        $schema2 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
                ],
            ),
        ]);

        $expected                                    = new SchemaDiff(
            [],
            [
                'bugdb' => new TableDiff(
                    'bugdb',
                    [
                        'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
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
        $column1 = new Column('charcolumn1', Type::getType('string'));
        $column2 = new Column('charcolumn1', Type::getType('integer'));

        self::assertEquals(['type'], $this->comparator->diffColumn($column1, $column2));
        self::assertEquals([], $this->comparator->diffColumn($column1, $column1));
    }

    public function testCompareColumnsMultipleTypeInstances(): void
    {
        $integerType1 = Type::getType('integer');
        Type::overrideType('integer', get_class($integerType1));
        $integerType2 = Type::getType('integer');

        $column1 = new Column('integercolumn1', $integerType1);
        $column2 = new Column('integercolumn1', $integerType2);

        self::assertEquals([], $this->comparator->diffColumn($column1, $column2));
    }

    public function testCompareColumnsOverriddenType(): void
    {
        $oldStringInstance = Type::getType('string');
        $integerType       = Type::getType('integer');

        Type::overrideType('string', get_class($integerType));
        $overriddenStringType = Type::getType('string');

        Type::overrideType('string', get_class($oldStringInstance));

        $column1 = new Column('integercolumn1', $integerType);
        $column2 = new Column('integercolumn1', $overriddenStringType);

        self::assertEquals([], $this->comparator->diffColumn($column1, $column2));
    }

    public function testCompareChangedColumnsChangeCustomSchemaOption(): void
    {
        $column1 = new Column('charcolumn1', Type::getType('string'));
        $column2 = new Column('charcolumn1', Type::getType('string'));

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
        $tableA->addColumn('datecolumn1', 'datetime');

        $tableB = new Table('foo');
        $tableB->addColumn('new_datecolumn1', 'datetime');
        $tableB->addColumn('new_datecolumn2', 'datetime');

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
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
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
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
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
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
                ],
            ),
        ]);
        $schema2 = new Schema([
            'bugdb' => new Table(
                'bugdb',
                [
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
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
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
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
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
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
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
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
                    'integercolumn1' => new Column('integercolumn1', Type::getType('integer')),
                    'integercolumn2' => new Column('integercolumn2', Type::getType('integer')),
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
        $tableForeign->addColumn('id', 'integer');

        $table1 = new Table('foo');
        $table1->addColumn('fk', 'integer');

        $table2 = new Table('foo');
        $table2->addColumn('fk', 'integer');
        $table2->addForeignKeyConstraint($tableForeign, ['fk'], ['id']);

        $tableDiff = $this->comparator->diffTable($table1, $table2);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertCount(1, $tableDiff->addedForeignKeys);
    }

    public function testTableRemoveForeignKey(): void
    {
        $tableForeign = new Table('bar');
        $tableForeign->addColumn('id', 'integer');

        $table1 = new Table('foo');
        $table1->addColumn('fk', 'integer');

        $table2 = new Table('foo');
        $table2->addColumn('fk', 'integer');
        $table2->addForeignKeyConstraint($tableForeign, ['fk'], ['id']);

        $tableDiff = $this->comparator->diffTable($table2, $table1);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertCount(1, $tableDiff->removedForeignKeys);
    }

    public function testTableUpdateForeignKey(): void
    {
        $tableForeign = new Table('bar');
        $tableForeign->addColumn('id', 'integer');

        $table1 = new Table('foo');
        $table1->addColumn('fk', 'integer');
        $table1->addForeignKeyConstraint($tableForeign, ['fk'], ['id']);

        $table2 = new Table('foo');
        $table2->addColumn('fk', 'integer');
        $table2->addForeignKeyConstraint($tableForeign, ['fk'], ['id'], ['onUpdate' => 'CASCADE']);

        $tableDiff = $this->comparator->diffTable($table1, $table2);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertCount(1, $tableDiff->changedForeignKeys);
    }

    public function testMovedForeignKeyForeignTable(): void
    {
        $tableForeign = new Table('bar');
        $tableForeign->addColumn('id', 'integer');

        $tableForeign2 = new Table('bar2');
        $tableForeign2->addColumn('id', 'integer');

        $table1 = new Table('foo');
        $table1->addColumn('fk', 'integer');
        $table1->addForeignKeyConstraint($tableForeign, ['fk'], ['id']);

        $table2 = new Table('foo');
        $table2->addColumn('fk', 'integer');
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
        $tableA->addColumn('id', 'integer');

        $tableB = new Table('foo');
        $tableB->addColumn('ID', 'integer');

        $tableDiff = $this->comparator->diffTable($tableA, $tableB);

        self::assertFalse($tableDiff);
    }

    public function testCompareIndexBasedOnPropertiesNotName(): void
    {
        $tableA = new Table('foo');
        $tableA->addColumn('id', 'integer');
        $tableA->addIndex(['id'], 'foo_bar_idx');

        $tableB = new Table('foo');
        $tableB->addColumn('ID', 'integer');
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
        $tableA->addColumn('id', 'integer');
        $tableA->addForeignKeyConstraint('bar', ['id'], ['id'], [], 'foo_constraint');

        $tableB = new Table('foo');
        $tableB->addColumn('ID', 'integer');
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
        $tableA->addColumn('foo', 'integer');

        $tableB = new Table('foo');
        $tableB->addColumn('bar', 'integer');

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
        $tableA->addColumn('foo', 'integer');
        $tableA->addColumn('bar', 'integer');

        $tableB = new Table('foo');
        $tableB->addColumn('baz', 'integer');

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
        $table1->addColumn('foo', 'integer');

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
        $table1->addColumn('foo', 'integer');

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
        $tableA->addColumn('id', 'integer', ['autoincrement' => false]);

        $tableB = new Table('foo');
        $tableB->addColumn('id', 'integer', ['autoincrement' => true]);

        $tableDiff = $this->comparator->diffTable($tableA, $tableB);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertArrayHasKey('id', $tableDiff->changedColumns);
    }

    public function testDiff(): void
    {
        $table = new Table('twitter_users');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('twitterId', 'integer');
        $table->addColumn('displayName', 'string');
        $table->setPrimaryKey(['id']);

        $newtable = new Table('twitter_users');
        $newtable->addColumn('id', 'integer', ['autoincrement' => true]);
        $newtable->addColumn('twitter_id', 'integer');
        $newtable->addColumn('display_name', 'string');
        $newtable->addColumn('logged_in_at', 'datetime');
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
        $column = new Column('foo', Type::getType('decimal'));
        $column->setPrecision(null);

        $column2 = new Column('foo', Type::getType('decimal'));

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
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        $oldSchema->createSequence('foo_id_seq');

        $newSchema = new Schema();
        $table     = $newSchema->createTable('foo');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
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
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        $newSchema = new Schema();
        $table     = $newSchema->createTable('foo');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
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
        $tableA->addColumn('id', 'integer');

        $tableB = $oldSchema->createTable('table_b');
        $tableB->addColumn('id', 'integer');

        $tableC = $oldSchema->createTable('table_c');
        $tableC->addColumn('id', 'integer');
        $tableC->addColumn('table_a_id', 'integer');
        $tableC->addColumn('table_b_id', 'integer');

        $tableC->addForeignKeyConstraint($tableA, ['table_a_id'], ['id']);
        $tableC->addForeignKeyConstraint($tableB, ['table_b_id'], ['id']);

        $newSchema = new Schema();

        $tableB = $newSchema->createTable('table_b');
        $tableB->addColumn('id', 'integer');

        $tableC = $newSchema->createTable('table_c');
        $tableC->addColumn('id', 'integer');

        $schemaDiff = $this->comparator->compare($oldSchema, $newSchema);

        self::assertCount(1, $schemaDiff->changedTables['table_c']->removedForeignKeys);
        self::assertCount(1, $schemaDiff->orphanedForeignKeys);
    }

    public function testCompareChangedColumn(): void
    {
        $oldSchema = new Schema();

        $tableFoo = $oldSchema->createTable('foo');
        $tableFoo->addColumn('id', 'integer');

        $newSchema = new Schema();
        $table     = $newSchema->createTable('foo');
        $table->addColumn('id', 'string');

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
        $tableFoo->addColumn('id', 'binary');

        $newSchema = new Schema();
        $table     = $newSchema->createTable('foo');
        $table->addColumn('id', 'binary', ['length' => 42, 'fixed' => true]);

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
        $column1 = new Column('foo', Type::getType('string'), [
            'platformOptions' => [
                'foo' => 'foo',
                'bar' => 'bar',
            ],
        ]);

        $column2 = new Column('foo', Type::getType('string'), [
            'platformOptions' => [
                'foo' => 'foo',
                'foobar' => 'foobar',
            ],
        ]);

        $column3 = new Column('foo', Type::getType('string'), [
            'platformOptions' => [
                'foo' => 'foo',
                'bar' => 'rab',
            ],
        ]);

        $column4 = new Column('foo', Type::getType('string'));

        self::assertEquals([], $this->comparator->diffColumn($column1, $column2));
        self::assertEquals([], $this->comparator->diffColumn($column2, $column1));
        self::assertEquals(['bar'], $this->comparator->diffColumn($column1, $column3));
        self::assertEquals(['bar'], $this->comparator->diffColumn($column3, $column1));
        self::assertEquals([], $this->comparator->diffColumn($column1, $column4));
        self::assertEquals([], $this->comparator->diffColumn($column4, $column1));
    }

    public function testComplexDiffColumn(): void
    {
        $column1 = new Column('foo', Type::getType('string'), [
            'platformOptions' => ['foo' => 'foo'],
            'customSchemaOptions' => ['foo' => 'bar'],
        ]);

        $column2 = new Column('foo', Type::getType('string'), [
            'platformOptions' => ['foo' => 'bar'],
        ]);

        self::assertEquals([], $this->comparator->diffColumn($column1, $column2));
        self::assertEquals([], $this->comparator->diffColumn($column2, $column1));
    }

    public function testComparesNamespaces(): void
    {
        $fromSchema = $this->getMockBuilder(Schema::class)
            ->onlyMethods(['getNamespaces', 'hasNamespace'])
            ->getMock();
        $toSchema   = $this->getMockBuilder(Schema::class)
            ->onlyMethods(['getNamespaces', 'hasNamespace'])
            ->getMock();

        $fromSchema->expects(self::once())
            ->method('getNamespaces')
            ->willReturn(['foo', 'bar']);

        $fromSchema->method('hasNamespace')
            ->withConsecutive(['bar'], ['baz'])
            ->willReturnOnConsecutiveCalls(true, false);

        $toSchema->expects(self::once())
            ->method('getNamespaces')
            ->willReturn(['bar', 'baz']);

        $toSchema->method('hasNamespace')
            ->withConsecutive(['foo'], ['bar'])
            ->willReturnOnConsecutiveCalls(false, true);

        $expected                    = new SchemaDiff();
        $expected->fromSchema        = $fromSchema;
        $expected->newNamespaces     = ['baz' => 'baz'];
        $expected->removedNamespaces = ['foo' => 'foo'];

        self::assertEquals($expected, $this->comparator->compare($fromSchema, $toSchema));
    }

    public function testCompareGuidColumns(): void
    {
        $column1 = new Column('foo', Type::getType('guid'), ['comment' => 'GUID 1']);
        $column2 = new Column(
            'foo',
            Type::getType('guid'),
            ['notnull' => false, 'length' => '36', 'fixed' => true, 'default' => 'NEWID()', 'comment' => 'GUID 2.'],
        );

        self::assertEquals(['notnull', 'default', 'comment'], $this->comparator->diffColumn($column1, $column2));
        self::assertEquals(['notnull', 'default', 'comment'], $this->comparator->diffColumn($column2, $column1));
    }

    /** @dataProvider getCompareColumnComments */
    public function testCompareColumnComments(?string $comment1, ?string $comment2, bool $equals): void
    {
        $column1 = new Column('foo', Type::getType('integer'), ['comment' => $comment1]);
        $column2 = new Column('foo', Type::getType('integer'), ['comment' => $comment2]);

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
                    'id' => new Column('id', Type::getType('integer')),
                ],
            ),
            'table2' => new Table(
                'table2',
                [
                    'id' => new Column('id', Type::getType('integer')),
                    'id_table1' => new Column('id_table1', Type::getType('integer')),
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
                    'id' => new Column('id', Type::getType('integer')),
                    'id_table3' => new Column('id_table3', Type::getType('integer')),
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
                    'id' => new Column('id', Type::getType('integer')),
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
                        new Column('is_default', Type::getType('string')),
                    ],
                ),
            ],
        );
        $toSchema   = new Schema(
            [
                new Table(
                    'a_table',
                    [
                        new Column('is_default', Type::getType('string'), ['columnDefinition' => 'ENUM(\'default\')']),
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
        $parent->addColumn('id', 'integer');

        $child = $schema1->createTable('child');
        $child->addColumn('id', 'integer');
        $child->addColumn('parent_id', 'integer');
        $child->addForeignKeyConstraint('parent', ['parent_id'], ['id']);

        $schema2 = new Schema();

        $diff = $this->comparator->compareSchemas($schema1, $schema2);

        self::assertEmpty($diff->orphanedForeignKeys);
    }
}

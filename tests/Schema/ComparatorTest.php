<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\AbstractAsset;
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
use PHPUnit\Framework\TestCase;

use function array_keys;

abstract class ComparatorTest extends TestCase
{
    protected Comparator $comparator;

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

        self::assertEquals(
            new SchemaDiff([], [], [], [], [], [], [], []),
            $this->comparator->compareSchemas($schema1, $schema2),
        );
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

        self::assertEquals(
            new SchemaDiff([], [], [], [], [], [], [], []),
            $this->comparator->compareSchemas($schema1, $schema2),
        );
    }

    public function testCompareMissingTable(): void
    {
        $schemaConfig = new SchemaConfig();

        $table = new Table('bugdb', ['integercolumn1' => new Column('integercolumn1', Type::getType('integer'))]);
        $table->setSchemaConfig($schemaConfig);

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

        $table = new Table('bugdb', ['integercolumn1' => new Column('integercolumn1', Type::getType('integer'))]);
        $table->setSchemaConfig($schemaConfig);

        $schema1 = new Schema([], [], $schemaConfig);
        $schema2 = new Schema([$table], [], $schemaConfig);

        $expected = new SchemaDiff([], [], [$table], [], [], [], [], []);

        self::assertEquals($expected, $this->comparator->compareSchemas($schema1, $schema2));
    }

    public function testCompareAutoIncrementChanged(): void
    {
        $column1 = new Column('foo', Type::getType('integer'), ['autoincrement' => true]);
        $column2 = new Column('foo', Type::getType('integer'), ['autoincrement' => false]);

        $diff = new ColumnDiff($column2, $column1);

        self::assertTrue($diff->hasAutoIncrementChanged());
    }

    public function testCompareChangedColumnsChangeType(): void
    {
        $column1 = new Column('id', Type::getType(Types::STRING));
        $column2 = new Column('id', Type::getType(Types::INTEGER));

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

        $column1 = new Column('id', $type1);
        $column2 = new Column('id', $type2);

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

        $column1 = new Column('id', $integerType);
        $column2 = new Column('id', $overriddenStringType);

        $diff = new ColumnDiff($column2, $column1);
        self::assertFalse($diff->hasTypeChanged());
    }

    public function testCompareChangeColumnsMultipleNewColumnsRename(): void
    {
        $tableA = new Table('foo');
        $tableA->addColumn('datecolumn1', 'datetime');

        $tableB = new Table('foo');
        $tableB->addColumn('new_datecolumn1', 'datetime');
        $tableB->addColumn('new_datecolumn2', 'datetime');

        $tableDiff = $this->comparator->diffTable($tableA, $tableB);
        self::assertNotNull($tableDiff);

        $renamedColumns = $tableDiff->getRenamedColumns();
        self::assertCount(1, $renamedColumns);
        self::assertArrayHasKey('datecolumn1', $renamedColumns);
        self::assertEquals(['new_datecolumn2'], $this->getAssetNames($tableDiff->getAddedColumns()));

        self::assertCount(0, $tableDiff->getDroppedColumns());
        self::assertCount(0, $tableDiff->getModifiedColumns());
    }

    public function testCompareSequences(): void
    {
        $seq1 = new Sequence('foo', 1, 1);
        $seq2 = new Sequence('foo', 1, 2);
        $seq3 = new Sequence('foo', 2, 1);

        self::assertTrue($this->comparator->diffSequence($seq1, $seq2));
        self::assertTrue($this->comparator->diffSequence($seq1, $seq3));
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
        $tableForeign = new Table('bar');
        $tableForeign->addColumn('id', 'integer');

        $table1 = new Table('foo');
        $table1->addColumn('fk', 'integer');

        $table2 = new Table('foo');
        $table2->addColumn('fk', 'integer');
        $table2->addForeignKeyConstraint($tableForeign->getName(), ['fk'], ['id']);

        $tableDiff = $this->comparator->diffTable($table1, $table2);

        self::assertNotNull($tableDiff);
        self::assertCount(1, $tableDiff->getAddedForeignKeys());
    }

    public function testTableRemoveForeignKey(): void
    {
        $tableForeign = new Table('bar');
        $tableForeign->addColumn('id', 'integer');

        $table1 = new Table('foo');
        $table1->addColumn('fk', 'integer');

        $table2 = new Table('foo');
        $table2->addColumn('fk', 'integer');
        $table2->addForeignKeyConstraint($tableForeign->getName(), ['fk'], ['id']);

        $tableDiff = $this->comparator->diffTable($table2, $table1);

        self::assertNotNull($tableDiff);
        self::assertCount(1, $tableDiff->getDroppedForeignKeys());
    }

    public function testTableUpdateForeignKey(): void
    {
        $tableForeign = new Table('bar');
        $tableForeign->addColumn('id', 'integer');

        $table1 = new Table('foo');
        $table1->addColumn('fk', 'integer');
        $table1->addForeignKeyConstraint($tableForeign->getName(), ['fk'], ['id']);

        $table2 = new Table('foo');
        $table2->addColumn('fk', 'integer');
        $table2->addForeignKeyConstraint($tableForeign->getName(), ['fk'], ['id'], ['onUpdate' => 'CASCADE']);

        $tableDiff = $this->comparator->diffTable($table1, $table2);

        self::assertNotNull($tableDiff);
        self::assertCount(1, $tableDiff->getModifiedForeignKeys());
    }

    public function testMovedForeignKeyForeignTable(): void
    {
        $tableForeign = new Table('bar');
        $tableForeign->addColumn('id', 'integer');

        $tableForeign2 = new Table('bar2');
        $tableForeign2->addColumn('id', 'integer');

        $table1 = new Table('foo');
        $table1->addColumn('fk', 'integer');
        $table1->addForeignKeyConstraint($tableForeign->getName(), ['fk'], ['id']);

        $table2 = new Table('foo');
        $table2->addColumn('fk', 'integer');
        $table2->addForeignKeyConstraint($tableForeign2->getName(), ['fk'], ['id']);

        $tableDiff = $this->comparator->diffTable($table1, $table2);

        self::assertNotNull($tableDiff);
        self::assertCount(1, $tableDiff->getModifiedForeignKeys());
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
        $tableA = new Table('foo');
        $tableA->addColumn('id', 'integer');

        $tableB = new Table('foo');
        $tableB->addColumn('ID', 'integer');

        $tableDiff = $this->comparator->diffTable($tableA, $tableB);

        self::assertNull($tableDiff);
    }

    public function testCompareIndexBasedOnPropertiesNotName(): void
    {
        $tableA = new Table('foo');
        $tableA->addColumn('id', 'integer');
        $tableA->addIndex(['id'], 'foo_bar_idx');

        $tableB = new Table('foo');
        $tableB->addColumn('ID', 'integer');
        $tableB->addIndex(['id'], 'bar_foo_idx');

        self::assertEquals(
            new TableDiff($tableA, [], [], [], [], [], [], [], [
                'foo_bar_idx' => new Index('bar_foo_idx', ['id']),
            ], [], [], []),
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

        self::assertNull($tableDiff);
    }

    public function testDetectRenameColumn(): void
    {
        $tableA = new Table('foo');
        $tableA->addColumn('foo', 'integer');

        $tableB = new Table('foo');
        $tableB->addColumn('bar', 'integer');

        $tableDiff = $this->comparator->diffTable($tableA, $tableB);
        self::assertNotNull($tableDiff);

        self::assertCount(0, $tableDiff->getAddedColumns());
        self::assertCount(0, $tableDiff->getDroppedColumns());

        $renamedColumns = $tableDiff->getRenamedColumns();
        self::assertArrayHasKey('foo', $renamedColumns);
        self::assertEquals('bar', $renamedColumns['foo']->getName());
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
        self::assertNotNull($tableDiff);

        self::assertEquals(['baz'], $this->getAssetNames($tableDiff->getAddedColumns()));
        self::assertEquals(['foo', 'bar'], $this->getAssetNames($tableDiff->getDroppedColumns()));
        self::assertCount(0, $tableDiff->getRenamedColumns());
    }

    public function testDetectRenameIndex(): void
    {
        $table1 = new Table('foo');
        $table1->addColumn('foo', 'integer');

        $table2 = clone $table1;

        $table1->addIndex(['foo'], 'idx_foo');

        $table2->addIndex(['foo'], 'idx_bar');

        $tableDiff = $this->comparator->diffTable($table1, $table2);
        self::assertNotNull($tableDiff);

        self::assertCount(0, $tableDiff->getAddedColumns());
        self::assertCount(0, $tableDiff->getDroppedIndexes());

        $renamedIndexes = $tableDiff->getRenamedIndexes();
        self::assertArrayHasKey('idx_foo', $renamedIndexes);
        self::assertEquals('idx_bar', $renamedIndexes['idx_foo']->getName());
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
        self::assertNotNull($tableDiff);

        self::assertEquals(['idx_baz'], $this->getAssetNames($tableDiff->getAddedIndexes()));
        self::assertEquals(['idx_foo', 'idx_bar'], $this->getAssetNames($tableDiff->getDroppedIndexes()));
        self::assertCount(0, $tableDiff->getRenamedIndexes());
    }

    public function testDetectChangeIdentifierType(): void
    {
        $tableA = new Table('foo');
        $tableA->addColumn('id', 'integer', ['autoincrement' => false]);

        $tableB = new Table('foo');
        $tableB->addColumn('id', 'integer', ['autoincrement' => true]);

        $tableDiff = $this->comparator->diffTable($tableA, $tableB);

        self::assertNotNull($tableDiff);

        $modifiedColumns = $tableDiff->getModifiedColumns();
        self::assertCount(1, $modifiedColumns);
        self::assertEquals('id', $modifiedColumns[0]->getOldColumn()->getName());
    }

    public function testDiff(): void
    {
        $table = new Table('twitter_users');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('twitterId', 'integer');
        $table->addColumn('displayName', 'string', ['length' => 32]);
        $table->setPrimaryKey(['id']);

        $newtable = new Table('twitter_users');
        $newtable->addColumn('id', 'integer', ['autoincrement' => true]);
        $newtable->addColumn('twitter_id', 'integer');
        $newtable->addColumn('display_name', 'string', ['length' => 32]);
        $newtable->addColumn('logged_in_at', 'datetime');
        $newtable->setPrimaryKey(['id']);

        $tableDiff = $this->comparator->diffTable($table, $newtable);

        self::assertNotNull($tableDiff);
        self::assertEquals(['twitterId', 'displayName'], array_keys($tableDiff->getRenamedColumns()));
        self::assertEquals(['logged_in_at'], $this->getAssetNames($tableDiff->getAddedColumns()));
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

        $oldSchema = new Schema([], [], $config);
        $oldSchema->createTable('bar');

        $newSchema = new Schema([], [], $config);
        $newSchema->createTable('foo.bar');

        self::assertEquals(
            new SchemaDiff([], [], [], [], [], [], [], []),
            $this->comparator->compareSchemas($oldSchema, $newSchema),
        );
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
        $oldSchema = new Schema([], [], $config);
        $oldSchema->createTable('bar');

        $newSchema = new Schema();
        $newSchema->createTable('bar');

        self::assertEquals(
            new SchemaDiff([], [], [], [], [], [], [], []),
            $this->comparator->compareSchemas($oldSchema, $newSchema),
        );
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

        $diff = $this->comparator->compareSchemas($oldSchema, $newSchema);

        self::assertCount(0, $diff->getDroppedSequences());
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

        $diff = $this->comparator->compareSchemas($oldSchema, $newSchema);

        self::assertCount(0, $diff->getCreatedSequences());
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

        $diff = $this->comparator->compareSchemas($fromSchema, $toSchema);

        self::assertEquals(['baz'], $diff->getCreatedSchemas());
        self::assertEquals(['foo'], $diff->getDroppedSchemas());
    }

    /** @dataProvider getCompareColumnComments */
    public function testCompareColumnComments(string $comment1, string $comment2, bool $equals): void
    {
        $column1 = new Column('foo', Type::getType('integer'), ['comment' => $comment1]);
        $column2 = new Column('foo', Type::getType('integer'), ['comment' => $comment2]);

        $diff1 = new ColumnDiff($column2, $column1);
        $diff2 = new ColumnDiff($column1, $column2);

        self::assertSame(! $equals, $diff1->hasCommentChanged());
        self::assertSame(! $equals, $diff2->hasCommentChanged());
    }

    /** @return mixed[][] */
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

        $schemaDiff = $this->comparator->compareSchemas($fromSchema, $toSchema);

        $alteredTables = $schemaDiff->getAlteredTables();
        self::assertCount(1, $alteredTables);

        $addedForeignKeys = $alteredTables[0]->getAddedForeignKeys();
        self::assertCount(1, $addedForeignKeys, 'FK to table3 should be added.');
        self::assertEquals('table3', $addedForeignKeys[0]->getForeignTableName());
    }

    public function testWillNotProduceSchemaDiffOnTableWithAddedCustomSchemaDefinition(): void
    {
        $fromSchema = new Schema(
            [
                new Table(
                    'a_table',
                    [
                        new Column(
                            'is_default',
                            Type::getType('string'),
                            ['length' => 32],
                        ),
                    ],
                ),
            ],
        );
        $toSchema   = new Schema(
            [
                new Table(
                    'a_table',
                    [
                        new Column('is_default', Type::getType('string'), [
                            'columnDefinition' => 'ENUM(\'default\')',
                            'length' => 32,
                        ]),
                    ],
                ),
            ],
        );

        self::assertEmpty(
            $this->comparator->compareSchemas($fromSchema, $toSchema)
                ->getAlteredTables(),
            'Schema diff is empty, since only `columnDefinition` changed from `null` (not detected) to a defined one',
        );
    }

    /**
     * @param array<AbstractAsset> $assets
     *
     * @return array<string>
     */
    protected function getAssetNames(array $assets): array
    {
        $names = [];

        foreach ($assets as $asset) {
            $names[] = $asset->getName();
        }

        return $names;
    }
}

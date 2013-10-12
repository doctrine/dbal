<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\Tests\DBAL\Schema;

require_once __DIR__ . '/../../TestInit.php';

use Doctrine\DBAL\Schema\Schema,
    Doctrine\DBAL\Schema\SchemaConfig,
    Doctrine\DBAL\Schema\Table,
    Doctrine\DBAL\Schema\Column,
    Doctrine\DBAL\Schema\Index,
    Doctrine\DBAL\Schema\Sequence,
    Doctrine\DBAL\Schema\SchemaDiff,
    Doctrine\DBAL\Schema\TableDiff,
    Doctrine\DBAL\Schema\ColumnDiff,
    Doctrine\DBAL\Schema\Comparator,
    Doctrine\DBAL\Types\Type,
    Doctrine\DBAL\Schema\ForeignKeyConstraint;

/**
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @copyright Copyright (C) 2005-2009 eZ Systems AS. All rights reserved.
 * @license http://ez.no/licenses/new_bsd New BSD License
 * @since   2.0
 * @version $Revision$
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 */
class ComparatorTest extends \PHPUnit_Framework_TestCase
{
    public function testCompareSame1()
    {
        $schema1 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer' ) ),
                )
            ),
        ) );
        $schema2 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer') ),
                )
            ),
        ) );

        $expected = new SchemaDiff();
        $expected->setFromSchema($schema1);
        $this->assertEquals($expected, Comparator::compareSchemas( $schema1, $schema2 ) );
    }

    public function testCompareSame2()
    {
        $schema1 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer')),
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                )
            ),
        ) );
        $schema2 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                    'integerfield1' => new Column('integerfield1', Type::getType('integer')),
                )
            ),
        ) );

        $expected = new SchemaDiff();
        $expected->setFromSchema($schema1);
        $this->assertEquals($expected, Comparator::compareSchemas( $schema1, $schema2 ) );
    }

    public function testCompareMissingTable()
    {
        $schemaConfig = new \Doctrine\DBAL\Schema\SchemaConfig;
        $table = new Table('bugdb', array ('integerfield1' => new Column('integerfield1', Type::getType('integer'))));
        $table->setSchemaConfig($schemaConfig);

        $schema1 = new Schema( array($table), array(), $schemaConfig );
        $schema2 = new Schema( array(),       array(), $schemaConfig );

        $expected = new SchemaDiff( array(), array(), array('bugdb' => $table), $schema1 );

        $this->assertEquals($expected, Comparator::compareSchemas( $schema1, $schema2 ) );
    }

    public function testCompareNewTable()
    {
        $schemaConfig = new \Doctrine\DBAL\Schema\SchemaConfig;
        $table = new Table('bugdb', array ('integerfield1' => new Column('integerfield1', Type::getType('integer'))));
        $table->setSchemaConfig($schemaConfig);

        $schema1 = new Schema( array(),       array(), $schemaConfig );
        $schema2 = new Schema( array($table), array(), $schemaConfig );

        $expected = new SchemaDiff( array('bugdb' => $table), array(), array(), $schema1 );

        $this->assertEquals($expected, Comparator::compareSchemas( $schema1, $schema2 ) );
    }

    public function testCompareOnlyAutoincrementChanged()
    {
        $column1 = new Column('foo', Type::getType('integer'), array('autoincrement' => true));
        $column2 = new Column('foo', Type::getType('integer'), array('autoincrement' => false));

        $comparator = new Comparator();
        $changedProperties = $comparator->diffColumn($column1, $column2);

        $this->assertEquals(array('autoincrement'), $changedProperties);
    }

    public function testCompareMissingField()
    {
        $missingColumn = new Column('integerfield1', Type::getType('integer'));
        $schema1 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => $missingColumn,
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                )
            ),
        ) );
        $schema2 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                )
            ),
        ) );

        $expected = new SchemaDiff ( array(),
            array (
                'bugdb' => new TableDiff( 'bugdb', array(), array(),
                    array (
                        'integerfield1' => $missingColumn,
                    )
                )
            )
        );
        $expected->setFromSchema($schema1);
        $changedTables                     = $expected->getChangedTables();
        $changedTables['bugdb']->setFromTable($schema1->getTable('bugdb'));

        $this->assertEquals($expected, Comparator::compareSchemas( $schema1, $schema2 ) );
    }

    public function testCompareNewField()
    {
        $schema1 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer')),
                )
            ),
        ) );
        $schema2 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer')),
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                )
            ),
        ) );

        $expected = new SchemaDiff ( array(),
            array (
                'bugdb' => new TableDiff ('bugdb',
                    array (
                        'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                    )
                ),
            )
        );
        $expected->setFromSchema($schema1);
        $changedTables                     = $expected->getChangedTables();
        $changedTables['bugdb']->setFromTable($schema1->getTable('bugdb'));

        $this->assertEquals($expected, Comparator::compareSchemas( $schema1, $schema2 ) );
    }

    public function testCompareChangedColumns_ChangeType()
    {
        $column1 = new Column('charfield1', Type::getType('string'));
        $column2 = new Column('charfield1', Type::getType('integer'));

        $c = new Comparator();
        $this->assertEquals(array('type'), $c->diffColumn($column1, $column2));
        $this->assertEquals(array(), $c->diffColumn($column1, $column1));
    }

    public function testCompareChangedColumns_ChangeCustomSchemaOption()
    {
        $column1 = new Column('charfield1', Type::getType('string'));
        $column2 = new Column('charfield1', Type::getType('string'));

        $column1->setCustomSchemaOption('foo', 'bar');
        $column2->setCustomSchemaOption('foo', 'bar');

        $column1->setCustomSchemaOption('foo1', 'bar1');
        $column2->setCustomSchemaOption('foo2', 'bar2');

        $c = new Comparator();
        $this->assertEquals(array('foo1', 'foo2'), $c->diffColumn($column1, $column2));
        $this->assertEquals(array(), $c->diffColumn($column1, $column1));
    }

    public function testCompareChangeColumns_MultipleNewColumnsRename()
    {
        $tableA = new Table("foo");
        $tableA->addColumn('datefield1', 'datetime');

        $tableB = new Table("foo");
        $tableB->addColumn('new_datefield1', 'datetime');
        $tableB->addColumn('new_datefield2', 'datetime');

        $c = new Comparator();
        $tableDiff = $c->diffTable($tableA, $tableB);

        $this->assertCount(1, $tableDiff->getRenamedColumns(), "we should have one rename datefield1 => new_datefield1.");
        $this->assertArrayHasKey('datefield1', $tableDiff->getRenamedColumns(), "'datefield1' should be set to be renamed to new_datefield1");
        $this->assertCount(1, $tableDiff->getAddedColumns(), "'new_datefield2' should be added");
        $this->assertArrayHasKey('new_datefield2', $tableDiff->getAddedColumns(), "'new_datefield2' should be added, not created through renaming!");
        $this->assertCount(0, $tableDiff->getRemovedColumns(), "Nothing should be removed.");
        $this->assertCount(0, $tableDiff->getChangedColumns(), "Nothing should be changed as all fields old & new have diff names.");
    }

    public function testCompareRemovedIndex()
    {
        $schema1 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer')),
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                ),
                array (
                    'primary' => new Index('primary',
                        array(
                            'integerfield1'
                        ),
                        true
                    )
                )
            ),
        ) );
        $schema2 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer')),
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                )
            ),
        ) );

        $expected = new SchemaDiff ( array(),
            array (
                'bugdb' => new TableDiff( 'bugdb', array(), array(), array(), array(), array(),
                    array (
                        'primary' => new Index('primary',
                        array(
                            'integerfield1'
                        ),
                        true
                    )
                    )
                ),
            )
        );
        $expected->setFromSchema($schema1);
        $changedTables                     = $expected->getChangedTables();
        $changedTables['bugdb']->setFromTable($schema1->getTable('bugdb'));

        $this->assertEquals($expected, Comparator::compareSchemas( $schema1, $schema2 ) );
    }

    public function testCompareNewIndex()
    {
        $schema1 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer')),
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                )
            ),
        ) );
        $schema2 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer')),
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                ),
                array (
                    'primary' => new Index('primary',
                        array(
                            'integerfield1'
                        ),
                        true
                    )
                )
            ),
        ) );

        $expected = new SchemaDiff ( array(),
            array (
                'bugdb' => new TableDiff( 'bugdb', array(), array(), array(),
                    array (
                        'primary' => new Index('primary',
                            array(
                                'integerfield1'
                            ),
                            true
                        )
                    )
                ),
            )
        );
        $expected->setFromSchema($schema1);
        $changedTables                     = $expected->getChangedTables();
        $changedTables['bugdb']->setFromTable($schema1->getTable('bugdb'));
        $expected->setChangedTables($changedTables);

        $this->assertEquals($expected, Comparator::compareSchemas( $schema1, $schema2 ) );
    }

    public function testCompareChangedIndex()
    {
        $schema1 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer')),
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                ),
                array (
                    'primary' => new Index('primary',
                        array(
                            'integerfield1'
                        ),
                        true
                    )
                )
            ),
        ) );
        $schema2 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer')),
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                ),
                array (
                    'primary' => new Index('primary',
                        array('integerfield1', 'integerfield2'),
                        true
                    )
                )
            ),
        ) );

        $expected = new SchemaDiff ( array(),
            array (
                'bugdb' => new TableDiff( 'bugdb', array(), array(), array(), array(),
                    array (
                        'primary' => new Index('primary',
                            array(
                                'integerfield1',
                                'integerfield2'
                            ),
                            true
                        )
                    )
                ),
            )
        );
        $expected->setFromSchema($schema1);
        $changedTables                     = $expected->getChangedTables();
        $changedTables['bugdb']->setFromTable($schema1->getTable('bugdb'));
        $expected->setChangedTables($changedTables);

        $this->assertEquals($expected, Comparator::compareSchemas( $schema1, $schema2 ));
    }

    public function testCompareChangedIndexFieldPositions()
    {
        $schema1 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer')),
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                ),
                array (
                    'primary' => new Index('primary', array('integerfield1', 'integerfield2'), true)
                )
            ),
        ) );
        $schema2 = new Schema( array(
            'bugdb' => new Table('bugdb',
                array (
                    'integerfield1' => new Column('integerfield1', Type::getType('integer')),
                    'integerfield2' => new Column('integerfield2', Type::getType('integer')),
                ),
                array (
                    'primary' => new Index('primary', array('integerfield2', 'integerfield1'), true)
                )
            ),
        ) );

        $expected = new SchemaDiff ( array(),
            array (
                'bugdb' => new TableDiff('bugdb', array(), array(), array(), array(),
                    array (
                        'primary' => new Index('primary', array('integerfield2', 'integerfield1'), true)
                    )
                ),
            )
        );
        $expected->setFromSchema($schema1);
        $changedTables                     = $expected->getChangedTables();
        $changedTables['bugdb']->setFromTable($schema1->getTable('bugdb'));
        $expected->setChangedTables($changedTables);

        $this->assertEquals($expected, Comparator::compareSchemas( $schema1, $schema2 ));
    }

    public function testCompareSequences()
    {
        $seq1 = new Sequence('foo', 1, 1);
        $seq2 = new Sequence('foo', 1, 2);
        $seq3 = new Sequence('foo', 2, 1);

        $c = new Comparator();

        $this->assertTrue($c->diffSequence($seq1, $seq2));
        $this->assertTrue($c->diffSequence($seq1, $seq3));
    }

    public function testRemovedSequence()
    {
        $schema1 = new Schema();
        $seq = $schema1->createSequence('foo');

        $schema2 = new Schema();

        $c = new Comparator();
        $diffSchema = $c->compare($schema1, $schema2);

        $sequences = $diffSchema->getRemovedSequences();
        $this->assertEquals(1, count($sequences));
        $this->assertSame($seq, $sequences[0]);
    }

    public function testAddedSequence()
    {
        $schema1 = new Schema();

        $schema2 = new Schema();
        $seq = $schema2->createSequence('foo');

        $c = new Comparator();
        $diffSchema = $c->compare($schema1, $schema2);

        $sequences = $diffSchema->getNewSequences();
        $this->assertEquals(1, count($sequences));
        $this->assertSame($seq, $sequences[0]);
    }

    public function testTableAddForeignKey()
    {
        $tableForeign = new Table("bar");
        $tableForeign->addColumn('id', 'integer');

        $table1 = new Table("foo");
        $table1->addColumn('fk', 'integer');

        $table2 = new Table("foo");
        $table2->addColumn('fk', 'integer');
        $table2->addForeignKeyConstraint($tableForeign, array('fk'), array('id'));

        $c = new Comparator();
        $tableDiff = $c->diffTable($table1, $table2);

        $this->assertInstanceOf('Doctrine\DBAL\Schema\TableDiff', $tableDiff);
        $this->assertEquals(1, count($tableDiff->getAddedForeignKeys()));
    }

    public function testTableRemoveForeignKey()
    {
        $tableForeign = new Table("bar");
        $tableForeign->addColumn('id', 'integer');

        $table1 = new Table("foo");
        $table1->addColumn('fk', 'integer');

        $table2 = new Table("foo");
        $table2->addColumn('fk', 'integer');
        $table2->addForeignKeyConstraint($tableForeign, array('fk'), array('id'));

        $c = new Comparator();
        $tableDiff = $c->diffTable($table2, $table1);

        $this->assertInstanceOf('Doctrine\DBAL\Schema\TableDiff', $tableDiff);
        $this->assertEquals(1, count($tableDiff->getRemovedForeignKeys()));
    }

    public function testTableUpdateForeignKey()
    {
        $tableForeign = new Table("bar");
        $tableForeign->addColumn('id', 'integer');

        $table1 = new Table("foo");
        $table1->addColumn('fk', 'integer');
        $table1->addForeignKeyConstraint($tableForeign, array('fk'), array('id'));

        $table2 = new Table("foo");
        $table2->addColumn('fk', 'integer');
        $table2->addForeignKeyConstraint($tableForeign, array('fk'), array('id'), array('onUpdate' => 'CASCADE'));

        $c = new Comparator();
        $tableDiff = $c->diffTable($table1, $table2);

        $this->assertInstanceOf('Doctrine\DBAL\Schema\TableDiff', $tableDiff);
        $this->assertEquals(1, count($tableDiff->getChangedForeignKeys()));
    }

    public function testMovedForeignKeyForeignTable()
    {
        $tableForeign = new Table("bar");
        $tableForeign->addColumn('id', 'integer');

        $tableForeign2 = new Table("bar2");
        $tableForeign2->addColumn('id', 'integer');

        $table1 = new Table("foo");
        $table1->addColumn('fk', 'integer');
        $table1->addForeignKeyConstraint($tableForeign, array('fk'), array('id'));

        $table2 = new Table("foo");
        $table2->addColumn('fk', 'integer');
        $table2->addForeignKeyConstraint($tableForeign2, array('fk'), array('id'));

        $c = new Comparator();
        $tableDiff = $c->diffTable($table1, $table2);

        $this->assertInstanceOf('Doctrine\DBAL\Schema\TableDiff', $tableDiff);
        $this->assertEquals(1, count($tableDiff->getChangedForeignKeys()));
    }

    public function testTablesCaseInsensitive()
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

        $c = new Comparator();
        $diff = $c->compare($schemaA, $schemaB);

        $this->assertSchemaTableChangeCount($diff, 1, 0, 1);
    }

    public function testSequencesCaseInsenstive()
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

        $c = new Comparator();
        $diff = $c->compare($schemaA, $schemaB);

        $this->assertSchemaSequenceChangeCount($diff, 1, 0, 1);
    }

    public function testCompareColumnCompareCaseInsensitive()
    {
        $tableA = new Table("foo");
        $tableA->addColumn('id', 'integer');

        $tableB = new Table("foo");
        $tableB->addColumn('ID', 'integer');

        $c = new Comparator();
        $tableDiff = $c->diffTable($tableA, $tableB);

        $this->assertFalse($tableDiff);
    }

    public function testCompareIndexBasedOnPropertiesNotName()
    {
        $tableA = new Table("foo");
        $tableA->addColumn('id', 'integer');
        $tableA->addIndex(array("id"), "foo_bar_idx");

        $tableB = new Table("foo");
        $tableB->addColumn('ID', 'integer');
        $tableB->addIndex(array("id"), "bar_foo_idx");

        $c = new Comparator();
        $tableDiff = $c->diffTable($tableA, $tableB);

        $this->assertFalse($tableDiff);
    }

    public function testCompareForeignKeyBasedOnPropertiesNotName()
    {
        $tableA = new Table("foo");
        $tableA->addColumn('id', 'integer');
        $tableA->addNamedForeignKeyConstraint('foo_constraint', 'bar', array('id'), array('id'));

        $tableB = new Table("foo");
        $tableB->addColumn('ID', 'integer');
        $tableB->addNamedForeignKeyConstraint('bar_constraint', 'bar', array('id'), array('id'));

        $c = new Comparator();
        $tableDiff = $c->diffTable($tableA, $tableB);

        $this->assertFalse($tableDiff);
    }

    public function testCompareForeignKey_RestrictNoAction_AreTheSame()
    {
        $fk1 = new ForeignKeyConstraint(array("foo"), "bar", array("baz"), "fk1", array('onDelete' => 'NO ACTION'));
        $fk2 = new ForeignKeyConstraint(array("foo"), "bar", array("baz"), "fk1", array('onDelete' => 'RESTRICT'));

        $c = new Comparator();
        $this->assertFalse($c->diffForeignKey($fk1, $fk2));
    }

    /**
     * @group DBAL-492
     */
    public function testCompareForeignKeyNamesUnqualified_AsNoSchemaInformationIsAvailable()
    {
        $fk1 = new ForeignKeyConstraint(array("foo"), "foo.bar", array("baz"), "fk1");
        $fk2 = new ForeignKeyConstraint(array("foo"), "baz.bar", array("baz"), "fk1");

        $c = new Comparator();
        $this->assertFalse($c->diffForeignKey($fk1, $fk2));
    }

    public function testDetectRenameColumn()
    {
        $tableA = new Table("foo");
        $tableA->addColumn('foo', 'integer');

        $tableB = new Table("foo");
        $tableB->addColumn('bar', 'integer');

        $c = new Comparator();
        $tableDiff = $c->diffTable($tableA, $tableB);

        $this->assertEquals(0, count($tableDiff->getAddedColumns()));
        $this->assertEquals(0, count($tableDiff->getRemovedColumns()));
        $renamedColumns = $tableDiff->getRenamedColumns();
        $this->assertArrayHasKey('foo', $renamedColumns);
        $this->assertEquals('bar', $renamedColumns['foo']->getName());
    }

    /**
     * You can easily have ambiguouties in the column renaming. If these
     * are detected no renaming should take place, instead adding and dropping
     * should be used exclusively.
     *
     * @group DBAL-24
     */
    public function testDetectRenameColumnAmbiguous()
    {
        $tableA = new Table("foo");
        $tableA->addColumn('foo', 'integer');
        $tableA->addColumn('bar', 'integer');

        $tableB = new Table("foo");
        $tableB->addColumn('baz', 'integer');

        $c = new Comparator();
        $tableDiff = $c->diffTable($tableA, $tableB);

        $this->assertEquals(1, count($tableDiff->getAddedColumns()), "'baz' should be added, not created through renaming!");
        $this->assertArrayHasKey('baz', $tableDiff->getAddedColumns(), "'baz' should be added, not created through renaming!");
        $this->assertEquals(2, count($tableDiff->getRemovedColumns()), "'foo' and 'bar' should both be dropped, an ambigouty exists which one could be renamed to 'baz'.");
        $this->assertArrayHasKey('foo', $tableDiff->getRemovedColumns(), "'foo' should be removed.");
        $this->assertArrayHasKey('bar', $tableDiff->getRemovedColumns(), "'bar' should be removed.");
        $this->assertEquals(0, count($tableDiff->getRenamedColumns()), "no renamings should take place.");
    }

    public function testDetectChangeIdentifierType()
    {
        $this->markTestSkipped('DBAL-2 was reopened, this test cannot work anymore.');

        $tableA = new Table("foo");
        $tableA->addColumn('id', 'integer', array('autoincrement' => false));

        $tableB = new Table("foo");
        $tableB->addColumn('id', 'integer', array('autoincrement' => true));

        $c = new Comparator();
        $tableDiff = $c->diffTable($tableA, $tableB);

        $this->assertInstanceOf('Doctrine\DBAL\Schema\TableDiff', $tableDiff);
        $this->assertArrayHasKey('id', $tableDiff->getChangedColumns());
    }


    /**
     * @group DBAL-105
     */
    public function testDiff()
    {
        $table = new \Doctrine\DBAL\Schema\Table('twitter_users');
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('twitterId', 'integer', array('nullable' => false));
        $table->addColumn('displayName', 'string', array('nullable' => false));
        $table->setPrimaryKey(array('id'));

        $newtable = new \Doctrine\DBAL\Schema\Table('twitter_users');
        $newtable->addColumn('id', 'integer', array('autoincrement' => true));
        $newtable->addColumn('twitter_id', 'integer', array('nullable' => false));
        $newtable->addColumn('display_name', 'string', array('nullable' => false));
        $newtable->addColumn('logged_in_at', 'datetime', array('nullable' => true));
        $newtable->setPrimaryKey(array('id'));

        $c = new Comparator();
        $tableDiff = $c->diffTable($table, $newtable);

        $this->assertInstanceOf('Doctrine\DBAL\Schema\TableDiff', $tableDiff);
        $this->assertEquals(array('twitterid', 'displayname'), array_keys($tableDiff->getRenamedColumns()));
        $this->assertEquals(array('logged_in_at'), array_keys($tableDiff->getAddedColumns()));
        $this->assertEquals(0, count($tableDiff->getRemovedColumns()));
    }


    /**
     * @group DBAL-112
     */
    public function testChangedSequence()
    {
        $schema = new Schema();
        $sequence = $schema->createSequence('baz');

        $schemaNew = clone $schema;
        /* @var $schemaNew Schema */
        $schemaNew->getSequence('baz')->setAllocationSize(20);

        $c = new \Doctrine\DBAL\Schema\Comparator;
        $diff = $c->compare($schema, $schemaNew);

        $changedSequences = $diff->getChangedSequences();
        $this->assertSame($changedSequences[0] , $schemaNew->getSequence('baz'));
    }

    /**
     * @group DBAL-106
     */
    public function testDiffDecimalWithNullPrecision()
    {
        $column = new Column('foo', Type::getType('decimal'));
        $column->setPrecision(null);

        $column2 = new Column('foo', Type::getType('decimal'));

        $c = new Comparator();
        $this->assertEquals(array(), $c->diffColumn($column, $column2));
    }

    /**
     * @group DBAL-204
     */
    public function testFqnSchemaComparision()
    {
        $config = new SchemaConfig();
        $config->setName("foo");

        $oldSchema = new Schema(array(), array(), $config);
        $oldSchema->createTable('bar');

        $newSchema= new Schema(array(), array(), $config);
        $newSchema->createTable('foo.bar');

        $expected = new SchemaDiff();
        $expected->setFromSchema($oldSchema);

        $this->assertEquals($expected, Comparator::compareSchemas($oldSchema, $newSchema));
    }

    /**
     * @group DBAL-204
     */
    public function testFqnSchemaComparisionDifferentSchemaNameButSameTableNoDiff()
    {
        $config = new SchemaConfig();
        $config->setName("foo");

        $oldSchema = new Schema(array(), array(), $config);
        $oldSchema->createTable('foo.bar');

        $newSchema = new Schema();
        $newSchema->createTable('bar');

        $expected = new SchemaDiff();
        $expected->setFromSchema($oldSchema);

        $this->assertEquals($expected, Comparator::compareSchemas($oldSchema, $newSchema));
    }

    /**
     * @group DBAL-204
     */
    public function testFqnSchemaComparisionNoSchemaSame()
    {
        $config = new SchemaConfig();
        $config->setName("foo");
        $oldSchema = new Schema(array(), array(), $config);
        $oldSchema->createTable('bar');

        $newSchema = new Schema();
        $newSchema->createTable('bar');

        $expected = new SchemaDiff();
        $expected->setFromSchema($oldSchema);

        $this->assertEquals($expected, Comparator::compareSchemas($oldSchema, $newSchema));
    }

    /**
     * @group DDC-1657
     */
    public function testAutoIncremenetSequences()
    {
        $oldSchema = new Schema();
        $table = $oldSchema->createTable("foo");
        $table->addColumn("id", "integer", array("autoincrement" => true));
        $table->setPrimaryKey(array("id"));
        $oldSchema->createSequence("foo_id_seq");

        $newSchema = new Schema();
        $table = $newSchema->createTable("foo");
        $table->addColumn("id", "integer", array("autoincrement" => true));
        $table->setPrimaryKey(array("id"));

        $c = new Comparator();
        $diff = $c->compare($oldSchema, $newSchema);

        $this->assertCount(0, $diff->getRemovedSequences());
    }


    /**
     * You can get multiple drops for a FK when a table referenced by a foreign
     * key is deleted, as this FK is referenced twice, once on the orphanedForeignKeys
     * array because of the dropped table, and once on changedTables array. We
     * now check that the key is present once.
     */
    public function testAvoidMultipleDropForeignKey()
    {
        $oldSchema = new Schema();

        $tableForeign = $oldSchema->createTable('foreign');
        $tableForeign->addColumn('id', 'integer');

        $table = $oldSchema->createTable('foo');
        $table->addColumn('fk', 'integer');
        $table->addForeignKeyConstraint($tableForeign, array('fk'), array('id'));


        $newSchema = new Schema();
        $table = $newSchema->createTable('foo');

        $c = new Comparator();
        $diff = $c->compare($oldSchema, $newSchema);

        $changedTables = $diff->getChangedTables();
        $this->assertCount(0, $changedTables['foo']->getRemovedForeignKeys());
        $this->assertCount(1, $diff->getOrphanedForeignKeys());
    }

    public function testCompareChangedColumn()
    {
        $oldSchema = new Schema();

        $tableFoo = $oldSchema->createTable('foo');
        $tableFoo->addColumn('id', 'integer');

        $newSchema = new Schema();
        $table = $newSchema->createTable('foo');
        $table->addColumn('id', 'string');

        $expected = new SchemaDiff();
        $expected->setFromSchema($oldSchema);
        $changedTables                     = $expected->getChangedTables();
        $tableDiff = $changedTables['foo'] = new TableDiff('foo');
        $tableDiff->setFromTable($tableFoo);
        $expected->setChangedTables($changedTables);

        $changedColumns                     = $tableDiff->getChangedColumns();
        $columnDiff = $changedColumns['id'] = new ColumnDiff('id', $table->getColumn('id'));
        $columnDiff->setFromColumn($tableFoo->getColumn('id'));
        $columnDiff->setChangedProperties(array('type'));
        $tableDiff->setChangedColumns($changedColumns);

        $this->assertEquals($expected, Comparator::compareSchemas($oldSchema, $newSchema));
    }

    /**
     * @param SchemaDiff $diff
     * @param int $newTableCount
     * @param int $changeTableCount
     * @param int $removeTableCount
     */
    public function assertSchemaTableChangeCount($diff, $newTableCount=0, $changeTableCount=0, $removeTableCount=0)
    {
        $this->assertEquals($newTableCount, count($diff->getNewTables()));
        $this->assertEquals($changeTableCount, count($diff->getChangedTables()));
        $this->assertEquals($removeTableCount, count($diff->getRemovedTables()));
    }

    /**
     * @param SchemaDiff $diff
     * @param int $newSequenceCount
     * @param int $changeSequenceCount
     * @param int $removeSequenceCount
     */
    public function assertSchemaSequenceChangeCount($diff, $newSequenceCount=0, $changeSequenceCount=0, $removeSequenceCount=0)
    {
        $this->assertEquals($newSequenceCount, count($diff->getNewSequences()), "Expected number of new sequences is wrong.");
        $this->assertEquals($changeSequenceCount, count($diff->getChangedSequences()), "Expected number of changed sequences is wrong.");
        $this->assertEquals($removeSequenceCount, count($diff->getRemovedSequences()), "Expected number of removed sequences is wrong.");
    }
}

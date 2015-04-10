<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

require_once __DIR__ . '/../../../TestInit.php';

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;


class FirebirdSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    
    public function testListSequences()
    {
        // Override the standard test with an simplified test, because firebird does not allow
        // to specify the increment by value, thus allocation-size must always be 1
        if(!$this->_conn->getDatabasePlatform()->supportsSequences()) {
            $this->markTestSkipped($this->_conn->getDriver()->getName().' does not support sequences.');
        }

        $sequence = new \Doctrine\DBAL\Schema\Sequence('list_sequences_test_seq', 1, 1);
        $this->_sm->createSequence($sequence);

        $sequences = $this->_sm->listSequences();

        $this->assertInternalType('array', $sequences, 'listSequences() should return an array.');

        $foundSequence = null;
        foreach($sequences as $sequence) {
            $this->assertInstanceOf('Doctrine\DBAL\Schema\Sequence', $sequence, 'Array elements of listSequences() should be Sequence instances.');
            if(strtolower($sequence->getName()) == 'list_sequences_test_seq') {
                $foundSequence = $sequence;
            }
        }

        $this->assertNotNull($foundSequence, "Sequence with name 'list_sequences_test_seq' was not found.");
        $this->assertEquals(1, $foundSequence->getAllocationSize(), "Allocation Size is expected to be 1.");
        $this->assertEquals(1, $foundSequence->getInitialValue(), "Initial Value is expected to be 1.");
    }
    
    
    /**
     * @group DBAL-1095
     * 
     * Firebird does not allow identifiers longer than 31, thus the original test fails.
     * Reimplemented with shorter table names
     * 
     * Firebird converts all identifers to uppercase if not quoted
     */
    public function testDoesNotListIndexesImplicitlyCreatedByForeignKeys()
    {
        if (! $this->_sm->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $this->markTestSkipped('This test is only supported on platforms that have foreign keys.');
        }

        $primaryTable = new Table('test_list_index_implc_primary');
        $primaryTable->addColumn('id', 'integer');
        $primaryTable->setPrimaryKey(array('id'));

        $foreignTable = new Table('test_list_index_implc_foreign');
        $foreignTable->addColumn('fk1', 'integer');
        $foreignTable->addColumn('fk2', 'integer');
        $foreignTable->addIndex(array('fk1'), 'explicit_fk1_idx');
        $foreignTable->addForeignKeyConstraint('test_list_index_implc_primary', array('fk1'), array('id'));
        $foreignTable->addForeignKeyConstraint('test_list_index_implc_primary', array('fk2'), array('id'));

        $this->_sm->dropAndCreateTable($primaryTable);
        $this->_sm->dropAndCreateTable($foreignTable);

        $indexes = $this->_sm->listTableIndexes('test_list_index_implc_foreign');

        $this->assertCount(2, $indexes);
        $this->assertArrayHasKey('explicit_fk1_idx', $indexes);
        $this->assertArrayHasKey('idx_abaeec4ffdc58d6c', $indexes);
    }
    
    
    

}

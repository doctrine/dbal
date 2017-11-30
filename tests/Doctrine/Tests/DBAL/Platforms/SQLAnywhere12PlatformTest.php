<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLAnywhere12Platform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;

class SQLAnywhere12PlatformTest extends SQLAnywhere11PlatformTest
{
    /**
     * @var \Doctrine\DBAL\Platforms\SQLAnywhere12Platform
     */
    protected $_platform;

    public function createPlatform()
    {
        return new SQLAnywhere12Platform;
    }

    public function testDoesNotSupportSequences()
    {
        $this->markTestSkipped('This version of the platform now supports sequences.');
    }

    public function testSupportsSequences()
    {
        self::assertTrue($this->_platform->supportsSequences());
    }

    public function testGeneratesSequenceSqlCommands()
    {
        $sequence = new Sequence('myseq', 20, 1);
        self::assertEquals(
            'CREATE SEQUENCE myseq INCREMENT BY 20 START WITH 1 MINVALUE 1',
            $this->_platform->getCreateSequenceSQL($sequence)
        );
        self::assertEquals(
            'ALTER SEQUENCE myseq INCREMENT BY 20',
            $this->_platform->getAlterSequenceSQL($sequence)
        );
        self::assertEquals(
            'DROP SEQUENCE myseq',
            $this->_platform->getDropSequenceSQL('myseq')
        );
        self::assertEquals(
            'DROP SEQUENCE myseq',
            $this->_platform->getDropSequenceSQL($sequence)
        );
        self::assertEquals(
            "SELECT myseq.NEXTVAL",
            $this->_platform->getSequenceNextValSQL('myseq')
        );
        self::assertEquals(
            'SELECT sequence_name, increment_by, start_with, min_value FROM SYS.SYSSEQUENCE',
            $this->_platform->getListSequencesSQL(null)
        );
    }

    public function testGeneratesDateTimeTzColumnTypeDeclarationSQL()
    {
        self::assertEquals(
            'TIMESTAMP WITH TIME ZONE',
            $this->_platform->getDateTimeTzTypeDeclarationSQL(array(
                'length' => 10,
                'fixed' => true,
                'unsigned' => true,
                'autoincrement' => true
            ))
        );
    }

    public function testHasCorrectDateTimeTzFormatString()
    {
        self::assertEquals('Y-m-d H:i:s.uP', $this->_platform->getDateTimeTzFormatString());
    }

    public function testInitializesDateTimeTzTypeMapping()
    {
        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('timestamp with time zone'));
        self::assertEquals('datetime', $this->_platform->getDoctrineTypeMapping('timestamp with time zone'));
    }

    public function testGeneratesCreateIndexWithAdvancedPlatformOptionsSQL()
    {
        self::assertEquals(
            'CREATE VIRTUAL UNIQUE CLUSTERED INDEX fooindex ON footable (a, b) WITH NULLS NOT DISTINCT FOR OLAP WORKLOAD',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    array('a', 'b'),
                    true,
                    false,
                    array('virtual', 'clustered', 'with_nulls_not_distinct', 'for_olap_workload')
                ),
                'footable'
            )
        );
        self::assertEquals(
            'CREATE VIRTUAL CLUSTERED INDEX fooindex ON footable (a, b) FOR OLAP WORKLOAD',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    array('a', 'b'),
                    false,
                    false,
                    array('virtual', 'clustered', 'with_nulls_not_distinct', 'for_olap_workload')
                ),
                'footable'
            )
        );

        // WITH NULLS NOT DISTINCT clause not available on primary indexes.
        self::assertEquals(
            'ALTER TABLE footable ADD PRIMARY KEY (a, b)',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    array('a', 'b'),
                    false,
                    true,
                    array('with_nulls_not_distinct')
                ),
                'footable'
            )
        );

        // WITH NULLS NOT DISTINCT clause not available on non-unique indexes.
        self::assertEquals(
            'CREATE INDEX fooindex ON footable (a, b)',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    array('a', 'b'),
                    false,
                    false,
                    array('with_nulls_not_distinct')
                ),
                'footable'
            )
        );
    }
}

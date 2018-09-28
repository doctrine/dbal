<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLAnywhere12Platform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;

class SQLAnywhere12PlatformTest extends SQLAnywhere11PlatformTest
{
    /** @var SQLAnywhere12Platform */
    protected $platform;

    public function createPlatform()
    {
        return new SQLAnywhere12Platform();
    }

    public function testDoesNotSupportSequences()
    {
        $this->markTestSkipped('This version of the platform now supports sequences.');
    }

    public function testSupportsSequences()
    {
        self::assertTrue($this->platform->supportsSequences());
    }

    public function testGeneratesSequenceSqlCommands()
    {
        $sequence = new Sequence('myseq', 20, 1);
        self::assertEquals(
            'CREATE SEQUENCE myseq INCREMENT BY 20 START WITH 1 MINVALUE 1',
            $this->platform->getCreateSequenceSQL($sequence)
        );
        self::assertEquals(
            'ALTER SEQUENCE myseq INCREMENT BY 20',
            $this->platform->getAlterSequenceSQL($sequence)
        );
        self::assertEquals(
            'DROP SEQUENCE myseq',
            $this->platform->getDropSequenceSQL('myseq')
        );
        self::assertEquals(
            'DROP SEQUENCE myseq',
            $this->platform->getDropSequenceSQL($sequence)
        );
        self::assertEquals(
            'SELECT myseq.NEXTVAL',
            $this->platform->getSequenceNextValSQL('myseq')
        );
        self::assertEquals(
            'SELECT sequence_name, increment_by, start_with, min_value FROM SYS.SYSSEQUENCE',
            $this->platform->getListSequencesSQL(null)
        );
    }

    public function testGeneratesDateTimeTzColumnTypeDeclarationSQL()
    {
        self::assertEquals(
            'TIMESTAMP WITH TIME ZONE',
            $this->platform->getDateTimeTzTypeDeclarationSQL([
                'length' => 10,
                'fixed' => true,
                'unsigned' => true,
                'autoincrement' => true,
            ])
        );
    }

    public function testHasCorrectDateTimeTzFormatString()
    {
        self::assertEquals('Y-m-d H:i:s.uP', $this->platform->getDateTimeTzFormatString());
    }

    public function testInitializesDateTimeTzTypeMapping()
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('timestamp with time zone'));
        self::assertEquals('datetime', $this->platform->getDoctrineTypeMapping('timestamp with time zone'));
    }

    public function testGeneratesCreateIndexWithAdvancedPlatformOptionsSQL()
    {
        self::assertEquals(
            'CREATE VIRTUAL UNIQUE CLUSTERED INDEX fooindex ON footable (a, b) WITH NULLS NOT DISTINCT FOR OLAP WORKLOAD',
            $this->platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    true,
                    false,
                    ['virtual', 'clustered', 'with_nulls_not_distinct', 'for_olap_workload']
                ),
                'footable'
            )
        );
        self::assertEquals(
            'CREATE VIRTUAL CLUSTERED INDEX fooindex ON footable (a, b) FOR OLAP WORKLOAD',
            $this->platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    false,
                    false,
                    ['virtual', 'clustered', 'with_nulls_not_distinct', 'for_olap_workload']
                ),
                'footable'
            )
        );

        // WITH NULLS NOT DISTINCT clause not available on primary indexes.
        self::assertEquals(
            'ALTER TABLE footable ADD PRIMARY KEY (a, b)',
            $this->platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    false,
                    true,
                    ['with_nulls_not_distinct']
                ),
                'footable'
            )
        );

        // WITH NULLS NOT DISTINCT clause not available on non-unique indexes.
        self::assertEquals(
            'CREATE INDEX fooindex ON footable (a, b)',
            $this->platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    false,
                    false,
                    ['with_nulls_not_distinct']
                ),
                'footable'
            )
        );
    }
}

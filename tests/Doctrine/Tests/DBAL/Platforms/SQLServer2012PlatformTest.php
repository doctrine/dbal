<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Schema\Sequence;

class SQLServer2012PlatformTest extends SQLServerPlatformTest
{
    public function createPlatform()
    {
        return new SQLServer2012Platform;
    }

    public function testSupportsSequences()
    {
        $this->assertTrue($this->_platform->supportsSequences());
    }

    public function testDoesNotPreferSequences()
    {
        $this->assertFalse($this->_platform->prefersSequences());
    }

    public function testGeneratesSequenceSqlCommands()
    {
        $sequence = new Sequence('myseq', 20, 1);
        $this->assertEquals(
            'CREATE SEQUENCE myseq START WITH 1 INCREMENT BY 20 MINVALUE 1',
            $this->_platform->getCreateSequenceSQL($sequence)
        );
        $this->assertEquals(
            'ALTER SEQUENCE myseq INCREMENT BY 20',
            $this->_platform->getAlterSequenceSQL($sequence)
        );
        $this->assertEquals(
            'DROP SEQUENCE myseq',
            $this->_platform->getDropSequenceSQL('myseq')
        );
        $this->assertEquals(
            "SELECT NEXT VALUE FOR myseq",
            $this->_platform->getSequenceNextValSQL('myseq')
        );
    }
}

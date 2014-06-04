<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLAnywhere16Platform;
use Doctrine\DBAL\Schema\Index;

class SQLAnywhere16PlatformTest extends SQLAnywhere12PlatformTest
{
    public function createPlatform()
    {
        return new SQLAnywhere16Platform;
    }

    public function testGeneratesCreateIndexWithAdvancedPlatformOptionsSQL()
    {
        $this->assertEquals(
            'CREATE UNIQUE INDEX fooindex ON footable (a, b) WITH NULLS DISTINCT',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    array('a', 'b'),
                    true,
                    false,
                    array('with_nulls_distinct')
                ),
                'footable'
            )
        );

        // WITH NULLS DISTINCT clause not available on primary indexes.
        $this->assertEquals(
            'ALTER TABLE footable ADD PRIMARY KEY (a, b)',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    array('a', 'b'),
                    false,
                    true,
                    array('with_nulls_distinct')
                ),
                'footable'
            )
        );

        // WITH NULLS DISTINCT clause not available on non-unique indexes.
        $this->assertEquals(
            'CREATE INDEX fooindex ON footable (a, b)',
            $this->_platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    array('a', 'b'),
                    false,
                    false,
                    array('with_nulls_distinct')
                ),
                'footable'
            )
        );

        parent::testGeneratesCreateIndexWithAdvancedPlatformOptionsSQL();
    }

    public function testThrowsExceptionOnInvalidWithNullsNotDistinctIndexOptions()
    {
        $this->setExpectedException('UnexpectedValueException');

        $this->_platform->getCreateIndexSQL(
            new Index(
                'fooindex',
                array('a', 'b'),
                false,
                false,
                array('with_nulls_distinct', 'with_nulls_not_distinct')
            ),
            'footable'
        );
    }
}

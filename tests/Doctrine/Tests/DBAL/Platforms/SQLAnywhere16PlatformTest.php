<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLAnywhere16Platform;
use Doctrine\DBAL\Schema\Index;

class SQLAnywhere16PlatformTest extends SQLAnywhere12PlatformTest
{
    public function createPlatform()
    {
        return new SQLAnywhere16Platform();
    }

    public function testGeneratesCreateIndexWithAdvancedPlatformOptionsSQL()
    {
        self::assertEquals(
            'CREATE UNIQUE INDEX fooindex ON footable (a, b) WITH NULLS DISTINCT',
            $this->platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    true,
                    false,
                    ['with_nulls_distinct']
                ),
                'footable'
            )
        );

        // WITH NULLS DISTINCT clause not available on primary indexes.
        self::assertEquals(
            'ALTER TABLE footable ADD PRIMARY KEY (a, b)',
            $this->platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    false,
                    true,
                    ['with_nulls_distinct']
                ),
                'footable'
            )
        );

        // WITH NULLS DISTINCT clause not available on non-unique indexes.
        self::assertEquals(
            'CREATE INDEX fooindex ON footable (a, b)',
            $this->platform->getCreateIndexSQL(
                new Index(
                    'fooindex',
                    ['a', 'b'],
                    false,
                    false,
                    ['with_nulls_distinct']
                ),
                'footable'
            )
        );

        parent::testGeneratesCreateIndexWithAdvancedPlatformOptionsSQL();
    }

    public function testThrowsExceptionOnInvalidWithNullsNotDistinctIndexOptions()
    {
        $this->expectException('UnexpectedValueException');

        $this->platform->getCreateIndexSQL(
            new Index(
                'fooindex',
                ['a', 'b'],
                false,
                false,
                ['with_nulls_distinct', 'with_nulls_not_distinct']
            ),
            'footable'
        );
    }
}

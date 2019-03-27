<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Schema\Table;

class PostgreSqlPlatformTest extends AbstractPostgreSqlPlatformTestCase
{
    public function createPlatform() : AbstractPlatform
    {
        return new PostgreSqlPlatform();
    }

    public function testSupportsPartialIndexes() : void
    {
        self::assertTrue($this->platform->supportsPartialIndexes());
    }

    public function testGetCreateTableSQLWithColumnCollation() : void
    {
        $table = new Table('foo');
        $table->addColumn('id', 'string');
        $table->addOption('comment', 'foo');
        self::assertSame(
            [
                'CREATE TABLE foo (id VARCHAR(255) NOT NULL)',
                "COMMENT ON TABLE foo IS 'foo'",
            ],
            $this->platform->getCreateTableSQL($table),
            'Comments are added to table.'
        );
    }
}

<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\PostgreSQL91Platform;
use Doctrine\DBAL\Schema\Table;

class PostgreSQL91PlatformTest extends PostgreSqlPlatformTest
{
    public function createPlatform()
    {
        return new PostgreSQL91Platform();
    }

    public function testSupportsColumnCollation() : void
    {
        self::assertTrue($this->platform->supportsColumnCollation());
    }

    public function testColumnCollationDeclarationSQL() : void
    {
        self::assertSame(
            'COLLATE "en_US.UTF-8"',
            $this->platform->getColumnCollationDeclarationSQL('en_US.UTF-8')
        );
    }

    public function testGetCreateTableSQLWithColumnCollation() : void
    {
        $table = new Table('foo');
        $table->addColumn('no_collation', 'string');
        $table->addColumn('column_collation', 'string')->setPlatformOption('collation', 'en_US.UTF-8');

        self::assertSame(
            ['CREATE TABLE foo (no_collation VARCHAR(255) NOT NULL, column_collation VARCHAR(255) NOT NULL COLLATE "en_US.UTF-8")'],
            $this->platform->getCreateTableSQL($table),
            'Column "no_collation" will use the default collation from the table/database and "column_collation" overwrites the collation on this column'
        );
    }
}

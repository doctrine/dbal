<?php

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class ConcatTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDatabasePlatform() instanceof MySQLPlatform) {
            return;
        }

        if ($this->connection->getDatabasePlatform() instanceof SQLServer2012Platform) {
            return;
        }

        self::markTestSkipped('Restricted to MySQL and SQL Server.');
    }

    public function testConcat(): void
    {
        $table = new Table('concat_test');
        $table->addColumn('name', 'string');
        $this->connection->getSchemaManager()->dropAndCreateTable($table);
        $this->connection->insert('concat_test', ['name' => 'A long string']);
        $this->connection->insert('concat_test', ['name' => 'A short string']);

        $platform = $this->connection->getDatabasePlatform();
        $query    = $this->connection->fetchOne(
            \sprintf(
                'SELECT name FROM concat_test WHERE name LIKE %s',
                $platform->getConcatExpression("'%'", "'long'", "'%'")
            )
        );

        self::assertEquals('A long string', $query);
    }
}

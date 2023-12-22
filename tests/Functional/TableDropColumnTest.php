<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use Throwable;

use function count;

class TableDropColumnTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = new Table('write_table');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('test_column1', Types::STRING);
        $table->addColumn('test_column2', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['test_column1', 'test_column2']);

        $this->dropAndCreateTable($table);

        $this->connection->executeStatement('ALTER TABLE write_table DROP COLUMN test_column1');
    }

    public function testPgSqlPgAttributeTable(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (!$platform instanceof PostgreSQLPlatform) {
            self::markTestSkipped('Test does work on PostgreSQL only.');
        }

        try {
            $this->connection->executeQuery('Select attisdropped from pg_attribute Limit 1')->fetchOne();
        } catch (Throwable $e) {
            self::fail("Column attisdropped not exists in pg_attribute table");
        }
    }

    public function testColumnNumber(): void
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns('write_table');

        self::assertEquals(2, count($columns), 'listTableColumns() should return the number of exact number of columns');
    }
}

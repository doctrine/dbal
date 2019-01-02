<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\Tests\DbalFunctionalTestCase;
use function extension_loaded;
use function in_array;

class PostgreSqlListTableFunctionsTest extends DbalFunctionalTestCase
{
    protected function setUp() : void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql is not loaded.');

            return;
        }

        parent::setUp();

        if (! in_array('pdo_pgsql', DriverManager::getAvailableDrivers())) {
            $this->markTestSkipped('PostgreSQL driver not available');

            return;
        }

        if (! $this->connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            $this->markTestSkipped('PostgreSQL Only test.');

            return;
        }

        $this->connection->executeQuery('CREATE SCHEMA test_schema1;');
        $this->connection->executeQuery('CREATE SCHEMA test_schema2;');
        $this->connection->executeQuery('set search_path = "test_schema1", "test_schema2";');
        $this->connection->executeQuery('CREATE TABLE test_schema1.test_foreign1 ( id integer PRIMARY KEY );');
        $this->connection->executeQuery('CREATE TABLE test_schema1.test_table ( test_column1 varchar(5), test_foreign1_id integer constraint fk_test_foreign1 references test_schema1.test_foreign1 (id) );');
        $this->connection->executeQuery('CREATE INDEX idx_test_column1 ON test_schema1.test_table (test_column1);');
        $this->connection->executeQuery('CREATE TABLE test_schema2.test_foreign2 ( id integer PRIMARY KEY );');
        $this->connection->executeQuery('CREATE TABLE test_schema2.test_table ( test_column2 varchar(5), test_foreign2_id integer constraint fk_test_foreign2 references test_schema2.test_foreign2 (id) );');
        $this->connection->executeQuery('CREATE INDEX idx_test_column2 ON test_schema2.test_table (test_column2);');
    }

    public function testListTableFunctions() : void
    {
        $foreignKeys = $this->connection->getSchemaManager()->listTableForeignKeys('test_table');
        $this->assertNotEmpty($foreignKeys);
        $foreignKeys = $this->connection->getSchemaManager()->listTableForeignKeys('test_schema1.test_table');
        $columns     = isset($foreignKeys[0]) ? $foreignKeys[0]->getColumns() : [];
        $this->assertContains('test_foreign1_id', $columns);
        $foreignKeys = $this->connection->getSchemaManager()->listTableForeignKeys('test_schema2.test_table');
        $columns     = isset($foreignKeys[0]) ? $foreignKeys[0]->getColumns() : [];
        $this->assertContains('test_foreign2_id', $columns);

        $columns = $this->connection->getSchemaManager()->listTableColumns('test_table');
        $this->assertNotEmpty($columns);
        $columns = $this->connection->getSchemaManager()->listTableColumns('test_schema1.test_table');
        $this->assertArrayHasKey('test_column1', $columns);
        $columns = $this->connection->getSchemaManager()->listTableColumns('test_schema2.test_table');
        $this->assertArrayHasKey('test_column2', $columns);

        $indexes = $this->connection->getSchemaManager()->listTableIndexes('test_table');
        $this->assertNotEmpty($indexes);
        $indexes = $this->connection->getSchemaManager()->listTableIndexes('test_schema1.test_table');
        $this->assertArrayHasKey('idx_test_column1', $indexes);
        $indexes = $this->connection->getSchemaManager()->listTableIndexes('test_schema2.test_table');
        $this->assertArrayHasKey('idx_test_column2', $indexes);
    }
}

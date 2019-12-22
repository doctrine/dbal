<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalFunctionalTestCase;
use function in_array;
use function sprintf;

class IndexTest extends DbalFunctionalTestCase
{
    /** @var AbstractSchemaManager */
    private $schemaManager;

    protected function setUp()
    {
        parent::setUp();

        $this->schemaManager = $this->connection->getSchemaManager();
    }

    public function testCreateIndexWithDotInItsName() : void
    {
        $indexName  = 'my.index.name';
        $tableName  = 'add_index_with_dots';
        $columnName = 'col';
        $table      = new Table($tableName);
        $table->addColumn($columnName, Type::INTEGER);
        $this->schemaManager->createTable($table);
        $platformName = $this->connection->getDatabasePlatform()->getName();
        if (in_array($platformName, ['postgresql', 'mssql', 'db2'])) {
            $this->connection->query(sprintf('CREATE INDEX "%s" ON %s ("%s")', $indexName, $tableName, $columnName));
        } else {
            $this->connection->query(sprintf('CREATE INDEX `%s` ON %s (`%s`)', $indexName, $tableName, $columnName));
        }
        $table = $this->schemaManager->listTableDetails($tableName);
        self::assertTrue($table->hasIndex($indexName));
    }
}

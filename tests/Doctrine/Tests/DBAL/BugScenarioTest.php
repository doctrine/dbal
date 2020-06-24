<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

final class BugScenarioTest extends DbalFunctionalTestCase
{
    public function setUp() : void
    {
        parent::setUp();

        $schemaManager = $this->connection->getSchemaManager();
        $schema        = $schemaManager->createSchema();

        $originalTable = $schema->createTable('to_be_altered');
        $column        = $originalTable->addColumn('some_column_name', Types::TEXT);
        $column->setNotnull(false);

        $schemaManager->createTable($originalTable);
    }

    public function testInvalidQueryIsGenerated() : void
    {
        $schemaManager = $this->connection->getSchemaManager();
        $schema        = $schemaManager->createSchema();

        $originalTable = $schema->getTable('to_be_altered');
        $modifiedTable = clone $originalTable;

        $column = $modifiedTable->getColumn('some_column_name');
        $column->setType(Type::getType(Types::INTEGER));

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($originalTable, $modifiedTable);

        $schemaManager->alterTable($tableDiff);
    }
}

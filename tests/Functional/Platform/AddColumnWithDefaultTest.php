<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class AddColumnWithDefaultTest extends FunctionalTestCase
{
    public function testAddColumnWithDefault(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        $table = new Table('add_default_test');

        $table->addColumn('original_field', Types::STRING, ['length' => 8]);
        $this->dropAndCreateTable($table);

        $this->connection->executeStatement("INSERT INTO add_default_test (original_field) VALUES ('one')");

        $table->addColumn('new_field', Types::STRING, [
            'length' => 8,
            'default' => 'DEFAULT',
        ]);

        $diff = $schemaManager->createComparator()->compareTables(
            $schemaManager->introspectTable('add_default_test'),
            $table,
        );

        $schemaManager->alterTable($diff);

        $query  = 'SELECT original_field, new_field FROM add_default_test';
        $result = $this->connection->fetchNumeric($query);
        self::assertSame(['one', 'DEFAULT'], $result);
    }
}

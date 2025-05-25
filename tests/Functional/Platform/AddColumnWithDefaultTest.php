<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class AddColumnWithDefaultTest extends FunctionalTestCase
{
    public function testAddColumnWithDefault(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        $table = Table::editor()
            ->setUnquotedName('add_default_test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('original_field')
                    ->setTypeName(Types::STRING)
                    ->setLength(8)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->connection->executeStatement("INSERT INTO add_default_test (original_field) VALUES ('one')");

        $table = $table->edit()
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('new_field')
                    ->setTypeName(Types::STRING)
                    ->setLength(8)
                    ->setDefaultValue('DEFAULT')
                    ->create(),
            )
            ->create();

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

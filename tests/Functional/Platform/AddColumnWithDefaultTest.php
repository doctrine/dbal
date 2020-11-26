<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

class AddColumnWithDefaultTest extends FunctionalTestCase
{
    public function testAddColumnWithDefault(): void
    {
        $schemaManager = $this->connection->getSchemaManager();

        $table = new Table('add_default_test');

        $table->addColumn('original_field', Types::STRING, ['length' => 8]);
        $schemaManager->dropAndCreateTable($table);

        $this->connection->executeStatement("INSERT INTO add_default_test (original_field) VALUES ('one')");

        $tableDiff                      = new TableDiff('add_default_test');
        $tableDiff->fromTable           = $table;
        $tableDiff->addedColumns['foo'] = new Column('new_field', Type::getType('string'), [
            'length' => 8,
            'default' => 'DEFAULT',
        ]);

        $schemaManager->alterTable($tableDiff);

        $query  = 'SELECT original_field, new_field FROM add_default_test';
        $result = $this->connection->executeQuery($query)->fetch(FetchMode::NUMERIC);
        self::assertSame(['one', 'DEFAULT'], $result);
    }
}

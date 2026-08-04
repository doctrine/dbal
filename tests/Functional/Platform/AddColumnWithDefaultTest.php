<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\TestWith;

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
            $schemaManager->introspectTableByUnquotedName('add_default_test'),
            $table,
        );

        $schemaManager->alterTable($diff);

        $query  = 'SELECT original_field, new_field FROM add_default_test';
        $result = $this->connection->fetchNumeric($query);
        self::assertSame(['one', 'DEFAULT'], $result);
    }

    #[TestWith([Types::DATETIME_MUTABLE], Types::DATETIME_MUTABLE)]
    #[TestWith([Types::DATETIME_IMMUTABLE], Types::DATETIME_IMMUTABLE)]
    public function testAddColumnWithCurrentTimestampDefaultToPopulatedTable(string $type): void
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

        $this->connection->insert('add_default_test', ['original_field' => 'one']);

        $table = $table->edit()
            ->addColumn(
                Column::editor()
                    ->setUnquotedName('created_at')
                    ->setTypeName($type)
                    ->setDefaultValue(new CurrentTimestamp())
                    ->create(),
            )
            ->create();

        $diff = $schemaManager->createComparator()->compareTables(
            $schemaManager->introspectTableByUnquotedName('add_default_test'),
            $table,
        );

        // A non-constant default such as CURRENT_TIMESTAMP cannot be applied with
        // ALTER TABLE ... ADD COLUMN on a non-empty table on SQLite; the platform must
        // therefore fall back to rebuilding the table instead of generating invalid SQL.
        $schemaManager->alterTable($diff);

        $createdAt = $this->connection->fetchOne('SELECT created_at FROM add_default_test');
        self::assertNotNull($createdAt);
        self::assertNotSame('', $createdAt);
    }
}

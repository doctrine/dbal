<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class AlterColumnTest extends FunctionalTestCase
{
    public function testColumnPositionRetainedAfterAltering(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_alter')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('c1')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('c2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $table = $table->edit()
            ->modifyColumnByUnquotedName('c1', static function (ColumnEditor $editor): void {
                $editor
                    ->setTypeName(Types::STRING)
                    ->setLength(16);
            })
            ->create();

        $sm   = $this->connection->createSchemaManager();
        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTable('test_alter'), $table);

        $sm->alterTable($diff);

        $table   = $sm->introspectTable('test_alter');
        $columns = $table->getColumns();

        self::assertCount(2, $columns);
        self::assertEqualsIgnoringCase('c1', $columns[0]->getName());
        self::assertEqualsIgnoringCase('c2', $columns[1]->getName());
    }

    public function testSupportsCollations(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestSkipped('This test covers PostgreSQL-specific schema comparison scenarios.');
        }

        $table = Table::editor()
            ->setUnquotedName('test_alter')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('c1')
                    ->setTypeName(Types::STRING)
                    ->setCollation('en_US.utf8')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('c2')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $sm   = $this->connection->createSchemaManager();
        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTable('test_alter'), $table);

        self::assertTrue($diff->isEmpty());
    }

    public function testSupportsIcuCollationProviders(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestSkipped('This test covers PostgreSQL-specific schema comparison scenarios.');
        }

        $hasIcuCollations = $this->connection->fetchOne(
            "SELECT 1 FROM pg_collation WHERE collprovider = 'icu'",
        ) !== false;

        if (! $hasIcuCollations) {
            self::markTestSkipped('This test requires ICU collations to be available.');
        }

        $table = Table::editor()
            ->setUnquotedName('test_alter')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('c1')
                    ->setTypeName(Types::STRING)
                    ->setCollation('en-US-x-icu')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $sm   = $this->connection->createSchemaManager();
        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTable('test_alter'), $table);

        self::assertTrue($diff->isEmpty());
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

class AlterColumnTest extends FunctionalTestCase
{
    public function testColumnPositionRetainedAfterAltering(): void
    {
        $table = new Table('test_alter');
        $table->addColumn('c1', Types::INTEGER);
        $table->addColumn('c2', Types::INTEGER);

        $this->dropAndCreateTable($table);

        $table->getColumn('c1')
            ->setType(Type::getType(Types::STRING))
            ->setLength(16);

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

        $table = new Table('test_alter');

        $columnUft8EnUs = $table->addColumn('c1', Types::STRING);
        $columnUft8EnUs->setPlatformOption('collation', 'en_US.utf8');

        $table->addColumn('c2', Types::STRING);

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

        $table = new Table('test_alter');

        $columnIcu = $table->addColumn('c1', Types::STRING);
        $columnIcu->setPlatformOption('collation', 'en-US-x-icu');

        $this->dropAndCreateTable($table);

        $sm   = $this->connection->createSchemaManager();
        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTable('test_alter'), $table);

        self::assertTrue($diff->isEmpty());
    }
}

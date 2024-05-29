<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;

use function array_filter;
use function array_pop;

class FunctionalIndexTest extends FunctionalTestCase
{
    public function testGetIndex(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform->supportsFunctionalIndex()) {
            self::markTestSkipped('Platform does not support functional indexes.');
        }

        $tableName = 'some_table';

        $table = new Table($tableName);
        $table->addColumn('column1', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('column2', Types::INTEGER, ['notnull' => false]);
        $table->addIndex(['column1', 'column2', '(column2 IS NOT NULL)'], 'func_idx');
        $this->dropAndCreateTable($table);

        $this->connection->insert($tableName, ['column1' => 1]);

        $tablesFromList = $this->connection->createSchemaManager()->listTables();

        $tables    = array_filter($tablesFromList, static fn (Table $table): bool => $table->getName() === $tableName);
        $someTable = array_pop($tables);

        self::assertInstanceOf(Table::class, $someTable);
        self::assertEquals($tableName, $someTable->getName());

        $index = $someTable->getIndex('func_idx');

        self::assertTrue($index->isFunctional());

        if (TestUtil::isDriverOneOf('pdo_pgsql', 'pgsql')) {
            self::assertEquals(['column1', 'column2', '(column2 IS NOT NULL)'], $index->getColumns());
        } else {
            self::assertEquals(['column1', 'column2', '((`column2` is not null))'], $index->getColumns());
        }
    }

    public function testPlatformException(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform->supportsFunctionalIndex()) {
            self::markTestSkipped('Skipping, platform supports functional indexes.');
        }

        $table = new Table('some_table');
        $table->addColumn('column1', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('column2', Types::INTEGER, ['notnull' => false]);
        $table->addIndex(['column1', 'column2', '(column2 IS NOT NULL)'], 'func_idx');

        $this->expectException(InvalidArgumentException::class);
        $this->dropAndCreateTable($table);
    }
}

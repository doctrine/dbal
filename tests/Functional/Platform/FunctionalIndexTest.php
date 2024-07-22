<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class FunctionalIndexTest extends FunctionalTestCase
{
    public function testGetIndex(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform->supportsFunctionalIndex()) {
            self::markTestSkipped('Platform does not support functional indexes.');
        }

        $tableName = 'functional_index_table';

        $table = new Table($tableName);
        $table->addColumn('column1', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('column2', Types::INTEGER, ['notnull' => false]);
        $table->addIndex(['column1', 'column2', '(column2 IS NOT NULL)'], 'func_idx');
        $this->dropAndCreateTable($table);

        $this->connection->insert($tableName, ['column1' => 1]);

        $functionalIndexTable = $this->connection->createSchemaManager()->introspectTable($tableName);

        $index = $functionalIndexTable->getIndex('func_idx');

        self::assertTrue($index->isFunctional());

        if ($platform instanceof PostgreSQLPlatform) {
            self::assertEquals(['column1', 'column2', '(column2 IS NOT NULL)'], $index->getColumns());
        } elseif ($platform instanceof MySQLPlatform) {
            self::assertEquals(['column1', 'column2', '(`column2` is not null)'], $index->getColumns());
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

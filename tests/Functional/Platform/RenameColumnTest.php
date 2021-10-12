<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class RenameColumnTest extends FunctionalTestCase
{
    /**
     * @dataProvider columnNameProvider
     */
    public function testColumnPositionRetainedAfterRenaming(string $columnName, string $newColumnName): void
    {
        if ($columnName === 'C1' || $columnName === 'importantColumn') {
            self::markTestIncomplete('See https://github.com/doctrine/dbal/issues/4816');
        }

        $table = new Table('test_rename');
        $table->addColumn($columnName, 'string', ['length' => 16]);
        $table->addColumn('c2', 'integer');

        $sm = $this->connection->createSchemaManager();
        $sm->dropAndCreateTable($table);

        $table->dropColumn($columnName)
            ->addColumn($newColumnName, 'string', ['length' => 16]);

        $diff = $sm->createComparator()
            ->diffTable($sm->listTableDetails('test_rename'), $table);

        self::assertNotNull($diff);
        $sm->alterTable($diff);

        $table   = $sm->listTableDetails('test_rename');
        $columns = $table->getColumns();

        self::assertCount(2, $columns);
        self::assertEqualsIgnoringCase($newColumnName, $columns[0]->getName());
        self::assertEqualsIgnoringCase('c2', $columns[1]->getName());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function columnNameProvider(): iterable
    {
        yield ['c1', 'c1_x'];
        yield ['C1', 'c1_x'];
        yield ['importantColumn', 'veryImportantColumn'];
    }
}

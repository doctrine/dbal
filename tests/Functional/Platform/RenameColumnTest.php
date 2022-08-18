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
        $table = new Table('test_rename');
        $table->addColumn($columnName, 'string', ['length' => 16]);
        $table->addColumn('c2', 'integer');

        $this->dropAndCreateTable($table);

        $table->dropColumn($columnName)
            ->addColumn($newColumnName, 'string', ['length' => 16]);

        $sm   =  $this->connection->createSchemaManager();
        $diff = $sm->createComparator()
            ->diffTable($sm->getTable('test_rename'), $table);

        self::assertNotNull($diff);
        $sm->alterTable($diff);

        $table   = $sm->getTable('test_rename');
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

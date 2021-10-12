<?php

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function array_keys;

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
        $table->addColumn($columnName, 'string');
        $table->addColumn('c2', 'integer');

        $sm = $this->connection->createSchemaManager();
        $sm->dropAndCreateTable($table);

        $table->dropColumn($columnName)
            ->addColumn($newColumnName, 'string');

        $comparator = new Comparator();
        $diff       = $comparator->diffTable($sm->listTableDetails('test_rename'), $table);

        self::assertNotFalse($diff);
        $sm->alterTable($diff);

        $table = $sm->listTableDetails('test_rename');
        self::assertSame([$newColumnName, 'c2'], array_keys($table->getColumns()));
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

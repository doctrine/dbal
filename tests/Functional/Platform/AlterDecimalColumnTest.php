<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

class AlterDecimalColumnTest extends FunctionalTestCase
{
    #[DataProvider('scaleAndPrecisionProvider')]
    public function testAlterPrecisionAndScale(int $newPrecision, int $newScale): void
    {
        $table  = new Table('decimal_table');
        $column = $table->addColumn('val', Types::DECIMAL, ['precision' => 16, 'scale' => 6]);

        $this->dropAndCreateTable($table);

        $column->setPrecision($newPrecision);
        $column->setScale($newScale);

        $schemaManager = $this->connection->createSchemaManager();

        $diff = $schemaManager->createComparator()
            ->compareTables($schemaManager->introspectTable('decimal_table'), $table);

        $schemaManager->alterTable($diff);

        $table  = $schemaManager->introspectTable('decimal_table');
        $column = $table->getColumn('val');

        self::assertSame($newPrecision, $column->getPrecision());
        self::assertSame($newScale, $column->getScale());
    }

    /** @return iterable<string,array{int,int}> */
    public static function scaleAndPrecisionProvider(): iterable
    {
        yield 'Precision' => [12, 6];
        yield 'Scale' => [16, 8];
        yield 'Precision and scale' => [10, 4];
    }
}

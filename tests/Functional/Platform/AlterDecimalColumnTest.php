<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;

class AlterDecimalColumnTest extends FunctionalTestCase
{
    #[DataProvider('scaleAndPrecisionProvider')]
    public function testAlterPrecisionAndScale(int $newPrecision, int $newScale, string $typeName): void
    {
        $table = Table::editor()
            ->setUnquotedName('decimal_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('val')
                    ->setTypeName($typeName)
                    ->setPrecision(16)
                    ->setScale(6)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $table = $table->edit()
            ->modifyColumnByUnquotedName(
                'val',
                static function (ColumnEditor $editor) use ($newPrecision, $newScale): void {
                    $editor
                        ->setPrecision($newPrecision)
                        ->setScale($newScale);
                },
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();

        $diff = $schemaManager->createComparator()
            ->compareTables($schemaManager->introspectTable('decimal_table'), $table);

        $schemaManager->alterTable($diff);

        $table  = $schemaManager->introspectTable('decimal_table');
        $column = $table->getColumn('val');

        self::assertSame($newPrecision, $column->getPrecision());
        self::assertSame($newScale, $column->getScale());
    }

    /** @return iterable<string,array{int,int,Types::*}> */
    public static function scaleAndPrecisionProvider(): iterable
    {
        foreach ([Types::DECIMAL, Types::NUMBER] as $type) {
            yield sprintf('Precision (%s)', $type) => [12, 6, $type];
            yield sprintf('Scale (%s)', $type) => [16, 8, $type];
            yield sprintf('Precision and scale (%s)', $type) => [10, 4, $type];
        }
    }
}

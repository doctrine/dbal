<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Schema\Types\MoneyType;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_map;
use function implode;
use function sprintf;

/**
 * Tests introspection of a custom column type with an underlying decimal column
 * on MySQL and MariaDb platforms.
 *
 * See bug #6185
 */
class CustomIntrospectionTest extends FunctionalTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (TestUtil::isDriverOneOf('oci8', 'pdo_oci')) {
            self::markTestSkipped('Skip on Oracle');
        }

        Type::addType(MoneyType::NAME, MoneyType::class);
    }

    public function testCustomColumnIntrospection(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_custom_column_introspection')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('quantity')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(10)
                    ->setScale(2)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('amount')
                    ->setTypeName('money')
                    ->setPrecision(10)
                    ->setScale(2)
                    ->setNotNull(false)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $schemaManager = $this->connection->createSchemaManager();

        $onlineTable = $schemaManager->introspectTable('test_custom_column_introspection');
        $diff        = $schemaManager->createComparator()->compareTables($onlineTable, $table);
        $changedCols = array_map(
            static fn (ColumnDiff $columnDiff): string => $columnDiff->getOldColumn()->getName(),
            $diff->getChangedColumns(),
        );

        self::assertTrue($diff->isEmpty(), sprintf(
            'Tables should be identical. Differences detected in %s.',
            implode(', ', $changedCols),
        ));
    }
}

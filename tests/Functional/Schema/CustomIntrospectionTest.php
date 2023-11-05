<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tests\Functional\Schema\Types\MoneyType;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Type;

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
        $tableName     = 'test_custom_column_introspection';
        $schemaManager = $this->connection->createSchemaManager();
        $schema        = new Schema([], [], $schemaManager->createSchemaConfig());
        $table         = $schema->createTable($tableName);

        $table->addColumn('id', 'integer');
        $table->addColumn('quantity', 'decimal', [
            'notnull' => false,
            'scale' => 2,
            'precision' => 10,
        ]);
        $table->addColumn('amount', 'money', [
            'notnull' => false,
            'scale' => 2,
            'precision' => 10,
        ]);

        $this->dropAndCreateTable($table);

        $onlineTable = $schemaManager->introspectTable($tableName);
        $diff        = $schemaManager->createComparator()->compareTables($onlineTable, $table);
        $changedCols = array_map(
            static fn (ColumnDiff $columnDiff): string => $columnDiff->getOldColumn()->getName(),
            $diff->getModifiedColumns(),
        );

        self::assertTrue($diff->isEmpty(), sprintf(
            'Tables should be identical. Differences detected in %s.',
            implode(', ', $changedCols),
        ));
    }
}

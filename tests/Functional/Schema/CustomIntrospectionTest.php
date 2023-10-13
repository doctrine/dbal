<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Schema\Types\MoneyType;
use Doctrine\DBAL\Tests\FunctionalTestCase;
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
    private AbstractSchemaManager $schemaManager;

    private Comparator $comparator;

    private AbstractPlatform $platform;

    public static function setUpBeforeClass(): void
    {
        Type::addType('money', MoneyType::class);
    }

    protected function setUp(): void
    {
        $this->platform = $this->connection->getDatabasePlatform();

        if (! $this->platform instanceof AbstractMySQLPlatform) {
            self::markTestSkipped();
        }

        $this->schemaManager = $this->connection->createSchemaManager();
        $this->comparator    = $this->schemaManager->createComparator();
    }

    public function testCustomColumnIntrospection(): void
    {
        $tableName = 'test_custom_column_introspection';
        $table     = new Table($tableName);

        $table->addColumn('id', 'integer');
        $table->addColumn('quantity', 'decimal');
        $table->addColumn('amount', 'money', [
            'notnull' => false,
            'scale' => 2,
            'precision' => 10,
        ]);

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTable($tableName);

        $diff        = $this->comparator->compareTables($table, $onlineTable);
        $changedCols = [];

        if (! $diff->isEmpty()) {
            $changedCols = array_map(static fn ($c) => $c->getOldColumnName()->getName(), $diff->getModifiedColumns());
        }

        self::assertTrue($diff->isEmpty(), sprintf(
            'Tables should be identical. Differences detected in %s.',
            implode(':', $changedCols),
        ));
    }
}

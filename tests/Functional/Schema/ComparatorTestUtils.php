<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use PHPUnit\Framework\TestCase;

final class ComparatorTestUtils
{
    /**
     * @throws Exception
     */
    public static function diffFromActualToDesiredTable(
        AbstractSchemaManager $schemaManager,
        Comparator $comparator,
        Table $desiredTable
    ): ?TableDiff {
        return $comparator->diffTable(
            $schemaManager->getTable($desiredTable->getName()),
            $desiredTable
        );
    }

    /**
     * @throws Exception
     */
    public static function diffFromDesiredToActualTable(
        AbstractSchemaManager $schemaManager,
        Comparator $comparator,
        Table $desiredTable
    ): ?TableDiff {
        return $comparator->diffTable(
            $desiredTable,
            $schemaManager->getTable($desiredTable->getName())
        );
    }

    public static function assertDiffNotEmpty(Connection $connection, Comparator $comparator, Table $table): void
    {
        $schemaManager = $connection->createSchemaManager();

        $diff = self::diffFromActualToDesiredTable($schemaManager, $comparator, $table);

        TestCase::assertNotNull($diff);

        $schemaManager->alterTable($diff);

        TestCase::assertNull(self::diffFromActualToDesiredTable($schemaManager, $comparator, $table));
        TestCase::assertNull(self::diffFromDesiredToActualTable($schemaManager, $comparator, $table));
    }
}

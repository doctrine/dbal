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
    public static function diffOnlineAndOfflineTable(
        AbstractSchemaManager $schemaManager,
        Comparator $comparator,
        Table $table
    ): ?TableDiff {
        return $comparator->diffTable(
            $schemaManager->listTableDetails($table->getName()),
            $table
        );
    }

    public static function assertDiffNotEmpty(Connection $connection, Comparator $comparator, Table $table): void
    {
        $schemaManager = $connection->createSchemaManager();

        $diff = self::diffOnlineAndOfflineTable($schemaManager, $comparator, $table);

        TestCase::assertNotNull($diff);

        $schemaManager->alterTable($diff);

        TestCase::assertNull(self::diffOnlineAndOfflineTable($schemaManager, $comparator, $table));
    }
}

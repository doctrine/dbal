<?php

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
     * @return TableDiff|false
     *
     * @throws Exception
     */
    public static function diffOnlineAndOfflineTable(
        AbstractSchemaManager $schemaManager,
        Comparator $comparator,
        Table $table
    ) {
        return $comparator->diffTable(
            $schemaManager->listTableDetails($table->getName()),
            $table
        );
    }

    public static function assertDiffNotEmpty(Connection $connection, Comparator $comparator, Table $table): void
    {
        $schemaManager = $connection->createSchemaManager();

        $diff = self::diffOnlineAndOfflineTable($schemaManager, $comparator, $table);

        TestCase::assertNotFalse($diff);

        $schemaManager->alterTable($diff);

        TestCase::assertFalse(self::diffOnlineAndOfflineTable($schemaManager, $comparator, $table));
    }

    /**
     * @return iterable<string,array<callable(AbstractSchemaManager):Comparator>>
     */
    public static function comparatorProvider(): iterable
    {
        yield 'Generic comparator' => [
            static function (): Comparator {
                return new Comparator();
            },
        ];

        yield 'Platform-specific comparator' => [
            static function (AbstractSchemaManager $schemaManager): Comparator {
                return $schemaManager->createComparator();
            },
        ];
    }
}

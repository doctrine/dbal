<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;
use PHPUnit\Framework\TestCase;

class SQLiteSchemaManagerTest extends TestCase
{
    /**
     * TODO move to functional test once SqliteSchemaManager::selectForeignKeyColumns can honor database/schema name
     * https://github.com/doctrine/dbal/blob/3.8.3/src/Schema/SqliteSchemaManager.php#L740
     */
    public function testListTableForeignKeysDefaultDatabasePassing(): void
    {
        $conn = self::createStub(Connection::class);
        $conn->method('getDatabase')
            ->willReturn('main');

        $manager = new class ($conn, new SQLitePlatform()) extends SQLiteSchemaManager {
            public static string $passedDatabaseName;

            protected function selectForeignKeyColumns(string $databaseName, ?string $tableName = null): Result
            {
                self::$passedDatabaseName = $databaseName;

                return parent::selectForeignKeyColumns($databaseName, $tableName);
            }
        };

        $manager->listTableForeignKeys('t');
        self::assertSame('main', $manager::$passedDatabaseName);
    }
}

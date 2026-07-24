<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\SQLite;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLite\SQLiteMetadataProvider;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

class SQLiteMetadataProviderTest extends TestCase
{
    /**
     * On SQLite versions older than 3.37.0, PRAGMA table_list does not expose the WITHOUT ROWID and STRICT
     * flags, so they have to be parsed from the CREATE TABLE statement instead.
     */
    #[DataProvider('tableOptionsProvider')]
    public function testParsesTableOptionsFromCreateTableSQLOnLegacyVersions(
        string $createTableSQL,
        bool $expectedWithoutRowid,
        bool $expectedStrict,
    ): void {
        $connection = self::createStub(Connection::class);
        $connection->method('getServerVersion')->willReturn('3.36.0');
        $connection->method('fetchOne')->willReturn($createTableSQL);

        $provider = new SQLiteMetadataProvider($connection, new SQLitePlatform());

        $rows = iterator_to_array($provider->getTableOptionsForTable(null, 'mytable'));
        self::assertCount(1, $rows);

        $options = $rows[0]->getOptions();
        self::assertSame($expectedWithoutRowid, $options['without_rowid'] ?? false);
        self::assertSame($expectedStrict, $options['strict'] ?? false);
    }

    /** @return iterable<string, array{string, bool, bool}> */
    public static function tableOptionsProvider(): iterable
    {
        yield 'plain' => ['CREATE TABLE mytable (id INTEGER)', false, false];
        yield 'without rowid' => ['CREATE TABLE mytable (id INTEGER, PRIMARY KEY (id)) WITHOUT ROWID', true, false];
        yield 'strict' => ['CREATE TABLE mytable (id INTEGER) STRICT', false, true];
        yield 'without rowid and strict' => [
            'CREATE TABLE mytable (id INTEGER, PRIMARY KEY (id)) WITHOUT ROWID, STRICT',
            true,
            true,
        ];
    }
}

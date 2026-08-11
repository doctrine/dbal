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
    #[DataProvider('tableOptionsProvider')]
    public function testParsesTableOptionsFromCreateTableSQL(
        string $createTableSQL,
        bool $expectedWithoutRowid,
        bool $expectedStrict,
    ): void {
        $connection = self::createStub(Connection::class);
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

        yield 'strict before without rowid' => [
            'CREATE TABLE mytable (id INTEGER, PRIMARY KEY (id)) STRICT, WITHOUT ROWID',
            true,
            true,
        ];

        yield 'column named like an option' => [
            'CREATE TABLE mytable (strict INTEGER, "without rowid" INTEGER)',
            false,
            false,
        ];

        yield 'multiline statement' => [
            <<<'SQL'
            CREATE TABLE mytable (
                id INTEGER,
                PRIMARY KEY (id)
            ) WITHOUT ROWID
            SQL,
            true,
            false,
        ];
    }
}

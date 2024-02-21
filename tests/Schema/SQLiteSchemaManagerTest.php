<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SQLiteSchemaManagerTest extends TestCase
{
    #[DataProvider('getDataColumnCollation')]
    public function testParseColumnCollation(?string $collation, string $column, string $sql): void
    {
        $conn = $this->createMock(Connection::class);

        $manager = new SQLiteSchemaManager($conn, new SQLitePlatform());
        $ref     = new ReflectionMethod($manager, 'parseColumnCollationFromSQL');

        self::assertSame($collation, $ref->invoke($manager, $column, $sql));
    }

    /** @return mixed[][] */
    public static function getDataColumnCollation(): iterable
    {
        return [
            [
                'RTRIM',
                'a',
                'CREATE TABLE "a" ("a" text DEFAULT "aa" COLLATE "RTRIM" NOT NULL)',
            ],
            [
                'utf-8',
                'a',
                'CREATE TABLE "a" ("b" text UNIQUE NOT NULL COLLATE NOCASE, '
                    . '"a" text DEFAULT "aa" COLLATE "utf-8" NOT NULL)',
            ],
            [
                'NOCASE',
                'a',
                'CREATE TABLE "a" ("a" text DEFAULT (lower(ltrim(" a") || rtrim("a ")))'
                    . ' CHECK ("a") NOT NULL COLLATE NOCASE UNIQUE, "b" text COLLATE RTRIM)',
            ],
            [
                null,
                'a',
                'CREATE TABLE "a" ("a" text CHECK ("a") NOT NULL, "b" text COLLATE RTRIM)',
            ],
            [
                'RTRIM',
                'a"b',
                'CREATE TABLE "a" ("a""b" text COLLATE RTRIM)',
            ],
            [
                'BINARY',
                'b',
                'CREATE TABLE "a" (bb TEXT COLLATE RTRIM, b VARCHAR(42) NOT NULL COLLATE BINARY)',
            ],
            [
                'BINARY',
                'b',
                'CREATE TABLE "a" (bbb TEXT COLLATE NOCASE, bb TEXT COLLATE RTRIM, '
                    . 'b VARCHAR(42) NOT NULL COLLATE BINARY)',
            ],
            [
                'BINARY',
                'b',
                'CREATE TABLE "a" (b VARCHAR(42) NOT NULL COLLATE BINARY, bb TEXT COLLATE RTRIM)',
            ],
            [
                'utf-8',
                'bar#',
                'CREATE TABLE dummy_table (id INTEGER NOT NULL, foo VARCHAR(255) COLLATE "utf-8" NOT NULL, '
                    . '"bar#" VARCHAR(255) COLLATE "utf-8" NOT NULL, baz VARCHAR(255) COLLATE "utf-8" NOT NULL, '
                    . 'PRIMARY KEY(id))',
            ],
            [
                null,
                'bar#',
                'CREATE TABLE dummy_table (id INTEGER NOT NULL, foo VARCHAR(255) NOT NULL,'
                    . ' "bar#" VARCHAR(255) NOT NULL, baz VARCHAR(255) NOT NULL, PRIMARY KEY(id))',
            ],
            [
                'utf-8',
                'baz',
                'CREATE TABLE dummy_table (id INTEGER NOT NULL, foo VARCHAR(255) COLLATE "utf-8" NOT NULL,'
                    . ' "bar#" INTEGER NOT NULL, baz VARCHAR(255) COLLATE "utf-8" NOT NULL, PRIMARY KEY(id))',
            ],
            [
                null,
                'baz',
                'CREATE TABLE dummy_table (id INTEGER NOT NULL, foo VARCHAR(255) NOT NULL, "bar#" INTEGER NOT NULL, '
                    . 'baz VARCHAR(255) NOT NULL, PRIMARY KEY(id))',
            ],
            [
                'utf-8',
                'bar/',
                'CREATE TABLE dummy_table (id INTEGER NOT NULL, foo VARCHAR(255) COLLATE "utf-8" NOT NULL, '
                    . '"bar/" VARCHAR(255) COLLATE "utf-8" NOT NULL, baz VARCHAR(255) COLLATE "utf-8" NOT NULL,'
                    . ' PRIMARY KEY(id))',
            ],
            [
                null,
                'bar/',
                'CREATE TABLE dummy_table (id INTEGER NOT NULL, foo VARCHAR(255) NOT NULL, '
                    . '"bar/" VARCHAR(255) NOT NULL, baz VARCHAR(255) NOT NULL, PRIMARY KEY(id))',
            ],
            [
                'utf-8',
                'baz',
                'CREATE TABLE dummy_table (id INTEGER NOT NULL, foo VARCHAR(255) COLLATE "utf-8" NOT NULL, '
                    . '"bar/" INTEGER NOT NULL, baz VARCHAR(255) COLLATE "utf-8" NOT NULL, PRIMARY KEY(id))',
            ],
            [
                null,
                'baz',
                'CREATE TABLE dummy_table (id INTEGER NOT NULL, foo VARCHAR(255) NOT NULL,'
                    . ' "bar/" INTEGER NOT NULL, baz VARCHAR(255) NOT NULL, PRIMARY KEY(id))',
            ],
        ];
    }

    #[DataProvider('getDataColumnComment')]
    public function testParseColumnCommentFromSQL(string $comment, string $column, string $sql): void
    {
        $conn = $this->createMock(Connection::class);

        $manager = new SQLiteSchemaManager($conn, new SQLitePlatform());
        $ref     = new ReflectionMethod($manager, 'parseColumnCommentFromSQL');

        self::assertSame($comment, $ref->invoke($manager, $column, $sql));
    }

    /** @return mixed[][] */
    public static function getDataColumnComment(): iterable
    {
        return [
            'Single column' => [
                '',
                'a',
                'CREATE TABLE "a" ("a" TEXT DEFAULT "a" COLLATE RTRIM)',
            ],
            'Column "bar", select "bar"' => [
                '',
                'bar',
                'CREATE TABLE dummy_table (
                    id INTEGER NOT NULL,
                    foo VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    "bar" VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    baz VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    PRIMARY KEY(id)
                )',
            ],
            'Column "bar#", select "bar#"' => [
                '',
                'bar#',
                'CREATE TABLE dummy_table (
                    id INTEGER NOT NULL,
                    foo VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    "bar#" VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    baz VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    PRIMARY KEY(id)
                )',
            ],
            'Column "bar#", select "baz"' => [
                '',
                'baz',
                'CREATE TABLE dummy_table (
                    id INTEGER NOT NULL,
                    foo VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    "bar#" INTEGER NOT NULL,
                    baz VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    PRIMARY KEY(id)
                )',
            ],

            'Column "bar/", select "bar/"' => [
                '',
                'bar/',
                'CREATE TABLE dummy_table (
                    id INTEGER NOT NULL,
                    foo VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    "bar/" VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    baz VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    PRIMARY KEY(id)
                    )',
            ],
            'Column "bar/", select "baz"' => [
                '',
                'baz',
                'CREATE TABLE dummy_table (
                    id INTEGER NOT NULL,
                    foo VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    "bar/" INTEGER NOT NULL,
                    baz VARCHAR(255) COLLATE "utf-8" NOT NULL,
                    PRIMARY KEY(id)
                )',
            ],
        ];
    }
}

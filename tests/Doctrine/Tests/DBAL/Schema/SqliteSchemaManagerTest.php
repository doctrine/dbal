<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\SqliteSchemaManager;

class SqliteSchemaManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getDataColumnCollation
     *
     * @group 2865
     */
    public function testParseColumnCollation($collation, string $column, string $sql) : void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('getDatabasePlatform')->willReturn(new SqlitePlatform());

        $manager = new SqliteSchemaManager($conn);
        $ref     = new \ReflectionMethod($manager, 'parseColumnCollationFromSQL');
        $ref->setAccessible(true);

        self::assertSame($collation, $ref->invoke($manager, $column, $sql));
    }

    public function getDataColumnCollation()
    {
        return [
            ['RTRIM', 'a', 'CREATE TABLE "a" ("a" text DEFAULT "aa" COLLATE "RTRIM" NOT NULL)'],
            ['utf-8', 'a', 'CREATE TABLE "a" ("b" text UNIQUE NOT NULL COLLATE NOCASE, "a" text DEFAULT "aa" COLLATE "utf-8" NOT NULL)'],
            ['NOCASE', 'a', 'CREATE TABLE "a" ("a" text DEFAULT (lower(ltrim(" a") || rtrim("a "))) CHECK ("a") NOT NULL COLLATE NOCASE UNIQUE, "b" text COLLATE RTRIM)'],
            [false, 'a', 'CREATE TABLE "a" ("a" text CHECK ("a") NOT NULL, "b" text COLLATE RTRIM)'],
            ['RTRIM', 'a"b', 'CREATE TABLE "a" ("a""b" text COLLATE RTRIM)'],
            ['BINARY', 'b', 'CREATE TABLE "a" (bb TEXT COLLATE RTRIM, b VARCHAR(42) NOT NULL COLLATE BINARY)'],
            ['BINARY', 'b', 'CREATE TABLE "a" (bbb TEXT COLLATE NOCASE, bb TEXT COLLATE RTRIM, b VARCHAR(42) NOT NULL COLLATE BINARY)'],
            ['BINARY', 'b', 'CREATE TABLE "a" (b VARCHAR(42) NOT NULL COLLATE BINARY, bb TEXT COLLATE RTRIM)'],
        ];
    }
}

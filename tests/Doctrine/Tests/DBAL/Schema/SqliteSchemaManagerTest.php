<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\SqliteSchemaManager;

class SqliteSchemaManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider getDataColumnCollation
     */
    public function testParseColumnCollation($collation, $column, $sql)
    {
        $conn = $this->getMockBuilder('Doctrine\DBAL\Connection')->disableOriginalConstructor()->getMock();
        $conn->expects($this->any())->method('getDatabasePlatform')->will($this->returnValue(new SqlitePlatform()));

        $manager = new SqliteSchemaManager($conn);
        $ref = new \ReflectionMethod($manager, 'parseColumnCollationFromSQL');
        $ref->setAccessible(true);

        self::assertEquals($collation, $ref->invoke($manager, $column, $sql));
    }

    public function getDataColumnCollation()
    {
        return array(
            array(
                'RTRIM', 'a', 'CREATE TABLE "a" ("a" text DEFAULT "aa" COLLATE "RTRIM" NOT NULL)'
            ),
            array(
                'utf-8', 'a', 'CREATE TABLE "a" ("b" text UNIQUE NOT NULL COLLATE NOCASE, "a" text DEFAULT "aa" COLLATE "utf-8" NOT NULL)'
            ),
            array(
                'NOCASE', 'a', 'CREATE TABLE "a" ("a" text DEFAULT (lower(ltrim(" a") || rtrim("a "))) CHECK ("a") NOT NULL COLLATE NOCASE UNIQUE, "b" text COLLATE RTRIM)'
            ),
            array(
                false, 'a', 'CREATE TABLE "a" ("a" text CHECK ("a") NOT NULL, "b" text COLLATE RTRIM)'
            ),
            array(
                'RTRIM', 'a"b', 'CREATE TABLE "a" ("a""b" text COLLATE RTRIM)'
            ),
            array(
                'utf-8', 'bar#', 'CREATE TABLE dummy_table (id INTEGER NOT NULL, foo VARCHAR(255) COLLATE "utf-8" NOT NULL, "bar#" VARCHAR(255) COLLATE "utf-8" NOT NULL, baz VARCHAR(255) COLLATE "utf-8" NOT NULL, PRIMARY KEY(id))'
            ),
            array(
                false, 'bar#', 'CREATE TABLE dummy_table (id INTEGER NOT NULL, foo VARCHAR(255) NOT NULL, "bar#" VARCHAR(255) NOT NULL, baz VARCHAR(255) NOT NULL, PRIMARY KEY(id))'
            ),
            array(
                'utf-8', 'baz', 'CREATE TABLE dummy_table (id INTEGER NOT NULL, foo VARCHAR(255) COLLATE "utf-8" NOT NULL, "bar#" INTEGER NOT NULL, baz VARCHAR(255) COLLATE "utf-8" NOT NULL, PRIMARY KEY(id))'
            ),
            array(
                false, 'baz', 'CREATE TABLE dummy_table (id INTEGER NOT NULL, foo VARCHAR(255) NOT NULL, "bar#" INTEGER NOT NULL, baz VARCHAR(255) NOT NULL, PRIMARY KEY(id))'
            ),
        );
    }
}

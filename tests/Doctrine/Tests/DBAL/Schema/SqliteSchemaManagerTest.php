<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\SqliteSchemaManager;

class SqliteSchemaManagerTest extends \PHPUnit_Framework_TestCase
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

        $this->assertEquals($collation, $ref->invoke($manager, $column, $sql));
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
        );
    }
}

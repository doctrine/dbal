<?php

namespace Doctrine\DBAL\Tests\Platforms\MySQL;

use Doctrine\DBAL\Platforms\MySQL\Comparator;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Schema\ComparatorTest as BaseComparatorTest;
use Doctrine\DBAL\Types\Type;

class ComparatorTest extends BaseComparatorTest
{
    protected function setUp(): void
    {
        $this->comparator = new Comparator(new MySQLPlatform());
    }

    public function testItMigratesUtf8ToUtf8Mb4(): void
    {
        $fromSchema = new Schema([
            new Table('a_table', [
                new Column('a_column', Type::getType('string')),
            ], [], [], [], ['collation' => 'utf8_unicode_ci', 'charset' => 'utf8']),
        ]);
        $toSchema   = new Schema([
            new Table('a_table', [
                new Column('a_column', Type::getType('string')),
            ], [], [], [], ['collation' => 'utf8_mb4_unicode_ci', 'charset' => 'utf8mb4']),
        ]);

        self::assertNotEmpty(
            $this->comparator->compare($fromSchema, $toSchema)->toSql(new MySQLPlatform())
        );
    }
}

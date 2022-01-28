<?php

namespace Doctrine\DBAL\Tests\Platforms\SQLite;

use Doctrine\DBAL\Platforms\SQLite\Comparator;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Tests\Schema\ComparatorTest as BaseComparatorTest;

class ComparatorTest extends BaseComparatorTest
{
    protected function setUp(): void
    {
        $this->comparator = new Comparator(new SqlitePlatform());
    }

    public function testCompareChangedBinaryColumn(): void
    {
        $oldSchema = new Schema();

        $tableFoo = $oldSchema->createTable('foo');
        $tableFoo->addColumn('id', 'binary');

        $newSchema = new Schema();
        $table     = $newSchema->createTable('foo');
        $table->addColumn('id', 'binary', ['length' => 42, 'fixed' => true]);

        $expected             = new SchemaDiff();
        $expected->fromSchema = $oldSchema;

        self::assertEquals($expected, $this->comparator->compareSchemas(
            $oldSchema,
            $newSchema,
            $this->comparator
        ));
    }
}

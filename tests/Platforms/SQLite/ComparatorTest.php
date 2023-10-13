<?php

namespace Doctrine\DBAL\Tests\Platforms\SQLite;

use Doctrine\DBAL\Platforms\SQLite\Comparator;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;

class ComparatorTest extends AbstractComparatorTestCase
{
    protected function setUp(): void
    {
        $this->comparator = new Comparator(new SqlitePlatform());
    }

    public function testCompareChangedBinaryColumn(): void
    {
        self::markTestSkipped('Binary columns are BLOB in SQLite and do not support $length or $fixed.');
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\SQLite;

use Doctrine\DBAL\Platforms\SQLite\Comparator;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;

class ComparatorTest extends AbstractComparatorTestCase
{
    protected function setUp(): void
    {
        $this->comparator = new Comparator(new SQLitePlatform());
    }

    public function testCompareChangedBinaryColumn(): void
    {
        self::markTestSkipped('SQLite maps binary columns to BLOB regardless of the length and it is fixed');
    }
}

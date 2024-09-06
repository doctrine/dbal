<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\SQLite;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Platforms\SQLite\Comparator;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;

class ComparatorTest extends AbstractComparatorTestCase
{
    protected function createComparator(?Configuration $configuration = null): Comparator
    {
        return new Comparator(new SQLitePlatform(), $configuration ?? new Configuration());
    }

    public function testCompareChangedBinaryColumn(): void
    {
        self::markTestSkipped('SQLite maps binary columns to BLOB regardless of the length and it is fixed');
    }
}

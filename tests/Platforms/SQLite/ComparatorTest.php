<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\SQLite;

use Doctrine\DBAL\Platforms\SQLite\Comparator;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;

class ComparatorTest extends AbstractComparatorTestCase
{
    protected function createComparator(ComparatorConfig $config): Comparator
    {
        return new Comparator(new SQLitePlatform(), $config);
    }

    public function testCompareChangedBinaryColumn(): void
    {
        self::markTestSkipped('SQLite maps binary columns to BLOB regardless of the length and it is fixed');
    }
}

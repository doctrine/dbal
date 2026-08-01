<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\SQLite;

use Doctrine\DBAL\Platforms\SQLite\Comparator;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;
use Override;

class ComparatorTest extends AbstractComparatorTestCase
{
    #[Override]
    protected function createComparator(ComparatorConfig $config): Comparator
    {
        $platform = new SQLitePlatform();

        return new Comparator(
            $platform,
            $platform->createDerivedObjectProvider(),
            $config,
        );
    }

    public function testCompareChangedBinaryColumn(): void
    {
        self::markTestSkipped('SQLite maps binary columns to BLOB regardless of the length and it is fixed');
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\PostgreSQL;

use Doctrine\DBAL\Platforms\PostgreSQL\Comparator;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;
use Override;

class ComparatorTest extends AbstractComparatorTestCase
{
    #[Override]
    protected function createComparator(ComparatorConfig $config): Comparator
    {
        $platform = new PostgreSQLPlatform();

        return new Comparator(
            $platform,
            $platform->createDerivedObjectProvider(),
            $config,
        );
    }
}

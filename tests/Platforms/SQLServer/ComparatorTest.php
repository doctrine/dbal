<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\SQLServer;

use Doctrine\DBAL\Platforms\SQLServer\Comparator;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;
use Override;

class ComparatorTest extends AbstractComparatorTestCase
{
    #[Override]
    protected function createComparator(ComparatorConfig $config): Comparator
    {
        $platform = new SQLServerPlatform();

        return new Comparator(
            $platform,
            $platform->createDerivedObjectProvider(),
            '',
            $config,
        );
    }
}

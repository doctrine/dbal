<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\SQLServer;

use Doctrine\DBAL\Platforms\SQLServer\Comparator;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;

class ComparatorTest extends AbstractComparatorTestCase
{
    protected function createComparator(ComparatorConfig $config): Comparator
    {
        return new Comparator(new SQLServerPlatform(), '', $config);
    }
}

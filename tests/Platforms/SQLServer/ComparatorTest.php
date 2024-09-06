<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\SQLServer;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Platforms\SQLServer\Comparator;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;

class ComparatorTest extends AbstractComparatorTestCase
{
    protected function createComparator(?Configuration $configuration = null): Comparator
    {
        return new Comparator(new SQLServerPlatform(), $configuration ?? new Configuration(), '');
    }
}

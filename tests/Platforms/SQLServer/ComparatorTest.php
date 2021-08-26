<?php

namespace Doctrine\DBAL\Tests\Platforms\SQLServer;

use Doctrine\DBAL\Platforms\SQLServer\Comparator;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Tests\Schema\ComparatorTest as BaseComparatorTest;

class ComparatorTest extends BaseComparatorTest
{
    protected function setUp(): void
    {
        $this->comparator = new Comparator(new SQLServer2012Platform(), '');
    }
}

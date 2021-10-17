<?php

namespace Doctrine\DBAL\Tests\Platforms\MySQL;

use Doctrine\DBAL\Platforms\MySQL\Comparator;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Tests\Schema\ComparatorTest as BaseComparatorTest;

class ComparatorTest extends BaseComparatorTest
{
    protected function setUp(): void
    {
        $this->comparator = new Comparator(new MySQLPlatform());
    }
}

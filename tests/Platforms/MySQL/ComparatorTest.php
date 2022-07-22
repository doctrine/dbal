<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\MySQL;

use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\Comparator;
use Doctrine\DBAL\Platforms\MySQL\DefaultTableOptions;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Tests\Schema\ComparatorTest as BaseComparatorTest;

class ComparatorTest extends BaseComparatorTest
{
    protected function setUp(): void
    {
        $this->comparator = new Comparator(
            new MySQLPlatform(),
            $this->createStub(CharsetMetadataProvider::class),
            $this->createStub(CollationMetadataProvider::class),
            new DefaultTableOptions('utf8mb4', 'utf8mb4_general_ci')
        );
    }
}

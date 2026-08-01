<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms\MySQL;

use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\Comparator;
use Doctrine\DBAL\Platforms\MySQL\DefaultTableOptions;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;
use Override;

class ComparatorTest extends AbstractComparatorTestCase
{
    #[Override]
    protected function createComparator(ComparatorConfig $config): Comparator
    {
        $platform = new MySQLPlatform();

        return new Comparator(
            $platform,
            $platform->createDerivedObjectProvider(),
            self::createStub(CharsetMetadataProvider::class),
            self::createStub(CollationMetadataProvider::class),
            new DefaultTableOptions('utf8mb4', 'utf8mb4_general_ci'),
            $config,
        );
    }
}

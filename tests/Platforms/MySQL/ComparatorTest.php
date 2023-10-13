<?php

namespace Doctrine\DBAL\Tests\Platforms\MySQL;

use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\Comparator;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Tests\Schema\AbstractComparatorTestCase;

class ComparatorTest extends AbstractComparatorTestCase
{
    protected function setUp(): void
    {
        $this->comparator = new Comparator(
            new MySQLPlatform(),
            new class implements CollationMetadataProvider {
                public function getCollationCharset(string $collation): ?string
                {
                    return null;
                }
            },
        );
    }
}

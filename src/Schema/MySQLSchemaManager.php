<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MySQL;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider\CachingCharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider\ConnectionCharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider\CachingCollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider\ConnectionCollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\DefaultTableOptions;
use Override;

use function assert;

/**
 * Schema manager for the MySQL RDBMS.
 *
 * @extends AbstractSchemaManager<AbstractMySQLPlatform>
 */
class MySQLSchemaManager extends AbstractSchemaManager
{
    private ?DefaultTableOptions $defaultTableOptions = null;

    /** @throws Exception */
    #[Override]
    public function createComparator(ComparatorConfig $config = new ComparatorConfig()): Comparator
    {
        return new MySQL\Comparator(
            $this->platform,
            new CachingCharsetMetadataProvider(
                new ConnectionCharsetMetadataProvider($this->connection),
            ),
            new CachingCollationMetadataProvider(
                new ConnectionCollationMetadataProvider($this->connection),
            ),
            $this->getDefaultTableOptions(),
            $config,
        );
    }

    /** @throws Exception */
    private function getDefaultTableOptions(): DefaultTableOptions
    {
        if ($this->defaultTableOptions === null) {
            $row = $this->connection->fetchNumeric(
                'SELECT @@character_set_database, @@collation_database',
            );

            assert($row !== false);

            $this->defaultTableOptions = new DefaultTableOptions(...$row);
        }

        return $this->defaultTableOptions;
    }
}

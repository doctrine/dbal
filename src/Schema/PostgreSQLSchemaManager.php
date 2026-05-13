<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\PostgreSQL;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Override;

use function assert;
use function strlen;

/**
 * PostgreSQL Schema Manager.
 *
 * @extends AbstractSchemaManager<PostgreSQLPlatform>
 */
class PostgreSQLSchemaManager extends AbstractSchemaManager
{
    #[Override]
    public function createComparator(ComparatorConfig $config = new ComparatorConfig()): Comparator
    {
        return new PostgreSQL\Comparator($this->platform, $config);
    }

    #[Override]
    protected function determineCurrentSchemaName(): ?string
    {
        $currentSchema = $this->connection->fetchOne('SELECT current_schema()');
        assert($currentSchema !== false);
        assert(strlen($currentSchema) > 0);

        return $currentSchema;
    }
}

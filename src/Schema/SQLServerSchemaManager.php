<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLServer;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Override;

use function assert;
use function is_string;

/**
 * SQL Server Schema Manager.
 *
 * @extends AbstractSchemaManager<SQLServerPlatform>
 */
class SQLServerSchemaManager extends AbstractSchemaManager
{
    private ?string $databaseCollation = null;

    /** @throws Exception */
    #[Override]
    public function createComparator(ComparatorConfig $config = new ComparatorConfig()): Comparator
    {
        return new SQLServer\Comparator(
            $this->platform,
            $this->platform->createDerivedObjectProvider(),
            $this->getDatabaseCollation(),
            $config,
        );
    }

    /** @throws Exception */
    private function getDatabaseCollation(): string
    {
        if ($this->databaseCollation === null) {
            $databaseCollation = $this->connection->fetchOne(
                'SELECT collation_name FROM sys.databases WHERE name = '
                . $this->platform->getCurrentDatabaseExpression(),
            );

            // a database is always selected, even if omitted in the connection parameters
            assert(is_string($databaseCollation));

            $this->databaseCollation = $databaseCollation;
        }

        return $this->databaseCollation;
    }

    #[Override]
    protected function determineCurrentSchemaName(): ?string
    {
        $schemaName = $this->connection->fetchOne('SELECT SCHEMA_NAME()');
        assert($schemaName !== false);

        return $schemaName;
    }
}

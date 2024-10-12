<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\SQLServer;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * Compares schemas in the context of SQL Server platform.
 *
 * @link https://docs.microsoft.com/en-us/sql/t-sql/statements/collations?view=sql-server-ver15
 */
class Comparator extends BaseComparator
{
    /** @internal The comparator can be only instantiated by a schema manager. */
    public function __construct(
        SQLServerPlatform $platform,
        private readonly string $databaseCollation,
        ComparatorConfig $config = new ComparatorConfig(),
    ) {
        parent::__construct($platform, $config);
    }

    public function compareTables(Table $oldTable, Table $newTable): TableDiff
    {
        return parent::compareTables(
            $this->normalizeColumns($oldTable),
            $this->normalizeColumns($newTable),
        );
    }

    private function normalizeColumns(Table $table): Table
    {
        $table = clone $table;

        foreach ($table->getColumns() as $column) {
            $options = $column->getPlatformOptions();

            if (! isset($options['collation']) || $options['collation'] !== $this->databaseCollation) {
                continue;
            }

            unset($options['collation']);
            $column->setPlatformOptions($options);
        }

        return $table;
    }
}

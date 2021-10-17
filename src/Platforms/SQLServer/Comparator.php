<?php

namespace Doctrine\DBAL\Platforms\SQLServer;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\Table;

/**
 * Compares schemas in the context of SQL Server platform.
 *
 * @link https://docs.microsoft.com/en-us/sql/t-sql/statements/collations?view=sql-server-ver15
 */
class Comparator extends BaseComparator
{
    /** @var string */
    private $databaseCollation;

    /**
     * @internal The comparator can be only instantiated by a schema manager.
     */
    public function __construct(SQLServerPlatform $platform, string $databaseCollation)
    {
        parent::__construct($platform);

        $this->databaseCollation = $databaseCollation;
    }

    /**
     * {@inheritDoc}
     */
    public function diffTable(Table $fromTable, Table $toTable)
    {
        $fromTable = clone $fromTable;
        $toTable   = clone $toTable;

        $this->normalizeColumns($fromTable);
        $this->normalizeColumns($toTable);

        return parent::diffTable($fromTable, $toTable);
    }

    private function normalizeColumns(Table $table): void
    {
        foreach ($table->getColumns() as $column) {
            $options = $column->getPlatformOptions();

            if (! isset($options['collation']) || $options['collation'] !== $this->databaseCollation) {
                continue;
            }

            unset($options['collation']);
            $column->setPlatformOptions($options);
        }
    }
}

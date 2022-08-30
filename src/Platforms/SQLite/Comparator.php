<?php

namespace Doctrine\DBAL\Platforms\SQLite;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\Table;

use function strcasecmp;

/**
 * Compares schemas in the context of SQLite platform.
 *
 * BINARY is the default column collation and should be ignored if specified explicitly.
 */
class Comparator extends BaseComparator
{
    /** @internal The comparator can be only instantiated by a schema manager. */
    public function __construct(SqlitePlatform $platform)
    {
        parent::__construct($platform);
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

            if (! isset($options['collation']) || strcasecmp($options['collation'], 'binary') !== 0) {
                continue;
            }

            unset($options['collation']);
            $column->setPlatformOptions($options);
        }
    }
}

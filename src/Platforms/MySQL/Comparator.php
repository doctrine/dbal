<?php

namespace Doctrine\DBAL\Platforms\MySQL;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\Table;

use function array_diff_assoc;
use function array_intersect_key;

/**
 * Compares schemas in the context of MySQL platform.
 *
 * In MySQL, unless specified explicitly, the column's character set and collation are inherited from its containing
 * table. So during comparison, an omitted value and the value that matches the default value of table in the
 * desired schema must be considered equal.
 */
class Comparator extends BaseComparator
{
    /**
     * @internal The comparator can be only instantiated by a schema manager.
     */
    public function __construct(AbstractMySQLPlatform $platform)
    {
        parent::__construct($platform);
    }

    /**
     * {@inheritDoc}
     */
    public function diffTable(Table $fromTable, Table $toTable)
    {
        $defaults = array_intersect_key($fromTable->getOptions(), [
            'charset'   => null,
            'collation' => null,
        ]);

        if ($defaults !== []) {
            $fromTable = clone $fromTable;
            $toTable   = clone $toTable;

            $this->normalizeColumns($fromTable, $defaults);
            $this->normalizeColumns($toTable, $defaults);
        }

        return parent::diffTable($fromTable, $toTable);
    }

    /**
     * @param array<string,mixed> $defaults
     */
    private function normalizeColumns(Table $table, array $defaults): void
    {
        foreach ($table->getColumns() as $column) {
            $options = $column->getPlatformOptions();
            $diff    = array_diff_assoc($options, $defaults);

            if ($diff === $options) {
                continue;
            }

            $column->setPlatformOptions($diff);
        }
    }
}

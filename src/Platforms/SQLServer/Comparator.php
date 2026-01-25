<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\SQLServer;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Override;

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

    #[Override]
    public function compareTables(Table $oldTable, Table $newTable): TableDiff
    {
        return parent::compareTables(
            $this->normalizeColumns($oldTable),
            $this->normalizeColumns($newTable),
        );
    }

    private function normalizeColumns(Table $table): Table
    {
        $editor = null;

        foreach ($table->getColumns() as $column) {
            $collation = $column->getCollation();

            if ($collation !== $this->databaseCollation) {
                continue;
            }

            ($editor ??= $table->edit())
                ->modifyColumn($column->getObjectName(), static function (ColumnEditor $editor): void {
                    $editor->setCollation(null);
                });
        }

        return $editor === null ? $table : $editor->create();
    }
}

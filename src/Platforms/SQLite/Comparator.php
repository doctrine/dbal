<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\SQLite;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Override;

use function strcasecmp;

/**
 * Compares schemas in the context of SQLite platform.
 *
 * BINARY is the default column collation and should be ignored if specified explicitly.
 */
class Comparator extends BaseComparator
{
    /** @internal The comparator can be only instantiated by a schema manager. */
    public function __construct(SQLitePlatform $platform, ComparatorConfig $config = new ComparatorConfig())
    {
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

            if ($collation !== null && strcasecmp($collation, 'binary') === 0) {
                ($editor ??= $table->edit())
                    ->modifyColumn($column->getObjectName(), static function (ColumnEditor $editor): void {
                        $editor->setCollation(null);
                    });
            }
        }

        return $editor === null ? $table : $editor->create();
    }
}

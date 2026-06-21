<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\PostgreSQL;

use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Override;

/**
 * Compares schemas in the context of PostgreSQL platform.
 *
 * PostgreSQL treats "default" collation as implicit, so an explicit "default" value should be ignored when comparing
 * schemas.
 */
class Comparator extends BaseComparator
{
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
            if ($column->getCollation() === 'default') {
                ($editor ??= $table->edit())
                    ->modifyColumn($column->getObjectName(), static function (ColumnEditor $editor): void {
                        $editor->setCollation(null);
                    });
            }
        }

        return $editor === null ? $table : $editor->create();
    }
}

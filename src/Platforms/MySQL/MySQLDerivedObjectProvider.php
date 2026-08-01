<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL;

use Doctrine\DBAL\Platforms\AbstractDerivedObjectProvider;
use Doctrine\DBAL\Schema\DerivedObject;
use Doctrine\DBAL\Schema\DerivedObjectKind;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\Table;
use Override;

use function array_all;
use function array_map;
use function count;
use function usort;

/** @internal */
final class MySQLDerivedObjectProvider extends AbstractDerivedObjectProvider
{
    public function __construct(private readonly UnquotedIdentifierFolding $folding)
    {
    }

    /**
     * A unique index creates a unique constraint.
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/create-index.html
     */
    #[Override]
    protected function deriveObjectFromIndex(Table $table, Index $index): ?DerivedObject
    {
        if ($index->getType() !== IndexType::UNIQUE) {
            return null;
        }

        return new DerivedObject(DerivedObjectKind::UniqueConstraint, array_map(
            static fn (IndexedColumn $column) => $column->getColumnName(),
            $index->getIndexedColumns(),
        ));
    }

    /**
     * A foreign key may get an index over its referencing columns.
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html
     */
    #[Override]
    protected function deriveObjectsFromForeignKeyConstraints(Table $table): array
    {
        // The columns of every index the table will have: the ones it declares, and the ones derived
        // for its foreign keys below.
        $indexColumnNameLists = [];

        $primaryKeyConstraint = $table->getPrimaryKeyConstraint();

        foreach ($table->getIndexes() as $index) {
            $indexColumnNameLists[] = $this->getColumnNamesIndexedInFull($index);
        }

        if ($primaryKeyConstraint !== null) {
            $indexColumnNameLists[] = $primaryKeyConstraint->getColumnNames();
        }

        foreach ($table->getUniqueConstraints() as $uniqueConstraint) {
            $indexColumnNameLists[] = $uniqueConstraint->getColumnNames();
        }

        $foreignKeyColumnNameLists = [];

        foreach ($table->getForeignKeys() as $constraint) {
            $foreignKeyColumnNameLists[] = $constraint->getReferencingColumnNames();
        }

        // The widest first: an index over a foreign key's columns serves every foreign key whose
        // columns it starts with, so the one derived below serves those behind it.
        usort(
            $foreignKeyColumnNameLists,
            static fn (array $a, array $b): int => count($b) <=> count($a),
        );

        $derivedObjects = [];

        foreach ($foreignKeyColumnNameLists as $columnNames) {
            foreach ($indexColumnNameLists as $indexColumnNames) {
                if ($this->columnNamesStartWith($indexColumnNames, $columnNames)) {
                    continue 2;
                }
            }

            $derivedObjects[] = new DerivedObject(DerivedObjectKind::RegularIndex, $columnNames);

            $indexColumnNameLists[] = $columnNames;
        }

        return $derivedObjects;
    }

    /**
     * Returns the index's leading columns, stopping before the first one it indexes by a prefix of
     * its value.
     *
     * @return list<UnqualifiedName>
     */
    private function getColumnNamesIndexedInFull(Index $index): array
    {
        $columnNames = [];

        foreach ($index->getIndexedColumns() as $indexedColumn) {
            if ($indexedColumn->getLength() !== null) {
                break;
            }

            $columnNames[] = $indexedColumn->getColumnName();
        }

        return $columnNames;
    }

    /**
     * @param list<UnqualifiedName>           $columnNames
     * @param non-empty-list<UnqualifiedName> $leadingColumnNames
     */
    private function columnNamesStartWith(array $columnNames, array $leadingColumnNames): bool
    {
        if (count($columnNames) < count($leadingColumnNames)) {
            return false;
        }

        return array_all(
            $leadingColumnNames,
            fn ($leadingColumnName, $i) => $columnNames[$i]->equals($leadingColumnName, $this->folding),
        );
    }
}

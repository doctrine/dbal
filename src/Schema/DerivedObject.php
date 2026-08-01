<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;

use function array_all;
use function count;

/**
 * An object introspection reports beside the ones the application declared.
 */
final readonly class DerivedObject
{
    /** @param non-empty-list<UnqualifiedName> $columnNames */
    public function __construct(private DerivedObjectKind $kind, private array $columnNames)
    {
    }

    /**
     * Tests if this derived object matches the index introspected from the database.
     */
    public function matchesIndex(Index $index, UnquotedIdentifierFolding $folding): bool
    {
        $type = match ($this->kind) {
            DerivedObjectKind::UniqueIndex => IndexType::UNIQUE,
            DerivedObjectKind::RegularIndex => IndexType::REGULAR,
            DerivedObjectKind::UniqueConstraint => null,
        };

        if ($index->getType() !== $type) {
            return false;
        }

        if ($index->getPredicate() !== null) {
            return false;
        }

        $columns = $index->getIndexedColumns();

        if (count($columns) !== count($this->columnNames)) {
            return false;
        }

        foreach ($columns as $i => $column) {
            if ($column->getLength() !== null) {
                return false;
            }

            if (! $column->getColumnName()->equals($this->columnNames[$i], $folding)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tests if this derived object matches the unique constraint introspected from the database.
     */
    public function matchesUniqueConstraint(UniqueConstraint $constraint, UnquotedIdentifierFolding $folding): bool
    {
        if ($this->kind !== DerivedObjectKind::UniqueConstraint) {
            return false;
        }

        $columnNames = $constraint->getColumnNames();

        if (count($columnNames) !== count($this->columnNames)) {
            return false;
        }

        return array_all($columnNames, fn ($columnName, $i) => $columnName->equals($this->columnNames[$i], $folding));
    }
}

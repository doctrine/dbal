<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Introspection\MetadataProcessor;

use Doctrine\DBAL\Schema\Metadata\PrimaryKeyConstraintColumnRow;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\PrimaryKeyConstraintEditor;

/**
 * Combines multiple {@see PrimaryKeyConstraintColumnRow}s into a {@see PrimaryKeyConstraint}.
 *
 * @internal Should be used only by {@link IntrospectingSchemaProvider}.
 */
final readonly class PrimaryKeyConstraintColumnMetadataProcessor
{
    public function initializeEditor(PrimaryKeyConstraintColumnRow $row): PrimaryKeyConstraintEditor
    {
        // Ignore the constraint name in 4.x, since it represents primary key constraints as indexes, and the name of
        // the constraint may conflict with the name of its backing index.
        return PrimaryKeyConstraint::editor()
            ->setIsClustered($row->isClustered());
    }

    public function applyRow(PrimaryKeyConstraintEditor $editor, PrimaryKeyConstraintColumnRow $row): void
    {
        $editor->addColumnName(
            UnqualifiedName::quoted($row->getColumnName()),
        );
    }
}

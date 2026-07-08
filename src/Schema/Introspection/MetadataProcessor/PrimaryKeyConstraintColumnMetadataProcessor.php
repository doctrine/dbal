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
        $editor = PrimaryKeyConstraint::editor();

        $constraintName = $row->getConstraintName();
        if ($constraintName !== null) {
            $editor->setName(
                UnqualifiedName::quoted($constraintName),
            );
        }

        return $editor->setIsClustered($row->isClustered());
    }

    public function applyRow(PrimaryKeyConstraintEditor $editor, PrimaryKeyConstraintColumnRow $row): void
    {
        $editor->addQuotedColumnName($row->getColumnName());
    }
}

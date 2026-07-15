<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Introspection\MetadataProcessor;

use Doctrine\DBAL\Schema\Metadata\UniqueConstraintColumnMetadataRow;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Schema\UniqueConstraintEditor;

/**
 * Combines multiple {@see UniqueConstraintColumnMetadataRow}s into a {@see UniqueConstraint}.
 *
 * @internal Should be used only by {@link IntrospectingSchemaProvider}.
 */
final readonly class UniqueConstraintColumnMetadataProcessor
{
    public function initializeEditor(UniqueConstraintColumnMetadataRow $row): UniqueConstraintEditor
    {
        $editor = UniqueConstraint::editor();

        $name = $row->getName();
        if ($name !== null) {
            $editor->setQuotedName($name);
        }

        return $editor;
    }

    public function applyRow(UniqueConstraintEditor $editor, UniqueConstraintColumnMetadataRow $row): void
    {
        $editor->addQuotedColumnName($row->getColumnName());
    }
}

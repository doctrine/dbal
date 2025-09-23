<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Introspection\MetadataProcessor;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\ForeignKeyConstraintEditor;
use Doctrine\DBAL\Schema\Metadata\ForeignKeyConstraintColumnMetadataRow;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

/**
 * Combines multiple {@see ForeignKeyConstraintColumnMetadataRow}s into a {@see ForeignKeyConstraint}.
 *
 * @internal Should be used only by {@link IntrospectingSchemaProvider}.
 */
final readonly class ForeignKeyConstraintColumnMetadataProcessor
{
    public function __construct(private ?string $currentSchemaName)
    {
    }

    public function initializeEditor(ForeignKeyConstraintColumnMetadataRow $row): ForeignKeyConstraintEditor
    {
        $editor = ForeignKeyConstraint::editor();

        $constraintName = $row->getName();
        if ($constraintName !== null) {
            $editor->setName(
                UnqualifiedName::quoted($constraintName),
            );
        }

        $referencedSchemaName = $row->getReferencedSchemaName();
        if ($referencedSchemaName === $this->currentSchemaName) {
            $referencedSchemaName = null;
        }

        $editor
            ->setReferencedTableName(
                OptionallyQualifiedName::quoted(
                    $row->getReferencedTableName(),
                    $referencedSchemaName,
                ),
            )
            ->setMatchType($row->getMatchType())
            ->setOnUpdateAction($row->getOnUpdateAction())
            ->setOnDeleteAction($row->getOnDeleteAction());

        if ($row->isDeferred()) {
            $editor->setDeferrability(Deferrability::DEFERRED);
        } elseif ($row->isDeferrable()) {
            $editor->setDeferrability(Deferrability::DEFERRABLE);
        }

        return $editor;
    }

    public function applyRow(ForeignKeyConstraintEditor $editor, ForeignKeyConstraintColumnMetadataRow $row): void
    {
        $editor
            ->addReferencingColumnName(
                UnqualifiedName::quoted($row->getReferencingColumnName()),
            )
            ->addReferencedColumnName(
                UnqualifiedName::quoted($row->getReferencedColumnName()),
            );
    }
}

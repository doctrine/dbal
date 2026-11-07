<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Introspection;

use Doctrine\DBAL\Schema\PrimaryKeyConstraintEditor;
use Doctrine\DBAL\Schema\UniqueConstraintEditor;

/**
 * The editor of an introspected constraint, with the name of the index that the catalog reports for the constraint.
 *
 * @internal Should be used only by {@link IntrospectingSchemaProvider}.
 *
 * @template E of PrimaryKeyConstraintEditor|UniqueConstraintEditor
 */
final readonly class IntrospectedConstraint
{
    /**
     * @param E                 $editor
     * @param ?non-empty-string $indexName
     */
    public function __construct(
        private PrimaryKeyConstraintEditor|UniqueConstraintEditor $editor,
        private ?string $indexName,
        private bool $isDerived,
    ) {
    }

    /** @return E */
    public function getEditor(): PrimaryKeyConstraintEditor|UniqueConstraintEditor
    {
        return $this->editor;
    }

    /** @return ?non-empty-string */
    public function getIndexName(): ?string
    {
        return $this->indexName;
    }

    public function isDerived(): bool
    {
        return $this->isDerived;
    }
}

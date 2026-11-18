<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Introspection;

use Doctrine\DBAL\Schema\ObjectOrigin;
use Doctrine\DBAL\Schema\PrimaryKeyConstraintEditor;
use Doctrine\DBAL\Schema\UniqueConstraintEditor;

/**
 * Pairs the editor of an introspected constraint with the name of the index that the catalog links to the
 * constraint, and with the constraint's origin.
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
        private ObjectOrigin $origin,
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

    public function getOrigin(): ObjectOrigin
    {
        return $this->origin;
    }
}

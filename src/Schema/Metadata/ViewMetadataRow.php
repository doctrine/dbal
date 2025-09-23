<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Metadata;

/**
 * A row of metadata describing a view.
 */
final readonly class ViewMetadataRow
{
    /**
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $viewName
     */
    public function __construct(
        private ?string $schemaName,
        private string $viewName,
        private string $definition,
    ) {
    }

    /** @return ?non-empty-string */
    public function getSchemaName(): ?string
    {
        return $this->schemaName;
    }

    /** @return non-empty-string */
    public function getViewName(): string
    {
        return $this->viewName;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }
}

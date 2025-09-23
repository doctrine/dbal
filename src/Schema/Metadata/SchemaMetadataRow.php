<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Metadata;

/**
 * A row of metadata describing a schema.
 */
final readonly class SchemaMetadataRow
{
    /** @param non-empty-string $schemaName */
    public function __construct(private string $schemaName)
    {
    }

    /** @return non-empty-string */
    public function getSchemaName(): string
    {
        return $this->schemaName;
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Metadata;

/**
 * A row of metadata describing a table.
 */
final readonly class TableMetadataRow
{
    /**
     * @param ?non-empty-string              $schemaName
     * @param non-empty-string               $tableName
     * @param array<non-empty-string, mixed> $options
     */
    public function __construct(
        private ?string $schemaName,
        private string $tableName,
        private array $options,
    ) {
    }

    /** @return ?non-empty-string */
    public function getSchemaName(): ?string
    {
        return $this->schemaName;
    }

    /** @return non-empty-string */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /** @return array<non-empty-string, mixed> */
    public function getOptions(): array
    {
        return $this->options;
    }
}

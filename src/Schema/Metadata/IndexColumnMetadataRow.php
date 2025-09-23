<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Metadata;

use Doctrine\DBAL\Schema\Index\IndexType;

/**
 * A row of metadata describing an index column.
 */
final readonly class IndexColumnMetadataRow
{
    /**
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     * @param non-empty-string  $indexName
     * @param ?non-empty-string $predicate
     * @param non-empty-string  $columnName
     * @param ?positive-int     $columnLength
     */
    public function __construct(
        private ?string $schemaName,
        private string $tableName,
        private string $indexName,
        private IndexType $type,
        private bool $isClustered,
        private ?string $predicate,
        private string $columnName,
        private ?int $columnLength,
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

    /** @return non-empty-string */
    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getType(): IndexType
    {
        return $this->type;
    }

    public function isClustered(): bool
    {
        return $this->isClustered;
    }

    /** @return ?non-empty-string */
    public function getPredicate(): ?string
    {
        return $this->predicate;
    }

    /** @return non-empty-string */
    public function getColumnName(): string
    {
        return $this->columnName;
    }

    /** @return ?positive-int */
    public function getColumnLength(): ?int
    {
        return $this->columnLength;
    }
}

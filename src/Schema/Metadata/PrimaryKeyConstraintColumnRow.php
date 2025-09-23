<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Metadata;

/**
 * A row of metadata describing a primary key constraint column.
 */
final readonly class PrimaryKeyConstraintColumnRow
{
    /**
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     * @param ?non-empty-string $constraintName
     * @param non-empty-string  $columnName
     */
    public function __construct(
        private ?string $schemaName,
        private string $tableName,
        private ?string $constraintName,
        private bool $isClustered,
        private string $columnName,
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

    /** @return ?non-empty-string */
    public function getConstraintName(): ?string
    {
        return $this->constraintName;
    }

    public function isClustered(): bool
    {
        return $this->isClustered;
    }

    /** @return non-empty-string */
    public function getColumnName(): string
    {
        return $this->columnName;
    }
}

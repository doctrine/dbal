<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Metadata;

use Doctrine\DBAL\Schema\Column;

/**
 * A row of metadata describing a table column.
 *
 * Unlike other metadata row types, this one contains a full Column object. The reason is that most of the properties
 * the column carries belong to its data type, not the column itself. Therefore, it is more practical to interpret
 * type-specific properties during introspection within a metadata provider and instantiate a complete Column object
 * right away instead of defining an abstraction to represent them.
 */
final readonly class TableColumnMetadataRow
{
    /**
     * @param ?non-empty-string $schemaName
     * @param non-empty-string  $tableName
     */
    public function __construct(
        private ?string $schemaName,
        private string $tableName,
        private Column $column,
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

    public function getColumn(): Column
    {
        return $this->column;
    }
}
